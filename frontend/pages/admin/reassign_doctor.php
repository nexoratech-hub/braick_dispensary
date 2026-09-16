<?php
// ================================================================
// FILE: frontend/pages/admin/reassign_doctor.php
// ADMIN - REMOVE DOCTOR FROM PATIENT
// BRAICK DISPENSARY - USING EXISTING DB TABLES
// ✅ Uses SHARED admin_header.php & admin_sidebar.php
// ✅ Page-specific CSS only (no duplicates)
// ✅ Dark mode via header toggle
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

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

// GET PARAMETERS
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

if ($patient_id <= 0) {
    header('Location: patients.php?branch=' . $branch_id . '&error=invalid_patient');
    exit;
}

// FETCH PATIENT DETAILS
$stmt = $db->prepare("
    SELECT 
        p.*,
        u.full_name as current_doctor_name,
        u.id as current_doctor_id,
        u.specialty as current_doctor_specialty,
        u.is_online as current_doctor_online,
        v.id as current_visit_id,
        v.visit_number,
        v.status as visit_status,
        v.visit_date,
        v.created_at as visit_created_at
    FROM patients p
    LEFT JOIN users u ON p.assigned_doctor_id = u.id
    LEFT JOIN visits v ON v.patient_id = p.id AND v.status NOT IN ('completed', 'cancelled')
    WHERE p.id = ?
    ORDER BY v.created_at DESC
    LIMIT 1
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: patients.php?branch=' . $branch_id . '&error=patient_not_found');
    exit;
}

