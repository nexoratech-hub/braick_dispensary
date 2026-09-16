<?php
// ================================================================
// FILE: frontend/pages/admin/view_lab_test.php
// ADMIN - VIEW LAB TEST DETAILS
// BRAICK DISPENSARY - BLUE THEME
// ✅ Uses SHARED admin_header.php & admin_sidebar.php
// ✅ Page-specific CSS only (no duplicates)
// ✅ Dark mode via header toggle
// ✅ Modal CSS included
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

// Verify user
$stmt = $db->prepare("SELECT id, full_name, role, status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] !== 'active') {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// GET UNREAD NOTIFICATIONS
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// GET PARAMETERS
$lab_test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($lab_test_id <= 0) {
    header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// FETCH LAB TEST DETAILS
try {
    $stmt = $db->prepare("
        SELECT 
            lt.*, lt.id as lab_test_id,
            p.id as patient_id, p.full_name as patient_name, p.patient_id as patient_code,
            p.phone as patient_phone, p.gender as patient_gender, p.date_of_birth,
            p.blood_group, p.allergies, p.address,
            u.full_name as doctor_name, u.specialty as doctor_specialty,
            u2.full_name as technician_name, u2.profile_pic as technician_profile_pic,
            v.visit_number, v.visit_type, v.id as visit_id,
            v.symptoms, v.complaint, v.diagnosis, v.treatment,
            b.name as branch_name,
            ltc.test_code, ltc.category as test_category, ltc.reference_range as catalog_reference_range
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

// HANDLE DELETE
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $stmt = $db->prepare("SELECT id, test_name FROM lab_tests WHERE id = ?");
        $stmt->execute([$lab_test_id]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($test) {
            $stmt = $db->prepare("DELETE FROM lab_tests WHERE id = ?");
            $stmt->execute([$lab_test_id]);
            
            $details = "Deleted lab test: " . htmlspecialchars($test['test_name']) . " (ID: #$lab_test_id)";
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                VALUES (?, ?, 'lab_test_deleted', ?, NOW())
            ");
            $stmt->execute([$user_id, $lab_test['branch_id'] ?? 1, $details]);
            
            $_SESSION['toast'] = [
                'message' => '✅ Lab test deleted successfully!',
                'type' => 'success'
            ];
            header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id));
            exit;
        }
    } catch (Exception $e) {
        $message = "❌ Error deleting lab test: " . $e->getMessage();
        $message_type = "danger";
    }
}

// HELPER FUNCTIONS
function getStatusBadge($status) {
    $classes = ['pending' => 'warning', 'in_progress' => 'info', 'completed' => 'success', 'cancelled' => 'danger'];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = ['pending' => 'fa-clock', 'in_progress' => 'fa-spinner fa-spin', 'completed' => 'fa-check-circle', 'cancelled' => 'fa-times-circle'];
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
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --vlt-primary: #0B5ED7;
        --vlt-primary-dark: #0A4CA8;
        --vlt-primary-light: #3B82F6;
        --vlt-primary-bg: #EFF6FF;
        --vlt-primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        --vlt-primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #073B8A);
        --vlt-success: #059669;
        --vlt-success-bg: #D1FAE5;
        --vlt-danger: #DC2626;
        --vlt-danger-bg: #FEE2E2;
        --vlt-warning: #D97706;
        --vlt-warning-bg: #FEF3C7;
        --vlt-purple: #7C3AED;
        --vlt-purple-bg: #EDE9FE;
        --vlt-teal: #0D9488;
        --vlt-teal-bg: #ECFDF5;
        --vlt-gray-50: #F8FAFC;
        --vlt-gray-100: #F1F5F9;
        --vlt-gray-200: #E2E8F0;
        --vlt-gray-300: #CBD5E1;
        --vlt-gray-400: #94A3B8;
        --vlt-gray-500: #64748B;
        --vlt-gray-600: #475569;
        --vlt-gray-700: #334155;
        --vlt-gray-800: #1E293B;
        --vlt-gray-900: #0F172A;
        --vlt-bg-body: #F0F4F8;
        --vlt-bg-card: #FFFFFF;
        --vlt-text-primary: #1E293B;
        --vlt-text-secondary: #64748B;
        --vlt-border-color: #E2E8F0;
        --vlt-radius: 12px;
        --vlt-radius-lg: 18px;
        --vlt-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --vlt-shadow: 0 1px 3px rgba(0,0,0,0.08);
        --vlt-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --vlt-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    }

    [data-theme="dark"] {
        --vlt-bg-body: #0F172A;
        --vlt-bg-card: #1E293B;
        --vlt-text-primary: #F1F5F9;
        --vlt-text-secondary: #94A3B8;
        --vlt-border-color: #334155;
        --vlt-primary: #3B82F6;
        --vlt-primary-dark: #2563EB;
        --vlt-primary-light: #60A5FA;
        --vlt-primary-bg: #1E3A5F;
        --vlt-primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
        --vlt-primary-gradient-strong: linear-gradient(135deg, #1D4ED8, #1E40AF);
        --vlt-shadow: 0 1px 3px rgba(0,0,0,0.3);
        --vlt-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --vlt-shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
    }

    /* ================================================================
       DARK MODE
       ================================================================ */
    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
        color: #F1F5F9;
    }

    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header-vlt {
        background: var(--vlt-primary-gradient-strong);
        border-radius: var(--vlt-radius-lg);
        padding: 28px 36px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-vlt::before {
        content: '';
        position: absolute;
        top: -60%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-vlt::after {
        content: '';
        position: absolute;
        bottom: -40%;
        left: -5%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.03);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-vlt .page-title-vlt {
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

    .page-header-vlt .page-title-vlt i { font-size: 2rem; opacity: 0.9; }

    .page-header-vlt .page-subtitle-vlt {
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

    .page-header-vlt .page-subtitle-vlt strong { color: white; font-weight: 600; }

    .page-header-vlt .role-badge-display-vlt {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        backdrop-filter: blur(4px);
    }

    .page-header-vlt .header-badge-vlt {
        background: rgba(255,255,255,0.12);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .page-header-vlt .btn-outline-light-vlt {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: var(--vlt-radius);
        font-weight: 500;
        font-size: 0.82rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
        transition: all 0.3s;
    }

    .page-header-vlt .btn-outline-light-vlt:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-vlt {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: var(--vlt-radius);
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        box-shadow: var(--vlt-shadow-sm);
        font-family: inherit;
    }

    .btn-vlt:hover {
        transform: translateY(-3px);
        box-shadow: var(--vlt-shadow-lg);
    }

    .btn-primary-vlt {
        background: var(--vlt-primary-gradient);
        color: white;
    }
    .btn-primary-vlt:hover {
        background: var(--vlt-primary-gradient-strong);
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.35);
        color: white;
    }

    .btn-success-vlt {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
    }
    .btn-success-vlt:hover {
        background: linear-gradient(135deg, #047857, #065F46);
        box-shadow: 0 4px 16px rgba(5, 150, 105, 0.35);
        color: white;
    }

    .btn-danger-vlt {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
    }
    .btn-danger-vlt:hover {
        background: linear-gradient(135deg, #B91C1C, #991B1B);
        box-shadow: 0 4px 16px rgba(220, 38, 38, 0.35);
        color: white;
    }

    .btn-warning-vlt {
        background: linear-gradient(135deg, #D97706, #B45309);
        color: white;
    }
    .btn-warning-vlt:hover {
        background: linear-gradient(135deg, #B45309, #92400E);
        box-shadow: 0 4px 16px rgba(217, 119, 6, 0.35);
        color: white;
    }

    .btn-outline-vlt {
        background: transparent;
        color: var(--vlt-text-primary);
        border: 2px solid var(--vlt-border-color);
    }
    .btn-outline-vlt:hover {
        background: var(--vlt-bg-body);
        border-color: var(--vlt-primary);
        color: var(--vlt-primary);
    }

    .btn-sm-vlt { padding: 5px 12px; font-size: 0.7rem; border-radius: 6px; }

    /* ================================================================
       INFO BOX
       ================================================================ */
    .info-box-vlt {
        background: var(--vlt-primary-bg);
        border-radius: var(--vlt-radius);
        padding: 16px 20px;
        border-left: 4px solid var(--vlt-primary);
        margin-bottom: 20px;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }

    .info-box-vlt .info-item {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        font-size: 0.85rem;
        flex-wrap: wrap;
        gap: 4px;
    }

    .info-box-vlt .info-item .label {
        color: var(--vlt-text-secondary);
        font-weight: 500;
    }

    .info-box-vlt .info-item .value {
        font-weight: 600;
        color: var(--vlt-text-primary);
    }

    .info-box-vlt .info-item .value a {
        color: var(--vlt-primary);
        text-decoration: none;
    }

    .info-box-vlt .info-item .value a:hover {
        text-decoration: underline;
    }

    .info-box-vlt .info-item .value .text-gray-400 {
        color: var(--vlt-text-secondary) !important;
        font-weight: 400;
    }

    /* ================================================================
       RESULT CARD
       ================================================================ */
    .result-card-vlt {
        background: var(--vlt-bg-card);
        border-radius: var(--vlt-radius-lg);
        padding: 24px 28px;
        border: 2px solid var(--vlt-border-color);
        transition: all 0.3s ease;
        box-shadow: var(--vlt-shadow-sm);
        margin-bottom: 24px;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }

    .result-card-vlt:hover {
        border-color: var(--vlt-primary);
        box-shadow: var(--vlt-shadow-md);
    }

    .result-card-vlt .result-label-vlt {
        font-size: 0.7rem;
        color: var(--vlt-text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 6px;
    }

    .result-card-vlt .result-value-vlt {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--vlt-text-primary);
        white-space: pre-wrap;
        line-height: 1.6;
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge-vlt {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        color: white;
        letter-spacing: 0.02em;
    }

    .badge-success-vlt { background: #059669; }
    .badge-danger-vlt { background: #DC2626; }
    .badge-warning-vlt { background: #D97706; color: #1E293B; }
    .badge-info-vlt { background: #0B5ED7; }
    .badge-secondary-vlt { background: #64748B; }

    [data-theme="dark"] .badge-warning-vlt { color: #1E293B; }

    /* ================================================================
       MODAL
       ================================================================ */
    .modal-vlt {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-vlt.show {
        display: flex;
    }

    .modal-overlay-vlt {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.7);
        backdrop-filter: blur(4px);
    }

    .modal-content-vlt {
        position: relative;
        background: var(--vlt-bg-card);
        border-radius: var(--vlt-radius-lg);
        padding: 24px 28px;
        max-width: 500px;
        width: 100%;
        box-shadow: var(--vlt-shadow-lg);
        border: 2px solid var(--vlt-border-color);
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from { opacity: 0; transform: translateY(30px) scale(0.95); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .modal-header-vlt {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--vlt-border-color);
        margin-bottom: 16px;
    }

    .modal-header-vlt h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--vlt-danger);
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    .modal-close-vlt {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--vlt-text-secondary);
        line-height: 1;
        padding: 0 4px;
    }

    .modal-close-vlt:hover { color: var(--vlt-danger); }

    .modal-body-vlt {
        color: var(--vlt-text-primary);
        font-size: 0.9rem;
        line-height: 1.6;
    }

    .modal-footer-vlt {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        padding-top: 16px;
        margin-top: 16px;
        border-top: 2px solid var(--vlt-border-color);
        flex-wrap: wrap;
    }

    /* ================================================================
       TOAST
       ================================================================ */
    .toast-custom-vlt {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 14px 20px;
        border-radius: 12px;
        z-index: 9999;
        max-width: 400px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    }
    .toast-custom-vlt.show { transform: translateY(0); opacity: 1; }
    .toast-custom-vlt.success { background: var(--vlt-success); }
    .toast-custom-vlt.error { background: var(--vlt-danger); }
    .toast-custom-vlt.info { background: var(--vlt-primary); }
    .toast-custom-vlt.warning { background: var(--vlt-warning); }

    /* ================================================================
       GRID UTILITIES
       ================================================================ */
    .grid-2-vlt {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .actions-flex-vlt {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-vlt {
        padding: 14px 0;
        border-top: 2px solid var(--vlt-border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--vlt-text-secondary);
    }

    .footer-vlt .footer-brand-vlt {
        color: var(--vlt-primary);
        font-weight: 700;
    }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-vlt {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .page-header-vlt { padding: 16px 18px; }
        .page-header-vlt .page-title-vlt { font-size: 1.3rem; }
        .result-card-vlt { padding: 16px; }
        .grid-2-vlt { grid-template-columns: 1fr; }
        .actions-flex-vlt .btn-vlt { width: 100%; justify-content: center; }
        .info-box-vlt .info-item { flex-direction: column; }
    }

    @media (max-width: 480px) {
        .page-header-vlt { flex-direction: column; align-items: flex-start !important; }
        .result-card-vlt { padding: 14px; }
    }

    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .btn-vlt, .btn-outline-light-vlt, .modal-vlt, .toast-custom-vlt { display: none !important; }
        .result-card-vlt { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
        .info-box-vlt { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
        .page-header-vlt {
            background: #0B5ED7 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header-vlt animate-fade-in-up-vlt">
        <div>
            <h1 class="page-title-vlt">
                <i class="fas fa-flask"></i>
                Lab Test Details
                <span class="role-badge-display-vlt">ADMIN</span>
            </h1>
            <p class="page-subtitle-vlt">
                <i class="fas fa-vial"></i>
                <strong><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></strong>
                <span class="header-badge-vlt">
                    <i class="fas <?= getStatusIcon($lab_test['status'] ?? 'pending') ?>"></i>
                    <?= ucfirst($lab_test['status'] ?? 'Pending') ?>
                </span>
                <span class="header-badge-vlt" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge-vlt" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-money-bill-wave"></i>
                    TSh <?= number_format($lab_test['test_price'] ?? 0, 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="edit_lab_test.php?id=<?= $lab_test_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light-vlt">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light-vlt">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="result-card-vlt" style="border-color:var(--vlt-<?= $message_type === 'danger' ? 'danger' : 'success' ?>);background:var(--vlt-<?= $message_type === 'danger' ? 'danger' : 'success' ?>-bg);">
            <div class="result-value-vlt" style="color:var(--vlt-<?= $message_type === 'danger' ? 'danger' : 'success' ?>);">
                <?= $message ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- TEST INFORMATION -->
    <div class="info-box-vlt animate-fade-in-up-vlt" style="animation-delay:0.05s;">
        <div class="info-item">
            <span class="label">Test ID</span>
            <span class="value">#<?= $lab_test_id ?></span>
        </div>
        <div class="info-item">
            <span class="label">Test Name</span>
            <span class="value">
                <strong><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></strong>
                <?php if (!empty($lab_test['test_code'])): ?>
                    <span class="text-gray-400" style="font-size:0.8rem;">(<?= htmlspecialchars($lab_test['test_code']) ?>)</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Category</span>
            <span class="value"><?= htmlspecialchars($lab_test['test_category'] ?? 'N/A') ?></span>
        </div>
        <div class="info-item">
            <span class="label">Patient</span>
            <span class="value">
                <?php if (!empty($lab_test['patient_id']) && !empty($lab_test['patient_name'])): ?>
                    <a href="view_patient.php?id=<?= $lab_test['patient_id'] ?>&branch=<?= urlencode($selected_branch_id) ?>">
                        <?= htmlspecialchars($lab_test['patient_name']) ?>
                    </a>
                    <?php if (!empty($lab_test['patient_code'])): ?>
                        (<?= htmlspecialchars($lab_test['patient_code']) ?>)
                    <?php endif; ?>
                    <?php if ($age !== null): ?>
                        <span class="text-gray-400" style="font-size:0.8rem;">| <?= $age ?> yrs</span>
                    <?php endif; ?>
                <?php else: ?>
                    <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Visit</span>
            <span class="value">
                <?php if (!empty($lab_test['visit_number']) && !empty($lab_test['visit_id'])): ?>
                    <a href="view_visit.php?id=<?= $lab_test['visit_id'] ?>&branch=<?= urlencode($selected_branch_id) ?>">
                        <?= htmlspecialchars($lab_test['visit_number']) ?>
                    </a>
                <?php else: ?>
                    <?= htmlspecialchars($lab_test['visit_number'] ?? 'N/A') ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Doctor</span>
            <span class="value">
                <?php if (!empty($lab_test['doctor_name'])): ?>
                    Dr. <?= htmlspecialchars($lab_test['doctor_name']) ?>
                    <?php if (!empty($lab_test['doctor_specialty'])): ?>
                        <span class="text-gray-400" style="font-size:0.8rem;">(<?= htmlspecialchars($lab_test['doctor_specialty']) ?>)</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="text-gray-400">Not assigned</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Technician</span>
            <span class="value">
                <?php if (!empty($lab_test['technician_name'])): ?>
                    <?= htmlspecialchars($lab_test['technician_name']) ?>
                <?php else: ?>
                    <span class="text-gray-400">Not assigned</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Branch</span>
            <span class="value"><?= htmlspecialchars($lab_test['branch_name'] ?? 'N/A') ?></span>
        </div>
        <div class="info-item">
            <span class="label">Status</span>
            <span class="value">
                <span class="badge-vlt badge-<?= getStatusBadge($lab_test['status'] ?? 'pending') ?>-vlt">
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
        <?php if (!empty($lab_test['completed_at'])): ?>
        <div class="info-item">
            <span class="label">Completed</span>
            <span class="value" style="color:var(--vlt-success);"><?= date('M d, Y h:i A', strtotime($lab_test['completed_at'])) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- REFERENCE RANGE -->
    <?php if (!empty($lab_test['reference_range'])): ?>
    <div class="result-card-vlt animate-fade-in-up-vlt" style="animation-delay:0.1s;">
        <div class="result-label-vlt"><i class="fas fa-chart-bar"></i> Reference Range</div>
        <div class="result-value-vlt"><?= htmlspecialchars($lab_test['reference_range']) ?></div>
    </div>
    <?php endif; ?>

    <!-- RESULTS -->
    <div class="result-card-vlt animate-fade-in-up-vlt" style="animation-delay:0.15s;">
        <div class="result-label-vlt"><i class="fas fa-file-medical-alt"></i> Results</div>
        <div class="result-value-vlt">
            <?php if (!empty($lab_test['formatted_result'])): ?>
                <?= $lab_test['formatted_result'] ?>
            <?php elseif (!empty($lab_test['results'])): ?>
                <?= nl2br(htmlspecialchars($lab_test['results'])) ?>
            <?php else: ?>
                <span class="text-gray-400" style="color:var(--vlt-text-secondary);font-weight:400;">No results available yet</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- INTERPRETATION -->
    <?php if (!empty($lab_test['interpretation'])): ?>
    <div class="result-card-vlt animate-fade-in-up-vlt" style="animation-delay:0.2s;border-color:var(--vlt-primary);background:var(--vlt-primary-bg);">
        <div class="result-label-vlt" style="color:var(--vlt-primary);"><i class="fas fa-stethoscope"></i> Interpretation</div>
        <div class="result-value-vlt" style="color:var(--vlt-primary);"><?= nl2br(htmlspecialchars($lab_test['interpretation'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- NOTES -->
    <?php if (!empty($lab_test['notes'])): ?>
    <div class="result-card-vlt animate-fade-in-up-vlt" style="animation-delay:0.25s;">
        <div class="result-label-vlt"><i class="fas fa-sticky-note"></i> Notes</div>
        <div class="result-value-vlt" style="font-style:italic;color:var(--vlt-text-secondary);font-weight:400;"><?= nl2br(htmlspecialchars($lab_test['notes'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- CLINICAL INFORMATION -->
    <?php if (!empty($lab_test['symptoms']) || !empty($lab_test['complaint']) || !empty($lab_test['diagnosis']) || !empty($lab_test['treatment'])): ?>
    <div class="result-card-vlt animate-fade-in-up-vlt" style="animation-delay:0.3s;">
        <h3 class="result-label-vlt" style="font-size:0.9rem;font-weight:700;color:var(--vlt-primary);margin-bottom:16px;">
            <i class="fas fa-notes-medical"></i> Clinical Information
        </h3>
        <div class="grid-2-vlt">
            <?php if (!empty($lab_test['symptoms'])): ?>
            <div>
                <div class="result-label-vlt"><i class="fas fa-exclamation-triangle"></i> Symptoms</div>
                <div class="result-value-vlt" style="font-weight:500;font-size:0.85rem;"><?= htmlspecialchars($lab_test['symptoms']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($lab_test['complaint'])): ?>
            <div>
                <div class="result-label-vlt"><i class="fas fa-comment-medical"></i> Complaint</div>
                <div class="result-value-vlt" style="font-weight:500;font-size:0.85rem;"><?= htmlspecialchars($lab_test['complaint']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($lab_test['diagnosis'])): ?>
            <div>
                <div class="result-label-vlt"><i class="fas fa-stethoscope"></i> Diagnosis</div>
                <div class="result-value-vlt" style="font-weight:500;font-size:0.85rem;"><?= htmlspecialchars($lab_test['diagnosis']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($lab_test['treatment'])): ?>
            <div>
                <div class="result-label-vlt"><i class="fas fa-prescription"></i> Treatment</div>
                <div class="result-value-vlt" style="font-weight:500;font-size:0.85rem;"><?= htmlspecialchars($lab_test['treatment']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ACTION BUTTONS -->
    <div class="result-card-vlt animate-fade-in-up-vlt" style="animation-delay:0.35s;">
        <h3 class="result-label-vlt" style="font-size:0.9rem;font-weight:700;color:var(--vlt-primary);margin-bottom:16px;">
            <i class="fas fa-bolt"></i> Actions
        </h3>
        <div class="actions-flex-vlt">
            <a href="edit_lab_test.php?id=<?= $lab_test_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-vlt btn-warning-vlt">
                <i class="fas fa-edit"></i> Edit
            </a>
            
            <?php if (isset($lab_test['status']) && $lab_test['status'] !== 'completed' && $lab_test['status'] !== 'cancelled'): ?>
            <a href="edit_lab_test.php?action=complete&id=<?= $lab_test_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-vlt btn-success-vlt">
                <i class="fas fa-check-circle"></i> Mark Complete
            </a>
            <?php endif; ?>
            
            <button onclick="confirmDelete()" class="btn-vlt btn-danger-vlt">
                <i class="fas fa-trash"></i> Delete
            </button>
            
            <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-vlt btn-outline-vlt">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer-vlt">
        <p>
            <span class="footer-brand-vlt">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Lab Test Details - <?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- DELETE CONFIRMATION MODAL -->
<!-- ================================================================ -->
<div id="deleteModal" class="modal-vlt">
    <div class="modal-overlay-vlt" onclick="closeDeleteModal()"></div>
    <div class="modal-content-vlt">
        <div class="modal-header-vlt">
            <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
            <button onclick="closeDeleteModal()" class="modal-close-vlt">&times;</button>
        </div>
        <div class="modal-body-vlt">
            <p>Are you sure you want to delete this lab test?</p>
            <p style="margin-top:8px;">
                <i class="fas fa-info-circle"></i> 
                <strong><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></strong>
            </p>
            <p style="margin-top:8px;color:var(--vlt-danger);font-weight:600;">
                ⚠️ This action cannot be undone!
            </p>
        </div>
        <div class="modal-footer-vlt">
            <button onclick="closeDeleteModal()" class="btn-vlt btn-outline-vlt btn-sm-vlt">Cancel</button>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn-vlt btn-danger-vlt btn-sm-vlt">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom-vlt" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DELETE MODAL
    // ================================================================
    function confirmDelete() {
        var modal = document.getElementById('deleteModal');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeDeleteModal() {
        var modal = document.getElementById('deleteModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeleteModal();
        }
    });

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom-vlt ' + type;
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

    console.log('%c🧪 Braick Dispensary - View Lab Test', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔬 Test: <?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?> (ID: <?= $lab_test_id ?>)', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 Patient: <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Status: <?= ucfirst($lab_test['status'] ?? 'Pending') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ No duplicate CSS/JS', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>