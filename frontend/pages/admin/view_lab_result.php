<?php
// ================================================================
// FILE: frontend/pages/admin/view_lab_result.php
// ADMIN - VIEW LAB RESULT DETAILS
// ✅ Uses SHARED header & sidebar
// ✅ Full dark mode support
// ✅ Blue page header card
// ✅ Only "Add Results" button
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

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

// VERIFY USER
$stmt = $db->prepare("SELECT id, full_name, role, status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] !== 'active') {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// UNREAD NOTIFICATIONS
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// PARAMETERS
$lab_test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($lab_test_id <= 0) {
    header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// FETCH LAB TEST
try {
    $stmt = $db->prepare("
        SELECT 
            lt.*,
            lt.id as lab_test_id,
            p.id as patient_id,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            p.gender as patient_gender,
            p.date_of_birth,
            p.blood_group,
            p.allergies,
            p.address,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            u2.full_name as technician_name,
            v.visit_number,
            v.visit_type,
            v.id as visit_id,
            v.symptoms,
            v.complaint,
            v.diagnosis,
            v.treatment,
            b.name as branch_name,
            ltc.test_code,
            ltc.category as test_category
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN users u2 ON lt.technician_id = u2.id
        LEFT JOIN branches b ON lt.branch_id = b.id
        LEFT JOIN lab_tests_catalog ltc ON lt.test_id = ltc.id
        WHERE lt.id = ?
    ");
    $stmt->execute([$lab_test_id]);
    $lab_test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lab_test) {
        header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching lab test: " . $e->getMessage());
    header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// CALCULATE AGE
$age = null;
if (!empty($lab_test['date_of_birth'])) {
    $birthDate = new DateTime($lab_test['date_of_birth']);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
}

// GET BRANCHES
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// HELPERS
function getStatusBadge($status) {
    $classes = [
        'pending' => 'warning',
        'in_progress' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
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

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// INCLUDE SHARED HEADER & SIDEBAR
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
        --page-hover: #F8FAFC;
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

    /* PAGE HEADER */
    .page-header-custom {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083D8A 100%);
        border-radius: 18px;
        padding: 28px 36px;
        margin-bottom: 24px;
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
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
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
    }

    .page-header-custom .page-title i {
        width: 48px; height: 48px;
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
    }

    .page-header-custom .header-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-custom .btn-outline-light {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1.5px solid rgba(255,255,255,0.3);
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.82rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        position: relative;
        z-index: 1;
    }

    .page-header-custom .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* INFO BOX */
    .info-box-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-left: 4px solid #0B5ED7;
        margin-bottom: 20px;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    html[data-theme="dark"] .info-box-custom {
        background: #1E293B;
        border-color: #334155;
        border-left-color: #6EA8FE;
    }

    .info-box-custom .info-item {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        font-size: 0.85rem;
        flex-wrap: wrap;
        gap: 4px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .info-box-custom .info-item {
        border-bottom-color: #334155;
    }

    .info-box-custom .info-item:last-child { border-bottom: none; }

    .info-box-custom .info-item .label {
        color: var(--page-text-secondary, #64748B);
        font-weight: 600;
    }

    .info-box-custom .info-item .value {
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
    }

    html[data-theme="dark"] .info-box-custom .info-item .value { color: #F1F5F9; }

    .info-box-custom .info-item .value a {
        color: #0B5ED7;
        text-decoration: none;
        font-weight: 700;
    }

    .info-box-custom .info-item .value a:hover {
        text-decoration: underline;
    }

    html[data-theme="dark"] .info-box-custom .info-item .value a { color: #6EA8FE; }

    /* RESULT CARD */
    .result-card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 20px;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }

    html[data-theme="dark"] .result-card-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .result-card-custom:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.08);
    }

    .result-card-custom .result-label {
        font-size: 0.75rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .result-card-custom .result-label i {
        color: #0B5ED7;
        font-size: 0.9rem;
    }

    html[data-theme="dark"] .result-card-custom .result-label i { color: #6EA8FE; }

    .result-card-custom .result-value {
        font-size: 0.95rem;
        font-weight: 500;
        color: var(--page-text-primary, #1E293B);
        white-space: pre-wrap;
        line-height: 1.7;
    }

    html[data-theme="dark"] .result-card-custom .result-value { color: #F1F5F9; }

    /* SPECIAL RESULT CARD (Interpretation) */
    .result-card-custom.interpretation {
        background: #EFF6FF;
        border-color: #93C5FD;
        border-left: 4px solid #0B5ED7;
    }

    html[data-theme="dark"] .result-card-custom.interpretation {
        background: #1E3A5F;
        border-color: #3B82F6;
    }

    .result-card-custom.interpretation .result-label {
        color: #0A4CA8;
    }

    .result-card-custom.interpretation .result-value {
        color: #0A4CA8;
        font-weight: 600;
    }

    html[data-theme="dark"] .result-card-custom.interpretation .result-label,
    html[data-theme="dark"] .result-card-custom.interpretation .result-value {
        color: #93C5FD;
    }

    /* CLINICAL INFO GRID */
    .clinical-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    @media (max-width: 640px) {
        .clinical-grid { grid-template-columns: 1fr; }
    }

    /* BADGES */
    .badge-custom {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
        color: white;
    }

    .badge-success { background: #059669; }
    .badge-danger { background: #DC2626; }
    .badge-warning { background: #D97706; }
    .badge-info { background: #0B5ED7; }
    .badge-secondary { background: #64748B; }
    .badge-purple { background: #7C3AED; }

    /* BUTTONS */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-primary-custom {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
    }
    .btn-primary-custom:hover {
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(11, 94, 215, 0.35);
    }

    .btn-outline-custom {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }
    .btn-outline-custom:hover {
        background: var(--page-hover, #F1F5F9);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }

    /* ADD RESULTS BUTTON */
    .btn-add-results-custom {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        padding: 16px 32px;
        font-size: 1.1rem;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.35);
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s ease;
        text-decoration: none;
        font-weight: 700;
    }

    .btn-add-results-custom:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.5);
        color: white;
        background: linear-gradient(135deg, #0A4CA8, #083D8A);
    }

    .btn-add-results-custom i {
        font-size: 1.3rem;
    }

    .btn-add-results-custom .sub-text {
        font-size: 0.7rem;
        font-weight: 500;
        opacity: 0.85;
        display: block;
        margin-top: 2px;
    }

    /* ACTIONS CARD */
    .actions-card {
        background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid #93C5FD;
        margin-bottom: 24px;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }

    html[data-theme="dark"] .actions-card {
        background: #1E3A5F;
        border-color: #3B82F6;
    }

    .actions-card .actions-title {
        font-size: 0.85rem;
        font-weight: 800;
        color: #0A4CA8;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    html[data-theme="dark"] .actions-card .actions-title { color: #93C5FD; }

    .actions-card .actions-title i { color: #0B5ED7; }
    html[data-theme="dark"] .actions-card .actions-title i { color: #6EA8FE; }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .page-header-custom { padding: 18px; }
        .page-header-custom .page-title { font-size: 1.15rem; }
        .info-box-custom, .result-card-custom, .actions-card { padding: 16px; }
        .btn-add-results-custom {
            padding: 12px 20px;
            font-size: 0.95rem;
            width: 100%;
            justify-content: center;
        }
    }

    @media print {
        .page-header-custom { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
        .btn-add-results-custom, .btn-custom, .actions-card { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div style="position:relative;z-index:1;">
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Lab Result Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <span><i class="fas fa-vial"></i> <strong><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></strong></span>
                <span class="header-badge">
                    <i class="fas <?= getStatusIcon($lab_test['status'] ?? 'pending') ?>"></i>
                    <?= ucfirst($lab_test['status'] ?? 'Pending') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.25);border-color:rgba(52,211,153,0.4);color:#A7F3D0;">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.25);border-color:rgba(251,191,36,0.4);color:#FDE68A;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($lab_test['test_price'] ?? 0, 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- TEST INFORMATION -->
    <div class="info-box-custom">
        <div class="info-item">
            <span class="label">Test ID</span>
            <span class="value">#<?= $lab_test_id ?></span>
        </div>
        <div class="info-item">
            <span class="label">Test Name</span>
            <span class="value">
                <strong><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></strong>
                <?php if (!empty($lab_test['test_code'])) { ?>
                    <span style="color:var(--page-text-muted);font-size:0.8rem;">(<?= htmlspecialchars($lab_test['test_code']) ?>)</span>
                <?php } ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Category</span>
            <span class="value"><?= htmlspecialchars($lab_test['test_category'] ?? 'N/A') ?></span>
        </div>
        <div class="info-item">
            <span class="label">Patient</span>
            <span class="value">
                <?php if (!empty($lab_test['patient_id']) && !empty($lab_test['patient_name'])) { ?>
                    <a href="view_patient.php?id=<?= $lab_test['patient_id'] ?>&branch=<?= urlencode($selected_branch_id) ?>">
                        <?= htmlspecialchars($lab_test['patient_name']) ?>
                    </a>
                    <?php if (!empty($lab_test['patient_code'])) { ?>
                        (<?= htmlspecialchars($lab_test['patient_code']) ?>)
                    <?php } ?>
                    <?php if ($age !== null) { ?>
                        <span style="color:var(--page-text-muted);font-size:0.8rem;">| <?= $age ?> yrs</span>
                    <?php } ?>
                <?php } else { ?>
                    <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>
                <?php } ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Visit</span>
            <span class="value">
                <?php if (!empty($lab_test['visit_number']) && !empty($lab_test['visit_id'])) { ?>
                    <a href="view_visit.php?id=<?= $lab_test['visit_id'] ?>&branch=<?= urlencode($selected_branch_id) ?>">
                        <?= htmlspecialchars($lab_test['visit_number']) ?>
                    </a>
                <?php } else { ?>
                    <?= htmlspecialchars($lab_test['visit_number'] ?? 'N/A') ?>
                <?php } ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Doctor</span>
            <span class="value">
                <?php if (!empty($lab_test['doctor_name'])) { ?>
                    Dr. <?= htmlspecialchars($lab_test['doctor_name']) ?>
                    <?php if (!empty($lab_test['doctor_specialty'])) { ?>
                        <span style="color:var(--page-text-muted);font-size:0.8rem;">(<?= htmlspecialchars($lab_test['doctor_specialty']) ?>)</span>
                    <?php } ?>
                <?php } else { ?>
                    <span style="color:var(--page-text-muted);">Not assigned</span>
                <?php } ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Technician</span>
            <span class="value">
                <?php if (!empty($lab_test['technician_name'])) { ?>
                    <?= htmlspecialchars($lab_test['technician_name']) ?>
                <?php } else { ?>
                    <span style="color:var(--page-text-muted);">Not assigned</span>
                <?php } ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Branch</span>
            <span class="value"><?= htmlspecialchars($lab_test['branch_name'] ?? 'N/A') ?></span>
        </div>
        <div class="info-item">
            <span class="label">Status</span>
            <span class="value">
                <span class="badge-custom badge-<?= getStatusBadge($lab_test['status'] ?? 'pending') ?>">
                    <i class="fas <?= getStatusIcon($lab_test['status'] ?? 'pending') ?>"></i>
                    <?= ucfirst($lab_test['status'] ?? 'Pending') ?>
                </span>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Price</span>
            <span class="value">TSh <?= number_format($lab_test['test_price'] ?? 0, 0) ?></span>
        </div>
        <div class="info-item">
            <span class="label">Created</span>
            <span class="value"><?= date('M d, Y h:i A', strtotime($lab_test['created_at'] ?? 'now')) ?></span>
        </div>
        <?php if (!empty($lab_test['completed_at'])) { ?>
        <div class="info-item">
            <span class="label">Completed</span>
            <span class="value" style="color:#059669;"><?= date('M d, Y h:i A', strtotime($lab_test['completed_at'])) ?></span>
        </div>
        <?php } ?>
    </div>

    <!-- REFERENCE RANGE -->
    <?php if (!empty($lab_test['reference_range'])) { ?>
    <div class="result-card-custom">
        <div class="result-label">
            <i class="fas fa-chart-bar"></i> Reference Range
        </div>
        <div class="result-value"><?= htmlspecialchars($lab_test['reference_range']) ?></div>
    </div>
    <?php } ?>

    <!-- RESULTS -->
    <div class="result-card-custom">
        <div class="result-label">
            <i class="fas fa-file-medical-alt"></i> Results
        </div>
        <div class="result-value">
            <?php if (!empty($lab_test['formatted_result'])) { ?>
                <?= $lab_test['formatted_result'] ?>
            <?php } elseif (!empty($lab_test['results'])) { ?>
                <?= nl2br(htmlspecialchars($lab_test['results'])) ?>
            <?php } else { ?>
                <span style="color:var(--page-text-muted);font-style:italic;">No results available yet</span>
            <?php } ?>
        </div>
    </div>

    <!-- INTERPRETATION -->
    <?php if (!empty($lab_test['interpretation'])) { ?>
    <div class="result-card-custom interpretation">
        <div class="result-label">
            <i class="fas fa-stethoscope"></i> Interpretation
        </div>
        <div class="result-value"><?= nl2br(htmlspecialchars($lab_test['interpretation'])) ?></div>
    </div>
    <?php } ?>

    <!-- NOTES -->
    <?php if (!empty($lab_test['notes'])) { ?>
    <div class="result-card-custom">
        <div class="result-label">
            <i class="fas fa-sticky-note"></i> Notes
        </div>
        <div class="result-value" style="font-style:italic;color:var(--page-text-secondary);">
            <?= nl2br(htmlspecialchars($lab_test['notes'])) ?>
        </div>
    </div>
    <?php } ?>

    <!-- CLINICAL INFORMATION -->
    <?php if (!empty($lab_test['symptoms']) || !empty($lab_test['complaint']) || !empty($lab_test['diagnosis']) || !empty($lab_test['treatment'])) { ?>
    <div class="result-card-custom">
        <div class="result-label">
            <i class="fas fa-notes-medical"></i> Clinical Information
        </div>
        <div class="clinical-grid" style="margin-top:12px;">
            <?php if (!empty($lab_test['symptoms'])) { ?>
            <div>
                <div class="result-label" style="font-size:0.7rem;"><i class="fas fa-exclamation-triangle"></i> Symptoms</div>
                <div class="result-value" style="font-size:0.85rem;"><?= htmlspecialchars($lab_test['symptoms']) ?></div>
            </div>
            <?php } ?>
            <?php if (!empty($lab_test['complaint'])) { ?>
            <div>
                <div class="result-label" style="font-size:0.7rem;"><i class="fas fa-comment-medical"></i> Complaint</div>
                <div class="result-value" style="font-size:0.85rem;"><?= htmlspecialchars($lab_test['complaint']) ?></div>
            </div>
            <?php } ?>
            <?php if (!empty($lab_test['diagnosis'])) { ?>
            <div>
                <div class="result-label" style="font-size:0.7rem;"><i class="fas fa-stethoscope"></i> Diagnosis</div>
                <div class="result-value" style="font-size:0.85rem;"><?= htmlspecialchars($lab_test['diagnosis']) ?></div>
            </div>
            <?php } ?>
            <?php if (!empty($lab_test['treatment'])) { ?>
            <div>
                <div class="result-label" style="font-size:0.7rem;"><i class="fas fa-prescription"></i> Treatment</div>
                <div class="result-value" style="font-size:0.85rem;"><?= htmlspecialchars($lab_test['treatment']) ?></div>
            </div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

    <!-- ACTIONS -->
    <div class="actions-card">
        <div class="actions-title">
            <i class="fas fa-bolt"></i> Actions
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:12px;">
            <a href="add_lab_results.php?id=<?= $lab_test_id ?>&branch=<?= urlencode($selected_branch_id) ?>" 
               class="btn-add-results-custom">
                <i class="fas fa-plus-circle"></i>
                <div>
                    Add Results
                    <span class="sub-text">Enter lab test results</span>
                </div>
            </a>
            
            <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-custom btn-outline-custom">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // DARK MODE BACKGROUND ENFORCEMENT
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

    console.log('%c🧪 Braick - View Lab Result', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🌙 FULL DARK MODE inafanya kazi', 'font-size:13px; color:#7C3AED;');
    console.log('%c🔬 Test: <?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?> (ID: <?= $lab_test_id ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Only "Add Results" button — action buttons removed', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>