// GET BRANCH DETAILS
$branch = [];
if ($branch_id > 0) {
    $stmt = $db->prepare("SELECT id, name, location FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$branch && $patient['branch_id'] > 0) {
    $stmt = $db->prepare("SELECT id, name, location FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$patient['branch_id']]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch) {
        $branch_id = $branch['id'];
    }
}

if (!$branch) {
    $stmt = $db->prepare("SELECT id, name, location FROM branches WHERE id = 1 AND status = 'active'");
    $stmt->execute();
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    $branch_id = 1;
}

$has_doctor = !empty($patient['current_doctor_id']);

// UNREAD NOTIFICATIONS
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

// HANDLE REMOVE DOCTOR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'remove_doctor') {
        try {
            $db->beginTransaction();
            
            $removed_doctor_name = $patient['current_doctor_name'] ?? 'Unknown Doctor';
            $removed_doctor_id = $patient['current_doctor_id'] ?? null;
            
            // Update patient
            $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$patient_id]);
            
            // Update active visit
            if (!empty($patient['current_visit_id'])) {
                $stmt = $db->prepare("
                    UPDATE visits 
                    SET doctor_id = NULL, status = 'pending', assigned_at = NULL, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$patient['current_visit_id']]);
            }
            
            // Log activity
            $details = "Doctor REMOVED from patient: " . htmlspecialchars($patient['full_name']) . 
                       " (ID: " . htmlspecialchars($patient['patient_id']) . ")" .
                       " - Removed doctor: " . htmlspecialchars($removed_doctor_name) .
                       " | Patient now has NO assigned doctor";
            
            $log_branch_id = !empty($branch_id) ? $branch_id : 1;
            
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                VALUES (?, ?, 'doctor_removed_from_patient', ?, NOW())
            ");
            $stmt->execute([$user_id, $log_branch_id, $details]);
            
            $db->commit();
            
            // Refresh patient data
            $stmt = $db->prepare("
                SELECT 
                    p.*,
                    u.full_name as current_doctor_name,
                    u.id as current_doctor_id,
                    u.specialty as current_doctor_specialty,
                    v.id as current_visit_id,
                    v.visit_number,
                    v.status as visit_status
                FROM patients p
                LEFT JOIN users u ON p.assigned_doctor_id = u.id
                LEFT JOIN visits v ON v.patient_id = p.id AND v.status NOT IN ('completed', 'cancelled')
                WHERE p.id = ?
                ORDER BY v.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            $has_doctor = false;
            
            $message = "✅ Doctor has been successfully REMOVED from patient. Patient is now UNASSIGNED.";
            $message_type = "success";
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = "❌ Error removing doctor: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// INCLUDE SHARED HEADER & SIDEBAR
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --rd-primary: #0B5ED7;
        --rd-primary-dark: #0A4CA8;
        --rd-primary-light: #3B82F6;
        --rd-primary-bg: #EFF6FF;
        --rd-primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        --rd-primary-gradient-hover: linear-gradient(135deg, #0A4CA8, #083C8A);
        --rd-success: #059669;
        --rd-success-dark: #047857;
        --rd-success-bg: #D1FAE5;
        --rd-danger: #DC2626;
        --rd-danger-dark: #B91C1C;
        --rd-danger-bg: #FEE2E2;
        --rd-warning: #D97706;
        --rd-warning-bg: #FEF3C7;
        --rd-gray-50: #F8FAFC;
        --rd-gray-100: #F1F5F9;
        --rd-gray-200: #E2E8F0;
        --rd-gray-300: #CBD5E1;
        --rd-gray-400: #94A3B8;
        --rd-gray-500: #64748B;
        --rd-gray-600: #475569;
        --rd-gray-700: #334155;
        --rd-gray-800: #1E293B;
        --rd-gray-900: #0F172A;
        --rd-bg-body: #F0F4F8;
        --rd-bg-card: #FFFFFF;
        --rd-text-primary: #1E293B;
        --rd-text-secondary: #64748B;
        --rd-border-color: #E2E8F0;
        --rd-radius: 12px;
        --rd-radius-lg: 18px;
        --rd-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --rd-shadow: 0 1px 3px rgba(0,0,0,0.08);
        --rd-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --rd-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        --rd-shadow-xl: 0 20px 40px rgba(0,0,0,0.12);
    }

    [data-theme="dark"] {
        --rd-bg-body: #0F172A;
        --rd-bg-card: #1E293B;
        --rd-text-primary: #F1F5F9;
        --rd-text-secondary: #94A3B8;
        --rd-border-color: #334155;
        --rd-primary: #3B82F6;
        --rd-primary-dark: #2563EB;
        --rd-primary-light: #60A5FA;
        --rd-primary-bg: #1E3A5F;
        --rd-shadow: 0 1px 3px rgba(0,0,0,0.3);
        --rd-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --rd-shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
        --rd-shadow-xl: 0 20px 40px rgba(0,0,0,0.5);
    }

    /* ================================================================
       DARK MODE - PAGE YOTE
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
    .page-header-rd {
        background: var(--rd-primary-gradient);
        border-radius: var(--rd-radius-lg);
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

    .page-header-rd::before {
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

    .page-header-rd::after {
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

    .page-header-rd .page-title-rd {
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

    .page-header-rd .page-title-rd i {
        font-size: 2rem;
        opacity: 0.9;
    }

    .page-header-rd .page-subtitle-rd {
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

    .page-header-rd .page-subtitle-rd strong {
        color: white;
        font-weight: 600;
    }

    .page-header-rd .role-badge-display-rd {
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

    .page-header-rd .header-badge-rd {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 500;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .btn-outline-light-rd {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: var(--rd-radius);
        font-weight: 500;
        font-size: 0.82rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
    }

    .btn-outline-light-rd:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       CARDS
       ================================================================ */
    .card-rd {
        background: var(--rd-bg-card);
        border-radius: var(--rd-radius-lg);
        border: 2px solid var(--rd-border-color);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--rd-shadow-sm);
        margin-bottom: 24px;
    }

    .card-rd:hover {
        box-shadow: var(--rd-shadow-md);
    }

    .card-header-rd {
        padding: 16px 24px;
        background: var(--rd-bg-body);
        border-bottom: 2px solid var(--rd-border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    [data-theme="dark"] .card-header-rd {
        background: #0F172A;
    }

    .card-title-rd {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--rd-text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .card-title-rd i {
        color: var(--rd-primary);
    }

    .card-body-rd {
        padding: 20px 24px;
    }

    /* ================================================================
       PATIENT INFO GRID
       ================================================================ */
    .patient-info-grid-rd {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
    }

    .info-item-rd {
        padding: 8px 0;
        border-bottom: 1px solid var(--rd-border-color);
    }

    .info-label-rd {
        font-size: 0.7rem;
        color: var(--rd-text-secondary);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .info-value-rd {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--rd-text-primary);
    }

    /* ================================================================
       DOCTOR STATUS
       ================================================================ */
    .doctor-status-rd {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-radius: var(--rd-radius);
        margin-bottom: 16px;
    }

    .doctor-status-rd.has-doctor {
        background: var(--rd-primary-bg);
        border: 2px solid var(--rd-primary);
    }

    .doctor-status-rd.no-doctor {
        background: var(--rd-danger-bg);
        border: 2px solid var(--rd-danger);
    }

    .doctor-status-rd .status-icon-rd {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .doctor-status-rd.has-doctor .status-icon-rd {
        background: var(--rd-primary);
        color: white;
    }

    .doctor-status-rd.no-doctor .status-icon-rd {
        background: var(--rd-danger);
        color: white;
    }

    .doctor-status-rd .status-text-rd {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--rd-text-primary);
    }

    .doctor-status-rd .status-sub-rd {
        font-size: 0.75rem;
        color: var(--rd-text-secondary);
        margin-top: 2px;
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge-rd {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        color: white;
    }

    .badge-success-rd { background: #059669; }
    .badge-danger-rd { background: #DC2626; }
    .badge-warning-rd { background: #D97706; }
    .badge-secondary-rd { background: #64748B; }
    .badge-info-rd { background: var(--rd-primary); }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-rd {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: var(--rd-radius);
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
    }

    .btn-rd:hover {
        transform: translateY(-2px);
        box-shadow: var(--rd-shadow-md);
    }

    .btn-danger-rd {
        background: var(--rd-danger);
        color: white;
    }

    .btn-danger-rd:hover {
        background: var(--rd-danger-dark);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        color: white;
    }

    .btn-outline-rd {
        background: transparent;
        color: var(--rd-text-secondary);
        border: 2px solid var(--rd-border-color);
    }

    .btn-outline-rd:hover {
        background: var(--rd-bg-body);
        border-color: var(--rd-primary);
        color: var(--rd-primary);
    }

    .btn-primary-rd {
        background: var(--rd-primary-gradient);
        color: white;
    }

    .btn-primary-rd:hover {
        background: var(--rd-primary-gradient-hover);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        color: white;
    }

    .btn-block-rd {
        width: 100%;
        justify-content: center;
    }

    .btn-lg-rd {
        padding: 14px 28px;
        font-size: 1rem;
    }

    /* ================================================================
       ALERT / MESSAGE
       ================================================================ */
    .alert-rd {
        padding: 14px 20px;
        border-radius: var(--rd-radius);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-success-rd {
        background: var(--rd-success-bg);
        border: 2px solid var(--rd-success);
        color: var(--rd-success-dark);
    }

    .alert-danger-rd {
        background: var(--rd-danger-bg);
        border: 2px solid var(--rd-danger);
        color: var(--rd-danger-dark);
    }

    .alert-rd i {
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    [data-theme="dark"] .alert-success-rd {
        background: #1A3A2A;
        color: #34D399;
    }

    [data-theme="dark"] .alert-danger-rd {
        background: #3A1A1A;
        color: #F87171;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-rd {
        padding: 14px 0;
        border-top: 2px solid var(--rd-border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--rd-text-secondary);
    }

    .footer-rd .footer-brand-rd {
        color: var(--rd-primary);
        font-weight: 600;
    }

    /* ================================================================
       CONFIRMATION MODAL
       ================================================================ */
    .modal-overlay-rd {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal-overlay-rd.active {
        display: flex;
    }

    .modal-box-rd {
        background: var(--rd-bg-card);
        border-radius: var(--rd-radius-lg);
        max-width: 500px;
        width: 90%;
        padding: 32px;
        box-shadow: var(--rd-shadow-xl);
        animation: fadeInUp 0.3s ease;
        border: 2px solid var(--rd-border-color);
    }

    .modal-box-rd .modal-icon-rd {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: var(--rd-danger-bg);
        color: var(--rd-danger);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin: 0 auto 16px;
    }

    .modal-box-rd h3 {
        text-align: center;
        font-size: 1.2rem;
        margin-bottom: 8px;
        color: var(--rd-text-primary);
    }

    .modal-box-rd p {
        text-align: center;
        color: var(--rd-text-secondary);
        font-size: 0.9rem;
        margin-bottom: 20px;
        line-height: 1.5;
    }

    .modal-box-rd .modal-actions-rd {
        display: flex;
        gap: 12px;
        justify-content: center;
        flex-wrap: wrap;
    }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-rd {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .page-header-rd { padding: 16px 18px; }
        .page-header-rd .page-title-rd { font-size: 1.3rem; }
        .patient-info-grid-rd { grid-template-columns: 1fr; }
        .card-body-rd { padding: 14px 16px; }
    }

    @media (max-width: 480px) {
        .page-header-rd { flex-direction: column; align-items: flex-start !important; }
        .modal-box-rd { padding: 20px; }
        .modal-box-rd .modal-actions-rd { flex-direction: column; }
        .modal-box-rd .modal-actions-rd .btn-rd { width: 100%; justify-content: center; }
    }

    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .btn-rd, .modal-overlay-rd, .btn-outline-light-rd { display: none !important; }
        .card-rd { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
        .page-header-rd {
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
    <div class="page-header-rd animate-fade-in-up-rd">
        <div>
            <h1 class="page-title-rd">
                <i class="fas fa-user-md"></i>
                Remove Assigned Doctor
                <span class="role-badge-display-rd">ADMIN</span>
            </h1>
            <p class="page-subtitle-rd">
                <i class="fas fa-user"></i>
                Patient: <strong><?= htmlspecialchars($patient['full_name']) ?></strong>
                <span class="header-badge-rd">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id']) ?>
                </span>
                <?php if (!empty($branch['name'])): ?>
                    <span class="header-badge-rd">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch['name']) ?>
                    </span>
                <?php endif; ?>
                <span class="header-badge-rd">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="patients.php?branch=<?= $branch_id ?>" class="btn-outline-light-rd">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION CARD -->
    <!-- ================================================================ -->
    <div class="card-rd animate-fade-in-up-rd" style="animation-delay:0.05s;">
        <div class="card-header-rd">
            <h3 class="card-title-rd">
                <i class="fas fa-user"></i>
                Patient Information
            </h3>
        </div>
        <div class="card-body-rd">
            <div class="patient-info-grid-rd">
                <div class="info-item-rd">
                    <div class="info-label-rd">Patient ID</div>
                    <div class="info-value-rd"><?= htmlspecialchars($patient['patient_id']) ?></div>
                </div>
                <div class="info-item-rd">
                    <div class="info-label-rd">Full Name</div>
                    <div class="info-value-rd"><?= htmlspecialchars($patient['full_name']) ?></div>
                </div>
                <div class="info-item-rd">
                    <div class="info-label-rd">Gender</div>
                    <div class="info-value-rd"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item-rd">
                    <div class="info-label-rd">Phone</div>
                    <div class="info-value-rd"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item-rd">
                    <div class="info-label-rd">Branch</div>
                    <div class="info-value-rd"><?= htmlspecialchars($branch['name'] ?? 'N/A') ?></div>
                </div>
                <?php if (!empty($patient['current_visit_id'])): ?>
                    <div class="info-item-rd">
                        <div class="info-label-rd">Active Visit</div>
                        <div class="info-value-rd">
                            <?= htmlspecialchars($patient['visit_number'] ?? 'N/A') ?>
                            <span class="badge-rd badge-info-rd">
                                <?= ucfirst($patient['visit_status'] ?? 'Pending') ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- CURRENT DOCTOR STATUS -->
    <!-- ================================================================ -->
    <div class="card-rd animate-fade-in-up-rd" style="animation-delay:0.1s;">
        <div class="card-header-rd">
            <h3 class="card-title-rd">
                <i class="fas fa-stethoscope"></i>
                Current Assigned Doctor
            </h3>
        </div>
        <div class="card-body-rd">
            <?php if ($has_doctor): ?>
                <div class="doctor-status-rd has-doctor">
                    <div class="status-icon-rd">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div>
                        <div class="status-text-rd"><?= htmlspecialchars($patient['current_doctor_name']) ?></div>
                        <div class="status-sub-rd">
                            <?= htmlspecialchars($patient['current_doctor_specialty'] ?? 'General Medicine') ?>
                            <?php if ($patient['current_doctor_online'] ?? false): ?>
                                <span class="badge-rd badge-success-rd">
                                    <i class="fas fa-circle" style="font-size:6px;"></i> Online
                                </span>
                            <?php else: ?>
                                <span class="badge-rd badge-secondary-rd">
                                    <i class="fas fa-circle" style="font-size:6px;"></i> Offline
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($patient['current_doctor_id'])): ?>
                                <span class="badge-rd badge-info-rd">
                                    ID: <?= $patient['current_doctor_id'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Remove Doctor Button -->
                <button type="button" class="btn-rd btn-danger-rd btn-block-rd btn-lg-rd" onclick="openConfirmModal()">
                    <i class="fas fa-user-minus"></i>
                    REMOVE DOCTOR FROM PATIENT
                </button>
                <p style="font-size:0.8rem;color:var(--rd-text-secondary);margin-top:8px;text-align:center;">
                    <i class="fas fa-info-circle"></i> 
                    Patient will have <strong>NO assigned doctor</strong> after removal.
                </p>
                
            <?php else: ?>
                <div class="doctor-status-rd no-doctor">
                    <div class="status-icon-rd">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div>
                        <div class="status-text-rd" style="color: var(--rd-danger);">No Doctor Assigned</div>
                        <div class="status-sub-rd">This patient currently has no assigned doctor</div>
                    </div>
                </div>
                <div style="text-align:center;padding:8px 0;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                    <a href="assign_doctor.php?patient_id=<?= $patient_id ?>&branch_id=<?= $branch_id ?>" class="btn-rd btn-primary-rd">
                        <i class="fas fa-user-plus"></i> Assign Doctor
                    </a>
                    <a href="patients.php?branch=<?= $branch_id ?>" class="btn-rd btn-outline-rd">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGES -->
    <!-- ================================================================ -->
    <?php if (!empty($message)): ?>
        <div class="alert-rd alert-<?= $message_type === 'success' ? 'success-rd' : 'danger-rd' ?> animate-fade-in-up-rd" style="animation-delay:0.15s;">
            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-rd">
        <p>
            <span class="footer-brand-rd">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Remove Assigned Doctor
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- CONFIRMATION MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay-rd" id="confirmModal">
    <div class="modal-box-rd">
        <div class="modal-icon-rd">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3>Confirm Remove Doctor</h3>
        <p>
            Are you sure you want to remove 
            <strong><?= htmlspecialchars($patient['current_doctor_name'] ?? 'the doctor') ?></strong> 
            from <strong><?= htmlspecialchars($patient['full_name']) ?></strong>?
            <br><br>
            <span style="color: var(--rd-danger); font-weight: 600;">
                ⚠️ This action will leave the patient with NO assigned doctor.
            </span>
        </p>
        <div class="modal-actions-rd">
            <button type="button" class="btn-rd btn-outline-rd" onclick="closeConfirmModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="remove_doctor">
                <button type="submit" class="btn-rd btn-danger-rd">
                    <i class="fas fa-user-minus"></i> Yes, Remove Doctor
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // CONFIRMATION MODAL
    // ================================================================
    function openConfirmModal() {
        document.getElementById('confirmModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    
    document.getElementById('confirmModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeConfirmModal();
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeConfirmModal();
        }
    });

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

    console.log('%c👨‍⚕️ Remove Doctor from Patient - Braick Dispensary', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name']) ?> (ID: <?= $patient_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🩺 Current Doctor: <?= $has_doctor ? htmlspecialchars($patient['current_doctor_name']) : 'None' ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c⚠️ Action: Remove doctor - patient will be unassigned', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ No duplicate CSS/JS', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>