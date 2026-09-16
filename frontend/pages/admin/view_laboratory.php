<?php
// ================================================================
// FILE: frontend/pages/admin/view_laboratory.php
// ADMIN - VIEW LABORATORY BRANCH DETAILS
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

// ================================================================
// GET PARAMETERS
// ================================================================
$lab_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';

if ($lab_id <= 0) {
    header('Location: laboratories.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH LABORATORY DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT b.*,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory' AND status = 'active') as active_technicians,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory') as total_technicians,
        (SELECT COUNT(*) FROM lab_tests_catalog WHERE branch_id = b.id AND is_active = 1) as total_tests,
        (SELECT COUNT(*) FROM lab_tests WHERE branch_id = b.id AND status = 'pending') as pending_tests,
        (SELECT COUNT(*) FROM lab_tests WHERE branch_id = b.id AND status = 'in_progress') as in_progress_tests,
        (SELECT COUNT(*) FROM lab_tests WHERE branch_id = b.id AND status = 'completed') as completed_tests,
        (SELECT COUNT(*) FROM lab_tests WHERE branch_id = b.id AND status = 'cancelled') as cancelled_tests,
        (SELECT COUNT(*) FROM lab_tests WHERE branch_id = b.id) as total_tests_done,
        (SELECT COALESCE(SUM(price), 0) FROM lab_tests_catalog WHERE branch_id = b.id AND is_active = 1) as total_catalog_value,
        (SELECT COUNT(*) FROM activity_logs WHERE branch_id = b.id AND (action LIKE '%lab%' OR action LIKE '%test%' OR action LIKE '%laboratory%')) as total_activities
    FROM branches b
    WHERE b.id = ?
");
$stmt->execute([$lab_id]);
$laboratory = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$laboratory) {
    header('Location: laboratories.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
    exit;
}

// ================================================================
// GET TECHNICIANS
// ================================================================
$technicians = [];
try {
    $stmt = $db->prepare("SELECT id, full_name, email, phone, status, created_at, is_online FROM users WHERE branch_id = ? AND role = 'laboratory' ORDER BY full_name");
    $stmt->execute([$lab_id]);
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $technicians = []; }

// ================================================================
// GET RECENT LAB TESTS
// ================================================================
$recent_tests = [];
try {
    $stmt = $db->prepare("
        SELECT lt.id, lt.test_name, lt.status, lt.created_at, lt.completed_at, lt.test_price,
               p.full_name as patient_name, p.patient_id as patient_code,
               u.full_name as doctor_name, v.visit_number
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        WHERE lt.branch_id = ?
        ORDER BY lt.created_at DESC LIMIT 10
    ");
    $stmt->execute([$lab_id]);
    $recent_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_tests = []; }

// ================================================================
// GET RECENT ACTIVITIES
// ================================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT al.*, u.full_name as user_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.branch_id = ? AND (al.action LIKE '%lab%' OR al.action LIKE '%test%' OR al.action LIKE '%laboratory%')
        ORDER BY al.created_at DESC LIMIT 10
    ");
    $stmt->execute([$lab_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_activities = []; }

// ================================================================
// STATUS HELPERS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success', 'inactive' => 'danger', 'pending' => 'warning',
        'in_progress' => 'info', 'completed' => 'success', 'cancelled' => 'danger',
        'accepted' => 'primary', 'paid' => 'success', 'partial' => 'warning',
        'online' => 'success', 'offline' => 'secondary'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'active' => 'fa-check-circle', 'inactive' => 'fa-times-circle',
        'pending' => 'fa-clock', 'in_progress' => 'fa-spinner fa-spin',
        'completed' => 'fa-check-circle', 'cancelled' => 'fa-times-circle',
        'accepted' => 'fa-check-double', 'paid' => 'fa-check-circle',
        'online' => 'fa-circle', 'offline' => 'fa-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
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
    /* PAGE HEADER */
    .page-header-lab {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 18px;
        padding: 28px 36px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
        position: relative;
        overflow: hidden;
    }

    .page-header-lab::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-lab .page-title-lab {
        color: white;
        font-size: 1.8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-lab .page-subtitle-lab {
        color: rgba(255,255,255,0.85);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }

    .page-header-lab .role-badge-display {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .page-header-lab .header-badge-lab {
        background: rgba(255,255,255,0.12);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .page-header-lab .btn-outline-light-lab {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: 12px;
        font-weight: 500;
        font-size: 0.82rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        position: relative;
        z-index: 1;
        transition: all 0.3s;
    }

    .page-header-lab .btn-outline-light-lab:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* STATS GRID - 3+3 */
    .stats-grid-lab {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
        margin-bottom: 24px;
    }

    .stat-card-lab {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 12px;
        padding: 20px 24px;
        border: 2px solid rgba(255,255,255,0.1);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(11, 94, 215, 0.2);
        text-decoration: none;
        color: white;
        position: relative;
        overflow: hidden;
        min-height: 90px;
    }

    .stat-card-lab::before {
        content: '';
        position: absolute;
        top: -50%; right: -20%;
        width: 150px; height: 150px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .stat-card-lab:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(11, 94, 215, 0.35);
        border-color: rgba(255,255,255,0.2);
        color: white;
    }

    .stat-card-lab .stat-icon-lab {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1px solid rgba(255,255,255,0.1);
        position: relative;
        z-index: 1;
    }

    .stat-card-lab .stat-label-lab {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.8);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
        position: relative;
        z-index: 1;
    }

    .stat-card-lab .stat-value-lab {
        font-size: 1.5rem;
        font-weight: 700;
        color: white;
        margin: 0;
        line-height: 1.2;
        position: relative;
        z-index: 1;
    }

    .stat-card-lab .stat-sub-lab {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.6);
        margin-top: 2px;
        position: relative;
        z-index: 1;
    }

    .stat-card-lab .stat-arrow-lab {
        opacity: 0;
        transition: all 0.3s ease;
        color: rgba(255,255,255,0.6);
        font-size: 0.8rem;
        flex-shrink: 0;
        position: relative;
        z-index: 1;
    }

    .stat-card-lab:hover .stat-arrow-lab {
        opacity: 1;
        transform: translateX(4px);
    }

    /* DETAIL CARD */
    .detail-card-lab {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        padding: 24px 28px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm);
        margin-bottom: 24px;
    }

    [data-theme="dark"] .detail-card-lab { background: #1E293B; border-color: #334155; }

    .detail-card-lab:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: var(--page-shadow-md);
    }

    .detail-label-lab {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .detail-value-lab {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
    }

    /* CARD */
    .card-lab {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm);
        margin-bottom: 24px;
    }

    [data-theme="dark"] .card-lab { background: #1E293B; border-color: #334155; }

    .card-lab:hover {
        box-shadow: var(--page-shadow-md);
    }

    .card-header-lab {
        padding: 16px 24px;
        background: var(--page-hover, #F8FAFC);
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    [data-theme="dark"] .card-header-lab { background: #0F172A; }

    .card-title-lab {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .card-title-lab i { color: var(--page-primary, #0B5ED7); }

    /* DATA TABLE */
    .data-table-lab {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8rem;
    }

    .data-table-lab thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        font-weight: 600;
        padding: 10px 12px;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
        text-align: left;
    }

    .data-table-lab thead th:first-child { border-radius: 8px 0 0 0; }
    .data-table-lab thead th:last-child { border-radius: 0 8px 0 0; }

    .data-table-lab td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
    }

    .data-table-lab tbody tr:hover td { background: var(--page-hover, #F8FAFC); }
    [data-theme="dark"] .data-table-lab tbody tr:hover td { background: #1E293B; }
    .data-table-lab tbody tr:last-child td { border-bottom: none; }

    /* BADGES */
    .badge-lab {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        color: white;
    }

    .badge-success-lab { background: #059669; }
    .badge-danger-lab { background: #DC2626; }
    .badge-warning-lab { background: #D97706; color: #1E293B; }
    .badge-info-lab { background: #0B5ED7; }
    .badge-secondary-lab { background: #64748B; }
    .badge-primary-lab { background: #0B5ED7; }

    [data-theme="dark"] .badge-warning-lab { color: #1E293B; }

    /* BUTTONS */
    .btn-lab {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
    }

    .btn-lab:hover { transform: translateY(-2px); box-shadow: var(--page-shadow-md); }

    .btn-sm-lab {
        padding: 4px 10px;
        font-size: 0.65rem;
        border-radius: 6px;
    }

    .btn-primary-lab {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
    }

    .btn-primary-lab:hover {
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
        color: white;
    }

    .btn-outline-lab {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    .btn-outline-lab:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
    }

    /* EMPTY STATE */
    .empty-state-lab {
        text-align: center;
        padding: 30px 20px;
        color: var(--page-text-secondary, #64748B);
    }

    .empty-state-lab i {
        font-size: 2.5rem;
        color: var(--page-text-muted, #94A3B8);
        margin-bottom: 12px;
        display: block;
    }

    .empty-state-lab h4 {
        font-size: 1rem;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 4px;
    }

    /* ACTIVITY ITEM */
    .activity-item-lab {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        transition: all 0.3s;
    }

    .activity-item-lab:hover { background: var(--page-hover, #F8FAFC); }
    [data-theme="dark"] .activity-item-lab:hover { background: #0F172A; }

    .activity-icon-lab {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #0B5ED7;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: white;
    }

    /* FOOTER */
    .footer-lab {
        padding: 14px 0;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-lab .footer-brand-lab { color: #0B5ED7; font-weight: 600; }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .stats-grid-lab { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-lab { padding: 16px 18px; }
        .page-header-lab .page-title-lab { font-size: 1.3rem; }
        .stats-grid-lab { grid-template-columns: 1fr 1fr; }
        .detail-card-lab { padding: 16px; }
        .data-table-lab { font-size: 0.7rem; }
        .data-table-lab thead th, .data-table-lab td { padding: 6px 8px; }
    }

    @media (max-width: 480px) {
        .stats-grid-lab { grid-template-columns: 1fr; }
        .page-header-lab { flex-direction: column; align-items: flex-start !important; }
        .card-header-lab { flex-direction: column; align-items: flex-start; }
        .btn-lab { width: 100%; justify-content: center; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-lab">
        <div>
            <h1 class="page-title-lab">
                <i class="fas fa-microscope"></i>
                Laboratory Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle-lab">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($laboratory['name']) ?></strong>
                <span class="header-badge-lab">
                    <i class="fas fa-<?= $laboratory['status'] === 'active' ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= ucfirst($laboratory['status']) ?>
                </span>
                <span class="header-badge-lab" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-flask"></i> <?= $laboratory['total_tests'] ?? 0 ?> Tests
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="edit_branch.php?id=<?= $laboratory['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light-lab">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="laboratories.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light-lab">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- LABORATORY INFO CARD -->
    <div class="detail-card-lab">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
            <div>
                <p class="detail-label-lab"><i class="fas fa-map-marker-alt"></i> Location</p>
                <p class="detail-value-lab"><?= htmlspecialchars($laboratory['location'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-lab"><i class="fas fa-phone"></i> Phone</p>
                <p class="detail-value-lab"><?= htmlspecialchars($laboratory['phone'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-lab"><i class="fas fa-envelope"></i> Email</p>
                <p class="detail-value-lab"><?= htmlspecialchars($laboratory['email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-lab"><i class="fas fa-calendar-plus"></i> Created</p>
                <p class="detail-value-lab"><?= date('M d, Y h:i A', strtotime($laboratory['created_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label-lab"><i class="fas fa-clock"></i> Last Updated</p>
                <p class="detail-value-lab"><?= date('M d, Y h:i A', strtotime($laboratory['updated_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label-lab"><i class="fas fa-user-md"></i> Technicians</p>
                <p class="detail-value-lab"><?= $laboratory['active_technicians'] ?? 0 ?> Active / <?= $laboratory['total_technicians'] ?? 0 ?> Total</p>
            </div>
        </div>
    </div>

    <!-- STATS CARDS - BLUE BACKGROUND (3+3) -->
    <div class="stats-grid-lab">
        <a href="lab_tests.php?branch=<?= $laboratory['id'] ?>" class="stat-card-lab">
            <div class="stat-icon-lab"><i class="fas fa-flask"></i></div>
            <div>
                <p class="stat-label-lab">Total Tests</p>
                <p class="stat-value-lab"><?= number_format($laboratory['total_tests_done'] ?? 0) ?></p>
                <p class="stat-sub-lab"><?= $laboratory['pending_tests'] ?? 0 ?> pending</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-lab"></i>
        </a>
        
        <a href="lab_tests.php?branch=<?= $laboratory['id'] ?>&status=completed" class="stat-card-lab">
            <div class="stat-icon-lab"><i class="fas fa-check-circle"></i></div>
            <div>
                <p class="stat-label-lab">Completed</p>
                <p class="stat-value-lab"><?= number_format($laboratory['completed_tests'] ?? 0) ?></p>
                <p class="stat-sub-lab">Tests finalized</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-lab"></i>
        </a>
        
        <a href="lab_tests.php?branch=<?= $laboratory['id'] ?>&status=in_progress" class="stat-card-lab">
            <div class="stat-icon-lab"><i class="fas fa-spinner fa-spin"></i></div>
            <div>
                <p class="stat-label-lab">In Progress</p>
                <p class="stat-value-lab"><?= number_format($laboratory['in_progress_tests'] ?? 0) ?></p>
                <p class="stat-sub-lab">Tests running</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-lab"></i>
        </a>
        
        <a href="lab_test_catalog.php?branch=<?= $laboratory['id'] ?>" class="stat-card-lab">
            <div class="stat-icon-lab"><i class="fas fa-book"></i></div>
            <div>
                <p class="stat-label-lab">Catalog Value</p>
                <p class="stat-value-lab">TSh <?= number_format($laboratory['total_catalog_value'] ?? 0, 0) ?></p>
                <p class="stat-sub-lab"><?= $laboratory['total_tests'] ?? 0 ?> tests available</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-lab"></i>
        </a>
        
        <a href="employees.php?branch=<?= $laboratory['id'] ?>&role=laboratory" class="stat-card-lab">
            <div class="stat-icon-lab"><i class="fas fa-user-md"></i></div>
            <div>
                <p class="stat-label-lab">Technicians</p>
                <p class="stat-value-lab"><?= $laboratory['active_technicians'] ?? 0 ?></p>
                <p class="stat-sub-lab"><?= $laboratory['total_technicians'] ?? 0 ?> total</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-lab"></i>
        </a>
        
        <a href="system_logs.php?branch=<?= $laboratory['id'] ?>" class="stat-card-lab">
            <div class="stat-icon-lab"><i class="fas fa-clock"></i></div>
            <div>
                <p class="stat-label-lab">Activities</p>
                <p class="stat-value-lab"><?= number_format($laboratory['total_activities'] ?? 0) ?></p>
                <p class="stat-sub-lab">Lab activities logged</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-lab"></i>
        </a>
    </div>

    <!-- TECHNICIANS LIST -->
    <div class="card-lab">
        <div class="card-header-lab">
            <h3 class="card-title-lab">
                <i class="fas fa-user-md"></i>
                Lab Technicians (<?= count($technicians) ?>)
            </h3>
            <a href="add_employee.php?branch=<?= $laboratory['id'] ?>&role=laboratory" class="btn-lab btn-sm-lab btn-primary-lab">
                <i class="fas fa-plus"></i> Add Technician
            </a>
        </div>
        <div style="overflow-x:auto;">
            <?php if (count($technicians) > 0): ?>
                <table class="data-table-lab">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($technicians as $tech): ?>
                            <tr>
                                <td style="font-weight:500;">
                                    <?= htmlspecialchars($tech['full_name'] ?? 'N/A') ?>
                                    <?php if (isset($tech['is_online']) && $tech['is_online'] == 1): ?>
                                        <span class="badge-lab badge-success-lab" style="font-size:0.5rem;padding:1px 8px;">
                                            <i class="fas fa-circle"></i> Online
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($tech['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($tech['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-lab badge-<?= $tech['status'] === 'active' ? 'success' : 'danger' ?>-lab" style="font-size:0.6rem;padding:2px 10px;">
                                        <?= ucfirst($tech['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_employee.php?id=<?= $tech['id'] ?>&branch=<?= $laboratory['id'] ?>" class="btn-lab btn-sm-lab btn-primary-lab">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state-lab">
                    <i class="fas fa-user-md"></i>
                    <h4>No Technicians</h4>
                    <p>No technicians assigned to this laboratory branch.</p>
                    <a href="add_employee.php?branch=<?= $laboratory['id'] ?>&role=laboratory" class="btn-lab btn-sm-lab btn-primary-lab" style="margin-top:8px;">
                        <i class="fas fa-plus"></i> Add Technician
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RECENT LAB TESTS -->
    <div class="card-lab">
        <div class="card-header-lab">
            <h3 class="card-title-lab">
                <i class="fas fa-flask" style="color:#7C3AED;"></i>
                Recent Lab Tests
            </h3>
            <a href="lab_tests.php?branch=<?= $laboratory['id'] ?>" style="font-size:0.75rem;color:#0B5ED7;font-weight:500;text-decoration:none;">View All →</a>
        </div>
        <div style="overflow-x:auto;">
            <?php if (count($recent_tests) > 0): ?>
                <table class="data-table-lab">
                    <thead>
                        <tr>
                            <th>Test</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Price</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_tests as $test): ?>
                            <tr>
                                <td style="font-weight:500;"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td>
                                    <?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?>
                                    <div style="font-size:0.65rem;color:var(--page-text-muted);"><?= htmlspecialchars($test['patient_code'] ?? '') ?></div>
                                </td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-lab badge-<?= getStatusBadge($test['status'] ?? 'pending') ?>-lab" style="font-size:0.6rem;padding:2px 10px;">
                                        <i class="fas <?= getStatusIcon($test['status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td>TSh <?= number_format($test['test_price'] ?? 0, 0) ?></td>
                                <td>
                                    <a href="view_lab_test.php?id=<?= $test['id'] ?>&branch=<?= $laboratory['id'] ?>" class="btn-lab btn-sm-lab btn-primary-lab">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state-lab">
                    <i class="fas fa-flask"></i>
                    <h4>No Lab Tests</h4>
                    <p>No lab tests found for this laboratory.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RECENT ACTIVITIES -->
    <div class="card-lab">
        <div class="card-header-lab">
            <h3 class="card-title-lab">
                <i class="fas fa-clock" style="color:#64748B;"></i>
                Recent Activities
            </h3>
            <a href="system_logs.php?branch=<?= $laboratory['id'] ?>" style="font-size:0.75rem;color:#0B5ED7;font-weight:500;text-decoration:none;">View All →</a>
        </div>
        <div style="max-height:240px;overflow-y:auto;">
            <?php if (count($recent_activities) > 0): ?>
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item-lab">
                        <div class="activity-icon-lab">
                            <i class="fas fa-circle" style="font-size:6px;"></i>
                        </div>
                        <div>
                            <p style="font-weight:500;font-size:0.85rem;color:var(--page-text-primary);margin:0;">
                                <?php 
                                    $action_display = $activity['action'] ?? 'Action';
                                    $action_display = ucwords(str_replace('_', ' ', $action_display));
                                    echo htmlspecialchars($action_display);
                                ?>
                            </p>
                            <p style="font-size:0.75rem;color:var(--page-text-secondary);margin:2px 0;">
                                <?= htmlspecialchars($activity['details'] ?? '') ?>
                                <?php if (!empty($activity['user_name'])): ?>
                                    <span style="color:var(--page-text-muted);">by <?= htmlspecialchars($activity['user_name']) ?></span>
                                <?php endif; ?>
                            </p>
                            <p style="font-size:0.65rem;color:var(--page-text-muted);margin:0;">
                                <?= isset($activity['created_at']) ? date('M d, Y h:i A', strtotime($activity['created_at'])) : 'Just now' ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state-lab">
                    <i class="fas fa-clock"></i>
                    <h4>No Activities</h4>
                    <p>No activities found for this laboratory.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="detail-card-lab">
        <h3 style="font-size:0.85rem;font-weight:600;color:var(--page-text-secondary);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px;">
            <i class="fas fa-bolt" style="color:#0B5ED7;"></i> Quick Actions
        </h3>
        <div style="display:flex;flex-wrap:wrap;gap:12px;">
            <a href="edit_branch.php?id=<?= $laboratory['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-lab btn-primary-lab">
                <i class="fas fa-edit"></i> Edit Branch
            </a>
            <a href="add_employee.php?branch=<?= $laboratory['id'] ?>&role=laboratory" class="btn-lab btn-primary-lab">
                <i class="fas fa-user-plus"></i> Add Technician
            </a>
            <a href="lab_tests.php?branch=<?= $laboratory['id'] ?>" class="btn-lab btn-primary-lab">
                <i class="fas fa-flask"></i> View Tests
            </a>
            <a href="laboratories.php?branch=<?= $selected_branch_id ?>" class="btn-lab btn-outline-lab">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer-lab">
        <p>
            <span class="footer-brand-lab">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Laboratory Details - <?= htmlspecialchars($laboratory['name']) ?>
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

    console.log('%c🔬 Braick - View Laboratory', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ NO duplicate header JavaScript', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c🏥 Laboratory: <?= htmlspecialchars($laboratory['name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🧪 Total Tests: <?= number_format($laboratory['total_tests_done'] ?? 0) ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>