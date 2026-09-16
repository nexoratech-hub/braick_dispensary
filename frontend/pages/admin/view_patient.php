<?php
// ================================================================
// FILE: frontend/pages/admin/view_patient.php
// VIEW PATIENT - COMPLETE PATIENT DETAILS WITH PDF
// WITH 7 VITAL SIGNS (INCLUDING OXYGEN SATURATION - SpO2)
// ✅ Uses SHARED admin_header.php & admin_sidebar.php
// ✅ Page-specific CSS only (no duplicates)
// ✅ Dark mode via header toggle
// BRAICK DISPENSARY - TUNAJARI AFYA YAKO
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'reception';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

$message = '';
$message_type = '';

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    header('Location: patients.php?error=invalid_patient');
    exit;
}

$patient = null;
$active_visit = null;
$visit_history = [];
$bills = [];
$bill_items = [];
$procedures = [];
$tools = [];
$latest_vitals = null;
$prescriptions = [];
$lab_tests = [];
$vital_signs = [];
$age = 'N/A';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

try {
    $db = Database::getInstance()->getConnection();
    
    // GET PATIENT DETAILS
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as created_by_name, b.name as branch_name,
               doc.full_name as assigned_doctor_name, doc.is_online as assigned_doctor_online
        FROM patients p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users doc ON p.assigned_doctor_id = doc.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$patient_id, $branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('Location: patients.php?error=patient_not_found');
        exit;
    }
    
    // GET ACTIVE VISIT
    $stmt = $db->prepare("
        SELECT v.*, u.full_name as doctor_name, u.is_online as doctor_online
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ? AND v.status IN ('pending', 'assigned', 'with_doctor', 'lab_test')
        ORDER BY v.created_at DESC LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $active_visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // GET VISIT HISTORY
    $stmt = $db->prepare("
        SELECT v.*, u.full_name as doctor_name, b.total_amount as bill_amount,
               b.status as bill_status, b.bill_number
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN bills b ON v.id = b.visit_id
        WHERE v.patient_id = ? 
        ORDER BY v.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $visit_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // GET BILLS
    $stmt = $db->prepare("
        SELECT b.*, v.visit_number, u.full_name as created_by_name
        FROM bills b
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.patient_id = ?
        ORDER BY b.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // GET BILL ITEMS
    $bill_items = [];
    foreach ($bills as $bill) {
        if (!empty($bill['id'])) {
            $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ? ORDER BY created_at DESC");
            $stmt->execute([$bill['id']]);
            $bill_items[$bill['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // GET PROCEDURES
    $stmt = $db->prepare("
        SELECT bi.*, b.patient_id, b.bill_number, b.created_at as bill_date
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        WHERE b.patient_id = ? AND bi.item_type = 'procedure'
        ORDER BY bi.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // GET TOOLS
    $stmt = $db->prepare("
        SELECT bi.*, b.patient_id, b.bill_number, b.created_at as bill_date
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        WHERE b.patient_id = ? AND bi.item_type = 'equipment'
        ORDER BY bi.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // GET VITAL SIGNS
    $stmt = $db->prepare("
        SELECT vs.*, u.full_name as recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.patient_id = ?
        ORDER BY vs.recorded_at DESC LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $latest_vitals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // GET PRESCRIPTIONS
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as doctor_name
        FROM prescriptions p
        LEFT JOIN users u ON p.doctor_id = u.id
        WHERE p.patient_id = ?
        ORDER BY p.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // GET LAB TESTS
    $stmt = $db->prepare("
        SELECT lt.*, u.full_name as doctor_name
        FROM lab_tests lt
        LEFT JOIN users u ON lt.doctor_id = u.id
        WHERE lt.visit_id IN (SELECT id FROM visits WHERE patient_id = ?)
        ORDER BY lt.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // CALCULATE AGE
    if (!empty($patient['date_of_birth'])) {
        $birthDate = new DateTime($patient['date_of_birth']);
        $today = new DateTime('today');
        $age = $birthDate->diff($today)->y;
    }
    
    $branch_name = $patient['branch_name'] ?? $branch_name;
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $patient = null;
}

// HELPER: SpO2 STATUS
function getSpO2Status($spo2) {
    if ($spo2 === null || $spo2 === '') return ['label' => 'N/A', 'class' => 'unknown', 'color' => '#64748B'];
    $spo2 = (int)$spo2;
    if ($spo2 >= 95) return ['label' => 'NORMAL', 'class' => 'normal', 'color' => '#059669'];
    if ($spo2 >= 90) return ['label' => 'LOW', 'class' => 'low', 'color' => '#D97706'];
    return ['label' => 'CRITICAL', 'class' => 'critical', 'color' => '#DC2626'];
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

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
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
        --vp-primary: #0B5ED7;
        --vp-primary-dark: #0A4CA8;
        --vp-primary-light: #6EA8FE;
        --vp-primary-bg: #E8F0FE;
        --vp-primary-gradient: linear-gradient(135deg, #0B5ED7, #1A7AFF);
        --vp-success: #059669;
        --vp-success-dark: #047857;
        --vp-success-bg: #D1FAE5;
        --vp-danger: #DC2626;
        --vp-danger-dark: #B91C1C;
        --vp-danger-bg: #FEE2E2;
        --vp-warning: #D97706;
        --vp-warning-bg: #FEF3C7;
        --vp-purple: #7C3AED;
        --vp-purple-bg: #EDE9FE;
        --vp-sky: #0EA5E9;
        --vp-sky-dark: #0284C7;
        --vp-sky-bg: #E0F2FE;
        --vp-gray-50: #F8FAFC;
        --vp-gray-100: #F1F5F9;
        --vp-gray-200: #E2E8F0;
        --vp-gray-300: #CBD5E1;
        --vp-gray-400: #94A3B8;
        --vp-gray-500: #64748B;
        --vp-gray-600: #475569;
        --vp-gray-700: #334155;
        --vp-gray-800: #1E293B;
        --vp-gray-900: #0F172A;
        --vp-bg-body: #F1F5F9;
        --vp-bg-card: #FFFFFF;
        --vp-text-primary: #1E293B;
        --vp-text-secondary: #64748B;
        --vp-text-muted: #94A3B8;
        --vp-border-color: #E2E8F0;
        --vp-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --vp-shadow: 0 1px 3px rgba(0,0,0,0.08);
        --vp-shadow-md: 0 4px 6px rgba(0,0,0,0.07);
        --vp-shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
        --vp-shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
        --vp-shadow-blue: 0 4px 16px rgba(11, 94, 215, 0.15);
        --vp-radius: 12px;
        --vp-radius-lg: 18px;
    }

    [data-theme="dark"] {
        --vp-bg-body: #0F172A;
        --vp-bg-card: #1E293B;
        --vp-text-primary: #F1F5F9;
        --vp-text-secondary: #94A3B8;
        --vp-text-muted: #64748B;
        --vp-border-color: #334155;
        --vp-primary-bg: #1E3A5F;
        --vp-purple-bg: #2D1B5F;
        --vp-shadow: 0 1px 3px rgba(0,0,0,0.3);
        --vp-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --vp-shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
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
    .page-header-vp {
        background: var(--vp-primary-gradient);
        border-radius: var(--vp-radius-lg);
        padding: 24px 32px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
        position: relative;
        overflow: hidden;
    }

    .page-header-vp .page-title-vp {
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

    .page-header-vp .page-title-vp i { font-size: 2rem; opacity: 0.9; }

    .page-header-vp .page-subtitle-vp {
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

    .page-header-vp .page-subtitle-vp strong { color: white; font-weight: 600; }

    .page-header-vp .role-badge-display-vp {
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

    .page-header-vp .header-badge-vp {
        background: rgba(255,255,255,0.15);
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

    .page-header-vp .btn-outline-light-vp {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: var(--vp-radius);
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

    .page-header-vp .btn-outline-light-vp:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       PROFILE HEADER
       ================================================================ */
    .profile-header-vp {
        background: var(--vp-bg-card);
        border-radius: 18px;
        padding: 28px 32px;
        border: 2px solid var(--vp-primary-light);
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        gap: 24px;
        align-items: center;
        box-shadow: var(--vp-shadow-blue);
        transition: all 0.3s ease;
    }

    .profile-header-vp:hover {
        border-color: var(--vp-primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.2);
    }

    .profile-avatar-vp {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        font-weight: 700;
        color: #ffffff;
        flex-shrink: 0;
    }

    .profile-avatar-vp.avatar-male { background: #0B5ED7; }
    .profile-avatar-vp.avatar-female { background: #DC2626; }
    .profile-avatar-vp.avatar-other { background: #7C3AED; }
    .profile-avatar-vp.avatar-default { background: #0B5ED7; }

    .profile-info-vp { flex: 1; min-width: 200px; }

    .profile-info-vp .patient-name-vp {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--vp-text-primary);
    }

    .profile-info-vp .patient-id-vp {
        font-size: 0.85rem;
        font-family: monospace;
        color: var(--vp-text-secondary);
        background: var(--vp-bg-body);
        padding: 2px 12px;
        border-radius: 12px;
        display: inline-block;
    }

    .profile-info-vp .patient-meta-vp {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin-top: 8px;
    }

    .profile-info-vp .patient-meta-vp .meta-item-vp {
        font-size: 0.8rem;
        color: var(--vp-text-secondary);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .profile-info-vp .patient-meta-vp .meta-item-vp i {
        color: var(--vp-primary);
        width: 16px;
    }

    .profile-actions-vp {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* ================================================================
       DETAIL CARDS
       ================================================================ */
    .detail-card-vp {
        background: var(--vp-bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--vp-primary-light);
        margin-bottom: 20px;
        box-shadow: var(--vp-shadow);
        transition: all 0.3s ease;
    }

    .detail-card-vp:hover {
        border-color: var(--vp-primary);
        box-shadow: var(--vp-shadow-blue);
    }

    [data-theme="dark"] .detail-card-vp {
        border-color: var(--vp-primary);
    }

    .detail-card-vp .card-title-vp {
        font-size: 1rem;
        font-weight: 600;
        color: var(--vp-text-primary);
        border-bottom: 2px solid var(--vp-primary-light);
        padding-bottom: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .detail-card-vp .card-title-vp i { color: var(--vp-primary); }

    .detail-grid-vp {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px 24px;
    }

    .detail-item-vp {
        display: flex;
        flex-direction: column;
        padding: 6px 0;
        border-bottom: 1px solid var(--vp-border-color);
    }

    .detail-item-vp .detail-label-vp {
        font-size: 0.65rem;
        font-weight: 600;
        color: var(--vp-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .detail-item-vp .detail-value-vp {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--vp-text-primary);
    }

    /* ================================================================
       VITAL SIGNS - 7 SIGNS
       ================================================================ */
    .vital-grid-7-vp {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
    }

    .vital-item-vp {
        background: var(--vp-primary-bg);
        border-radius: 10px;
        padding: 12px 14px;
        border-left: 4px solid var(--vp-primary);
        text-align: center;
        transition: all 0.3s ease;
    }

    .vital-item-vp:hover {
        transform: translateY(-2px);
        box-shadow: var(--vp-shadow-blue);
    }

    .vital-item-vp .vital-label-vp {
        font-size: 0.55rem;
        font-weight: 600;
        color: var(--vp-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: block;
    }

    .vital-item-vp .vital-value-vp {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--vp-primary-dark);
    }

    .vital-item-vp .vital-unit-vp {
        font-size: 0.6rem;
        font-weight: 400;
        color: var(--vp-text-secondary);
    }

    .vital-item-vp.spo2-item-vp {
        border-left-color: #0EA5E9;
        background: linear-gradient(135deg, rgba(14, 165, 233, 0.05), rgba(14, 165, 233, 0.12));
    }
    .vital-item-vp.spo2-item-vp .vital-value-vp { color: #0284C7; }
    .vital-item-vp.spo2-item-vp .vital-label-vp { color: #0284C7; }

    .vital-item-vp.green { border-left-color: var(--vp-success); }
    .vital-item-vp.green .vital-value-vp { color: var(--vp-success-dark); }
    .vital-item-vp.purple { border-left-color: var(--vp-purple); }
    .vital-item-vp.purple .vital-value-vp { color: var(--vp-purple); }
    .vital-item-vp.orange { border-left-color: var(--vp-warning); }
    .vital-item-vp.orange .vital-value-vp { color: var(--vp-warning); }
    .vital-item-vp.teal { border-left-color: #0D9488; }
    .vital-item-vp.teal .vital-value-vp { color: #0D9488; }
    .vital-item-vp.red { border-left-color: var(--vp-danger); }
    .vital-item-vp.red .vital-value-vp { color: var(--vp-danger); }

    .spo2-status-badge-vp {
        display: inline-block;
        font-size: 0.5rem;
        font-weight: 700;
        padding: 1px 8px;
        border-radius: 8px;
        margin-top: 3px;
        letter-spacing: 0.4px;
    }
    .spo2-status-badge-vp.normal { background: #D1FAE5; color: #059669; border: 1px solid #6EE7B7; }
    .spo2-status-badge-vp.low { background: #FEF3C7; color: #D97706; border: 1px solid #FCD34D; }
    .spo2-status-badge-vp.critical { background: #FEE2E2; color: #DC2626; border: 1px solid #FCA5A5; }
    .spo2-status-badge-vp.unknown { background: var(--vp-gray-200); color: var(--vp-text-secondary); }

    .bmi-label-vp {
        display: inline-block;
        font-size: 0.5rem;
        font-weight: 600;
        padding: 1px 8px;
        border-radius: 10px;
        margin-left: 4px;
    }
    .bmi-label-vp.normal { background: var(--vp-success-bg); color: var(--vp-success); }
    .bmi-label-vp.underweight { background: var(--vp-warning-bg); color: var(--vp-warning); }
    .bmi-label-vp.overweight { background: var(--vp-warning-bg); color: var(--vp-warning); }
    .bmi-label-vp.obese { background: var(--vp-danger-bg); color: var(--vp-danger); }

    .spo2-footer-info-vp {
        margin-top: 12px;
        padding: 8px 14px;
        background: linear-gradient(135deg, #F0F9FF, #E0F2FE);
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        font-size: 0.7rem;
        border: 1px dashed #0EA5E9;
    }

    [data-theme="dark"] .spo2-footer-info-vp {
        background: #0C2A3A;
        border-color: #0EA5E9;
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge-vp {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 12px;
        border-radius: 12px;
    }

    .badge-success-vp { background: var(--vp-success-bg); color: var(--vp-success); }
    .badge-danger-vp { background: var(--vp-danger-bg); color: var(--vp-danger); }
    .badge-warning-vp { background: var(--vp-warning-bg); color: var(--vp-warning); }
    .badge-info-vp { background: var(--vp-primary-bg); color: var(--vp-primary); }
    .badge-purple-vp { background: var(--vp-purple-bg); color: var(--vp-purple); }

    [data-theme="dark"] .badge-success-vp { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .badge-danger-vp { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .badge-warning-vp { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .badge-info-vp { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .badge-purple-vp { background: #2D1B5F; color: #A78BFA; }

    /* ================================================================
       TABLE
       ================================================================ */
    .table-wrapper-vp { overflow-x: auto; }

    .table-wrapper-vp table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .table-wrapper-vp table thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        color: var(--vp-text-secondary);
        border-bottom: 2px solid var(--vp-border-color);
        white-space: nowrap;
    }

    .table-wrapper-vp table tbody td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--vp-border-color);
        color: var(--vp-text-primary);
    }

    .table-wrapper-vp table tbody tr:hover {
        background: var(--vp-bg-body);
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-vp {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.82rem;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
    }

    .btn-primary-vp {
        background: var(--vp-primary-gradient);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
    }

    .btn-primary-vp:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 24px rgba(11, 94, 215, 0.35);
        color: white;
    }

    .btn-outline-vp {
        background: transparent;
        color: var(--vp-text-secondary);
        border: 2px solid var(--vp-border-color);
    }

    .btn-outline-vp:hover {
        background: var(--vp-bg-body);
        border-color: var(--vp-primary);
        color: var(--vp-primary);
    }

    .btn-sm-vp {
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 8px;
    }

    .btn-pdf-vp {
        background: #DC2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
    }

    .btn-pdf-vp:hover {
        background: #B91C1C;
        transform: translateY(-2px);
        box-shadow: 0 6px 24px rgba(220, 38, 38, 0.35);
        color: white;
    }

    /* ================================================================
       PDF MODAL
       ================================================================ */
    .pdf-modal-overlay-vp {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.6);
        z-index: 9999;
        backdrop-filter: blur(4px);
        justify-content: center;
        align-items: center;
    }

    .pdf-modal-overlay-vp.active { display: flex; }

    .pdf-modal-vp {
        background: var(--vp-bg-card);
        border-radius: 14px;
        width: 95%;
        max-width: 1100px;
        max-height: 95vh;
        display: flex;
        flex-direction: column;
        box-shadow: var(--vp-shadow-lg);
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .pdf-modal-header-vp {
        padding: 16px 24px;
        border-bottom: 2px solid var(--vp-border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
        background: var(--vp-primary-gradient);
        border-radius: 14px 14px 0 0;
        flex-wrap: wrap;
        gap: 8px;
    }

    .pdf-modal-header-vp .modal-title-vp {
        font-size: 1.1rem;
        font-weight: 700;
        color: white;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .pdf-modal-header-vp .modal-actions-vp {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .pdf-modal-header-vp .modal-actions-vp .btn-vp {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.78rem;
    }

    .pdf-modal-header-vp .modal-actions-vp .btn-vp:hover {
        background: rgba(255,255,255,0.3);
        transform: translateY(-2px);
    }

    .pdf-modal-body-vp {
        flex: 1;
        overflow-y: auto;
        padding: 24px 32px;
        background: var(--vp-bg-body);
    }

    .pdf-content-vp {
        max-width: 100%;
        font-size: 0.85rem;
        background: var(--vp-bg-card);
        padding: 32px 40px;
        border-radius: 10px;
        box-shadow: var(--vp-shadow);
        border: 1px solid var(--vp-border-color);
    }

    .pdf-content-vp .pdf-header-vp {
        text-align: center;
        padding-bottom: 20px;
        border-bottom: 3px solid var(--vp-primary);
        margin-bottom: 24px;
    }

    .pdf-content-vp .pdf-header-vp .pdf-logo-vp {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 16px;
        margin-bottom: 6px;
    }

    .pdf-content-vp .pdf-header-vp .pdf-logo-vp img {
        height: 55px;
        width: auto;
        object-fit: contain;
    }

    .pdf-content-vp .pdf-header-vp .clinic-name-vp {
        font-size: 1.6rem;
        font-weight: 800;
        color: var(--vp-primary);
        letter-spacing: -0.5px;
    }

    .pdf-content-vp .pdf-header-vp .clinic-sub-vp {
        font-size: 0.8rem;
        color: var(--vp-text-secondary);
        letter-spacing: 0.5px;
    }

    .pdf-content-vp .pdf-header-vp .doc-title-vp {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--vp-primary);
        margin-top: 6px;
        background: var(--vp-primary-bg);
        padding: 4px 16px;
        border-radius: 20px;
        display: inline-block;
    }

    .pdf-content-vp .section-title-vp {
        font-weight: 700;
        font-size: 1rem;
        color: var(--vp-primary);
        border-bottom: 2px solid var(--vp-primary-light);
        padding-bottom: 6px;
        margin: 18px 0 10px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .pdf-content-vp .pdf-row-vp {
        display: flex;
        padding: 4px 0;
        border-bottom: 1px solid var(--vp-border-color);
    }

    .pdf-content-vp .pdf-row-vp .pdf-label-vp {
        font-weight: 600;
        color: var(--vp-text-secondary);
        width: 160px;
        flex-shrink: 0;
    }

    .pdf-content-vp .pdf-row-vp .pdf-value-vp {
        flex: 1;
        color: var(--vp-text-primary);
    }

    .pdf-content-vp .pdf-grid-2-vp {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4px 20px;
    }

    .pdf-content-vp .pdf-vital-grid-vp {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
        margin: 8px 0;
    }

    .pdf-content-vp .pdf-vital-item-vp {
        background: var(--vp-primary-bg);
        padding: 8px 12px;
        border-radius: 6px;
        border-left: 3px solid var(--vp-primary);
        text-align: center;
    }

    .pdf-content-vp .pdf-vital-item-vp .vital-label-vp {
        font-size: 0.55rem;
        font-weight: 600;
        color: var(--vp-text-secondary);
        text-transform: uppercase;
    }

    .pdf-content-vp .pdf-vital-item-vp .vital-value-vp {
        font-size: 1rem;
        font-weight: 700;
        color: var(--vp-primary-dark);
    }

    .pdf-content-vp .pdf-vital-item-vp .vital-unit-vp {
        font-size: 0.55rem;
        color: var(--vp-text-secondary);
    }

    .pdf-content-vp .pdf-table-vp {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
        margin: 8px 0;
    }

    .pdf-content-vp .pdf-table-vp th {
        background: var(--vp-primary);
        color: white;
        padding: 6px 10px;
        text-align: left;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
    }

    .pdf-content-vp .pdf-table-vp td {
        padding: 5px 10px;
        border-bottom: 1px solid var(--vp-border-color);
    }

    .pdf-content-vp .pdf-table-vp tr:nth-child(even) td {
        background: var(--vp-gray-50);
    }

    [data-theme="dark"] .pdf-content-vp .pdf-table-vp tr:nth-child(even) td {
        background: #1E293B;
    }

    .pdf-content-vp .pdf-footer-vp {
        margin-top: 24px;
        padding-top: 20px;
        border-top: 2px solid var(--vp-border-color);
    }

    .pdf-content-vp .pdf-footer-vp .footer-stamp-vp {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .pdf-content-vp .pdf-footer-vp .footer-left-vp {
        font-size: 0.7rem;
        color: var(--vp-text-secondary);
    }

    .pdf-content-vp .pdf-footer-vp .stamp-box-vp {
        text-align: center;
        padding: 8px 20px;
        border: 3px solid var(--vp-primary);
        border-radius: 10px;
        background: var(--vp-primary-bg);
        min-width: 180px;
    }

    .pdf-content-vp .pdf-footer-vp .stamp-box-vp .stamp-title-vp {
        font-size: 0.55rem;
        color: var(--vp-text-secondary);
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 700;
    }

    .pdf-content-vp .pdf-footer-vp .stamp-box-vp .stamp-name-vp {
        font-size: 0.9rem;
        font-weight: 800;
        color: var(--vp-primary);
    }

    .pdf-content-vp .pdf-footer-vp .stamp-box-vp .stamp-line-vp {
        font-size: 0.65rem;
        color: var(--vp-text-secondary);
        margin-top: 2px;
    }

    .pdf-content-vp .pdf-footer-vp .stamp-box-vp .stamp-date-vp {
        font-size: 0.55rem;
        color: var(--vp-text-muted);
        margin-top: 2px;
    }

    .pdf-content-vp .pdf-footer-vp .footer-bottom-vp {
        text-align: center;
        margin-top: 12px;
        font-size: 0.6rem;
        color: var(--vp-text-muted);
    }

    .pdf-content-vp .pdf-footer-vp .footer-bottom-vp .footer-brand-vp {
        color: var(--vp-primary);
        font-weight: 700;
    }

    /* ================================================================
       TOAST
       ================================================================ */
    .toast-custom-vp {
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
        box-shadow: var(--vp-shadow-lg);
    }

    .toast-custom-vp.show {
        transform: translateY(0);
        opacity: 1;
    }

    .toast-custom-vp.success { background: var(--vp-success); }
    .toast-custom-vp.error { background: var(--vp-danger); }
    .toast-custom-vp.info { background: var(--vp-primary); }
    .toast-custom-vp.warning { background: var(--vp-warning); }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state-vp {
        text-align: center;
        padding: 30px;
        color: var(--vp-text-secondary);
    }

    .empty-state-vp i {
        font-size: 2rem;
        color: var(--vp-gray-300);
        display: block;
        margin-bottom: 8px;
    }

    [data-theme="dark"] .empty-state-vp i { color: var(--vp-gray-600); }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-vp {
        padding: 14px 0;
        border-top: 1px solid var(--vp-border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--vp-text-secondary);
    }

    .footer-vp .footer-brand-vp { 
        color: var(--vp-primary); 
        font-weight: 600; 
    }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-vp {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .vital-grid-7-vp { grid-template-columns: repeat(3, 1fr); }
        .pdf-content-vp .pdf-vital-grid-vp { grid-template-columns: repeat(2, 1fr); }
        .pdf-content-vp .pdf-grid-2-vp { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        .page-header-vp { padding: 16px 18px; }
        .page-header-vp .page-title-vp { font-size: 1.3rem; }
        .detail-grid-vp { grid-template-columns: 1fr; }
        .profile-header-vp { flex-direction: column; text-align: center; }
        .profile-info-vp .patient-meta-vp { justify-content: center; }
        .profile-actions-vp { justify-content: center; width: 100%; }
        .vital-grid-7-vp { grid-template-columns: repeat(3, 1fr); }
        .pdf-modal-body-vp .pdf-content-vp { padding: 16px; }
        .pdf-content-vp .pdf-row-vp { flex-direction: column; }
        .pdf-content-vp .pdf-row-vp .pdf-label-vp { width: 100%; }
        .pdf-content-vp .pdf-footer-vp .footer-stamp-vp { flex-direction: column; align-items: center; }
    }

    @media (max-width: 640px) {
        .vital-grid-7-vp { grid-template-columns: repeat(2, 1fr); }
        .pdf-modal-header-vp { flex-direction: column; align-items: stretch; }
        .pdf-modal-header-vp .modal-actions-vp { justify-content: center; }
        .pdf-modal-body-vp .pdf-content-vp { padding: 12px; }
    }

    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .no-print { display: none !important; }
        .page-header-vp, .profile-header-vp { break-inside: avoid; }
        .detail-card-vp { break-inside: avoid; border: 1px solid #ddd !important; }
        .badge-vp { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .vital-item-vp { background: #E8F0FE !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .vital-item-vp.spo2-item-vp { background: #E0F2FE !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($patient): ?>
    
    <!-- PAGE HEADER -->
    <div class="page-header-vp animate-fade-in-up-vp">
        <div>
            <h1 class="page-title-vp">
                <i class="fas fa-user-circle"></i>
                Patient Details
                <span class="role-badge-display-vp"><?= strtoupper($role) ?></span>
            </h1>
            <p class="page-subtitle-vp">
                <i class="fas fa-id-card"></i>
                View complete patient information for <strong><?= htmlspecialchars($patient['full_name']) ?></strong>
                
                <span class="header-badge-vp">
                    <i class="fas fa-user"></i>
                    ID: <strong><?= htmlspecialchars($patient['patient_id']) ?></strong>
                </span>
                
                <span class="header-badge-vp" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-ring"></i>
                    <?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="patients.php" class="btn-outline-light-vp">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
            <button onclick="generatePDF()" class="btn-outline-light-vp" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>
    
    <!-- PROFILE HEADER -->
    <div class="profile-header-vp animate-fade-in-up-vp" style="animation-delay:0.05s;">
        <?php
            $gender = $patient['gender'] ?? '';
            $avatar_class = 'avatar-default';
            if ($gender === 'Male') $avatar_class = 'avatar-male';
            elseif ($gender === 'Female') $avatar_class = 'avatar-female';
            elseif ($gender === 'Other') $avatar_class = 'avatar-other';
        ?>
        <div class="profile-avatar-vp <?= $avatar_class ?>">
            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
        </div>
        
        <div class="profile-info-vp">
            <div>
                <span class="patient-name-vp"><?= htmlspecialchars($patient['full_name']) ?></span>
                <span class="patient-id-vp"><?= htmlspecialchars($patient['patient_id']) ?></span>
                <?php if (!empty($patient['assigned_doctor_name'])): ?>
                    <span class="badge-vp badge-info-vp">
                        <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($patient['assigned_doctor_name']) ?>
                        <?= $patient['assigned_doctor_online'] ? '🟢' : '⚪' ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="patient-meta-vp">
                <span class="meta-item-vp"><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                <span class="meta-item-vp"><i class="fas fa-ring"></i> <?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?></span>
                <span class="meta-item-vp"><i class="fas fa-calendar"></i> <?= !empty($patient['date_of_birth']) ? date('d M Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></span>
                <span class="meta-item-vp"><i class="fas fa-clock"></i> <?= $age ?> years</span>
                <span class="meta-item-vp"><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                <span class="meta-item-vp"><i class="fas fa-envelope"></i> <?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
                <span class="meta-item-vp"><i class="fas fa-tint"></i> <?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
                <span class="meta-item-vp">
                    <i class="fas fa-circle" style="color:<?= ($patient['status'] ?? 'active') === 'active' ? '#059669' : '#DC2626' ?>;"></i>
                    <?= ucfirst($patient['status'] ?? 'Active') ?>
                </span>
            </div>
        </div>
        
        <div class="profile-actions-vp no-print">
            <a href="assign_doctor.php?patient_id=<?= $patient['id'] ?>" class="btn-vp btn-primary-vp btn-sm-vp">
                <i class="fas fa-user-md"></i> Assign Doctor
            </a>
            <button onclick="window.print()" class="btn-vp btn-outline-vp btn-sm-vp">
                <i class="fas fa-print"></i> Print
            </button>
            <button onclick="generatePDF()" class="btn-vp btn-pdf-vp btn-sm-vp">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
        </div>
    </div>
    
    <!-- 1. PERSONAL INFORMATION -->
    <div class="detail-card-vp animate-fade-in-up-vp" style="animation-delay:0.1s;">
        <div class="card-title-vp">
            <i class="fas fa-user"></i>
            Personal Information
        </div>
        
        <div class="detail-grid-vp">
            <div class="detail-item-vp">
                <span class="detail-label-vp">Full Name</span>
                <span class="detail-value-vp"><?= htmlspecialchars($patient['full_name']) ?></span>
            </div>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Patient ID</span>
                <span class="detail-value-vp"><?= htmlspecialchars($patient['patient_id']) ?></span>
            </div>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Gender</span>
                <span class="detail-value-vp"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Marital Status</span>
                <span class="detail-value-vp"><?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Date of Birth</span>
                <span class="detail-value-vp">
                    <?= !empty($patient['date_of_birth']) ? date('d M Y', strtotime($patient['date_of_birth'])) : 'N/A' ?>
                    <?php if ($age !== 'N/A'): ?>
                        <span style="font-size:0.7rem;color:var(--vp-text-secondary);">(<?= $age ?> years)</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Age</span>
                <span class="detail-value-vp"><?= $age ?> years</span>
            </div>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Phone</span>
                <span class="detail-value-vp"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Email</span>
                <span class="detail-value-vp"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Blood Group</span>
                <span class="detail-value-vp"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Branch</span>
                <span class="detail-value-vp"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Registered By</span>
                <span class="detail-value-vp"><?= htmlspecialchars($patient['created_by_name'] ?? 'System') ?></span>
            </div>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Registered At</span>
                <span class="detail-value-vp"><?= date('d M Y h:i A', strtotime($patient['created_at'])) ?></span>
            </div>
            <div class="detail-item-vp" style="grid-column: 1 / -1;">
                <span class="detail-label-vp">Address</span>
                <span class="detail-value-vp"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>
    
    <!-- 2. ASSIGNED DOCTOR & ACTIVE VISIT -->
    <div class="detail-card-vp animate-fade-in-up-vp" style="animation-delay:0.15s;">
        <div class="card-title-vp">
            <i class="fas fa-user-md"></i>
            Assigned Doctor & Active Visit
        </div>
        
        <div class="detail-grid-vp">
            <div class="detail-item-vp">
                <span class="detail-label-vp">Assigned Doctor</span>
                <span class="detail-value-vp">
                    <?php if (!empty($patient['assigned_doctor_name'])): ?>
                        <span class="badge-vp badge-info-vp">
                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($patient['assigned_doctor_name']) ?>
                            <?= $patient['assigned_doctor_online'] ? '🟢 Online' : '⚪ Offline' ?>
                        </span>
                    <?php else: ?>
                        <span style="color:var(--vp-text-secondary);">No doctor assigned</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Active Visit</span>
                <span class="detail-value-vp">
                    <?php if ($active_visit): ?>
                        <span class="badge-vp badge-<?= ($active_visit['status'] ?? 'pending') === 'completed' ? 'success' : (($active_visit['status'] ?? '') === 'cancelled' ? 'danger' : 'warning') ?>-vp">
                            <?= ucfirst(str_replace('_', ' ', $active_visit['status'] ?? 'Pending')) ?>
                        </span>
                        <span style="font-size:0.7rem;color:var(--vp-text-secondary);">
                            #<?= htmlspecialchars($active_visit['visit_number'] ?? '') ?>
                        </span>
                    <?php else: ?>
                        <span style="color:var(--vp-text-secondary);">No active visit</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- 3. LATEST VITAL SIGNS - 7 SIGNS WITH SpO2 -->
    <?php if ($latest_vitals): 
        $spo2_status = getSpO2Status($latest_vitals['oxygen_saturation'] ?? null);
    ?>
    <div class="detail-card-vp animate-fade-in-up-vp" style="animation-delay:0.2s;">
        <div class="card-title-vp" style="border-bottom: 2px solid var(--vp-primary-light);">
            <i class="fas fa-heartbeat" style="color:#DC2626;"></i>
            Latest Vital Signs (7 Signs)
            <span style="font-size:0.7rem;font-weight:400;color:#0284C7;">🫁 SpO2 Normal: 95-100%</span>
            <span style="font-size:0.7rem;color:var(--vp-text-secondary);">(<?= date('d M Y h:i A', strtotime($latest_vitals['recorded_at'])) ?>)</span>
        </div>
        
        <div class="vital-grid-7-vp">
            <!-- 1. Temperature -->
            <div class="vital-item-vp">
                <span class="vital-label-vp">🌡️ Temperature</span>
                <span class="vital-value-vp"><?= $latest_vitals['temperature'] ?? 'N/A' ?> <span class="vital-unit-vp">°C</span></span>
            </div>
            
            <!-- 2. Blood Pressure -->
            <div class="vital-item-vp green">
                <span class="vital-label-vp">❤️ Blood Pressure</span>
                <span class="vital-value-vp">
                    <?php if (!empty($latest_vitals['blood_pressure_systolic']) && !empty($latest_vitals['blood_pressure_diastolic'])): ?>
                        <?= $latest_vitals['blood_pressure_systolic'] ?> / <?= $latest_vitals['blood_pressure_diastolic'] ?> <span class="vital-unit-vp">mmHg</span>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </span>
            </div>
            
            <!-- 3. Pulse Rate -->
            <div class="vital-item-vp purple">
                <span class="vital-label-vp">💓 Pulse Rate</span>
                <span class="vital-value-vp"><?= $latest_vitals['pulse_rate'] ?? 'N/A' ?> <span class="vital-unit-vp">bpm</span></span>
            </div>
            
            <!-- 4. OXYGEN SATURATION (SpO2) -->
            <div class="vital-item-vp spo2-item-vp">
                <span class="vital-label-vp">🫁 Oxygen (SpO2)</span>
                <span class="vital-value-vp">
                    <?= ($latest_vitals['oxygen_saturation'] !== null && $latest_vitals['oxygen_saturation'] !== '') ? $latest_vitals['oxygen_saturation'] : '--' ?> 
                    <span class="vital-unit-vp">%</span>
                </span>
                <?php if ($latest_vitals['oxygen_saturation'] !== null && $latest_vitals['oxygen_saturation'] !== ''): ?>
                    <span class="spo2-status-badge-vp <?= $spo2_status['class'] ?>"><?= $spo2_status['label'] ?></span>
                <?php endif; ?>
            </div>
            
            <!-- 5. Weight -->
            <div class="vital-item-vp orange">
                <span class="vital-label-vp">⚖️ Weight</span>
                <span class="vital-value-vp"><?= $latest_vitals['weight'] ?? 'N/A' ?> <span class="vital-unit-vp">kg</span></span>
            </div>
            
            <!-- 6. Height -->
            <div class="vital-item-vp teal">
                <span class="vital-label-vp">📏 Height</span>
                <span class="vital-value-vp"><?= $latest_vitals['height'] ?? 'N/A' ?> <span class="vital-unit-vp">cm</span></span>
            </div>
            
            <!-- 7. BMI -->
            <div class="vital-item-vp red">
                <span class="vital-label-vp">📊 BMI</span>
                <span class="vital-value-vp">
                    <?= $latest_vitals['bmi'] ?? 'N/A' ?>
                    <span class="vital-unit-vp">kg/m²</span>
                    <?php if (!empty($latest_vitals['bmi'])): ?>
                        <?php 
                            $bmi = $latest_vitals['bmi'];
                            if ($bmi < 18.5) $bmi_label = 'Underweight';
                            elseif ($bmi < 25) $bmi_label = 'Normal';
                            elseif ($bmi < 30) $bmi_label = 'Overweight';
                            else $bmi_label = 'Obese';
                            $bmi_class = strtolower($bmi_label);
                        ?>
                        <span class="bmi-label-vp <?= $bmi_class ?>"><?= $bmi_label ?></span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <div class="spo2-footer-info-vp">
            <i class="fas fa-lungs" style="color:#0EA5E9;"></i>
            <span style="color:#0284C7;">SpO2 (Oxygen Saturation) Normal Range: <strong>95-100%</strong></span>
            <span style="color:var(--vp-text-secondary);"> • 7 Vital Signs Tracked</span>
        </div>
        
        <?php if (!empty($latest_vitals['notes'])): ?>
            <div style="margin-top:12px;font-size:0.85rem;color:var(--vp-text-secondary);">
                <i class="fas fa-sticky-note" style="color:var(--vp-primary);"></i> Notes: <?= htmlspecialchars($latest_vitals['notes']) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($latest_vitals['recorded_by_name'])): ?>
            <div style="margin-top:8px;font-size:0.7rem;color:var(--vp-text-muted);">
                <i class="fas fa-user"></i> Recorded By: <?= htmlspecialchars($latest_vitals['recorded_by_name']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- 4. VISIT HISTORY -->
    <div class="detail-card-vp animate-fade-in-up-vp" style="animation-delay:0.25s;">
        <div class="card-title-vp">
            <i class="fas fa-clock"></i>
            Visit History
            <span style="font-size:0.7rem;color:var(--vp-text-secondary);">(Last 10 visits)</span>
        </div>
        
        <?php if (count($visit_history) > 0): ?>
            <div class="table-wrapper-vp">
                <table>
                    <thead>
                        <tr>
                            <th>Visit #</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Bill</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visit_history as $visit): ?>
                            <tr>
                                <td><span style="font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span></td>
                                <td><?= date('d M Y', strtotime($visit['created_at'])) ?></td>
                                <td><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-vp badge-<?= ($visit['status'] ?? 'pending') === 'completed' ? 'success' : (($visit['status'] ?? '') === 'cancelled' ? 'danger' : 'warning') ?>-vp">
                                        <?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($visit['bill_amount'])): ?>
                                        <span style="font-weight:600;">TSh <?= number_format($visit['bill_amount'], 0) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--vp-text-secondary);">No bill</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_visit.php?id=<?= $visit['id'] ?>" class="btn-vp btn-outline-vp btn-sm-vp">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state-vp">
                <i class="fas fa-clock"></i>
                <p>No visit history found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 5. SYMPTOMS -->
    <?php if ($active_visit && !empty($active_visit['symptoms'])): ?>
    <div class="detail-card-vp animate-fade-in-up-vp" style="animation-delay:0.3s;">
        <div class="card-title-vp">
            <i class="fas fa-notes-medical"></i>
            Symptoms & Complaint
        </div>
        
        <div class="detail-grid-vp">
            <div class="detail-item-vp">
                <span class="detail-label-vp">Symptoms</span>
                <span class="detail-value-vp"><?= htmlspecialchars($active_visit['symptoms'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Complaint / Reason</span>
                <span class="detail-value-vp"><?= htmlspecialchars($active_visit['complaint'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($active_visit['notes'])): ?>
            <div class="detail-item-vp" style="grid-column: 1 / -1;">
                <span class="detail-label-vp">Notes</span>
                <span class="detail-value-vp"><?= htmlspecialchars($active_visit['notes']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- 6. LAB TESTS -->
    <div class="detail-card-vp animate-fade-in-up-vp" style="animation-delay:0.35s;">
        <div class="card-title-vp">
            <i class="fas fa-flask"></i>
            Lab Tests
            <span style="font-size:0.7rem;color:var(--vp-text-secondary);">(Last 10)</span>
        </div>
        
        <?php if (!empty($lab_tests) && count($lab_tests) > 0): ?>
            <div class="table-wrapper-vp">
                <table>
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Results</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): ?>
                            <tr>
                                <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($test['created_at'])) ?></td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-vp badge-<?= ($test['status'] ?? 'pending') === 'completed' ? 'success' : (($test['status'] ?? '') === 'cancelled' ? 'danger' : 'warning') ?>-vp">
                                        <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($test['results'])): ?>
                                        <span style="color:var(--vp-success);">✅ Results available</span>
                                    <?php else: ?>
                                        <span style="color:var(--vp-text-secondary);">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_lab_test.php?id=<?= $test['id'] ?>" class="btn-vp btn-outline-vp btn-sm-vp">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state-vp">
                <i class="fas fa-flask"></i>
                <p>No lab tests found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 7. DIAGNOSIS -->
    <?php if ($active_visit && !empty($active_visit['diagnosis'])): ?>
    <div class="detail-card-vp animate-fade-in-up-vp" style="animation-delay:0.4s;">
        <div class="card-title-vp">
            <i class="fas fa-stethoscope"></i>
            Diagnosis & Treatment
        </div>
        
        <div class="detail-grid-vp">
            <div class="detail-item-vp">
                <span class="detail-label-vp">Diagnosis</span>
                <span class="detail-value-vp"><?= htmlspecialchars($active_visit['diagnosis'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Treatment</span>
                <span class="detail-value-vp"><?= htmlspecialchars($active_visit['treatment'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($active_visit['follow_up_date'])): ?>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Follow-up Date</span>
                <span class="detail-value-vp"><?= date('d M Y', strtotime($active_visit['follow_up_date'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- 8. MEDICAL INFORMATION -->
    <div class="detail-card-vp animate-fade-in-up-vp" style="animation-delay:0.45s;">
        <div class="card-title-vp">
            <i class="fas fa-notes-medical"></i>
            Medical Information
        </div>
        
        <div class="detail-grid-vp">
            <div class="detail-item-vp">
                <span class="detail-label-vp">Blood Group</span>
                <span class="detail-value-vp"><?= htmlspecialchars($patient['blood_group'] ?? 'Not recorded') ?></span>
            </div>
            <div class="detail-item-vp">
                <span class="detail-label-vp">Emergency Contact</span>
                <span class="detail-value-vp"><?= htmlspecialchars($patient['emergency_contact'] ?? 'Not recorded') ?></span>
            </div>
            <div class="detail-item-vp" style="grid-column: 1 / -1;">
                <span class="detail-label-vp">Allergies</span>
                <span class="detail-value-vp">
                    <?php if (!empty($patient['allergies'])): ?>
                        <?php 
                            $allergy_list = array_map('trim', explode(',', $patient['allergies']));
                            foreach ($allergy_list as $allergy): 
                        ?>
                            <span class="badge-vp badge-danger-vp" style="margin:2px;">⚠️ <?= htmlspecialchars($allergy) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color:var(--vp-text-secondary);">No known allergies</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- 9. PRESCRIPTIONS -->
    <div class="detail-card-vp animate-fade-in-up-vp" style="animation-delay:0.5s;">
        <div class="card-title-vp">
            <i class="fas fa-prescription"></i>
            Prescriptions
            <span style="font-size:0.7rem;color:var(--vp-text-secondary);">(Last 10)</span>
        </div>
        
        <?php if (!empty($prescriptions) && count($prescriptions) > 0): ?>
            <div class="table-wrapper-vp">
                <table>
                    <thead>
                        <tr>
                            <th>Prescription #</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Medication</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $prescription): ?>
                            <tr>
                                <td><span style="font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></span></td>
                                <td><?= date('d M Y', strtotime($prescription['created_at'])) ?></td>
                                <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prescription['medication'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-vp badge-<?= ($prescription['status'] ?? 'pending') === 'dispensed' ? 'success' : (($prescription['status'] ?? '') === 'cancelled' ? 'danger' : 'warning') ?>-vp">
                                        <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_prescription.php?id=<?= $prescription['id'] ?>" class="btn-vp btn-outline-vp btn-sm-vp">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state-vp">
                <i class="fas fa-prescription"></i>
                <p>No prescriptions found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 10. PROCEDURES -->
    <div class="detail-card-vp animate-fade-in-up-vp" style="animation-delay:0.55s;">
        <div class="card-title-vp">
            <i class="fas fa-syringe" style="color:#7C3AED;"></i>
            Procedures
            <span style="font-size:0.7rem;color:var(--vp-text-secondary);">(Last 10)</span>
        </div>
        
        <?php if (!empty($procedures) && count($procedures) > 0): ?>
            <div class="table-wrapper-vp">
                <table>
                    <thead>
                        <tr>
                            <th>Procedure Name</th>
                            <th>Date</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Bill #</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedures as $procedure): ?>
                            <tr>
                                <td><?= htmlspecialchars($procedure['item_name'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($procedure['created_at'])) ?></td>
                                <td><?= $procedure['quantity'] ?? 1 ?></td>
                                <td>TSh <?= number_format($procedure['unit_price'] ?? 0, 0) ?></td>
                                <td><span style="font-weight:600;">TSh <?= number_format($procedure['total_price'] ?? 0, 0) ?></span></td>
                                <td>
                                    <span class="badge-vp badge-<?= ($procedure['status'] ?? 'pending') === 'paid' ? 'success' : 'warning' ?>-vp">
                                        <?= ucfirst($procedure['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td><span style="font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars($procedure['bill_number'] ?? 'N/A') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state-vp">
                <i class="fas fa-syringe"></i>
                <p>No procedures found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 11. TOOLS -->
    <div class="detail-card-vp animate-fade-in-up-vp" style="animation-delay:0.6s;">
        <div class="card-title-vp">
            <i class="fas fa-tools" style="color:#D97706;"></i>
            Tools / Equipment
            <span style="font-size:0.7rem;color:var(--vp-text-secondary);">(Last 10)</span>
        </div>
        
        <?php if (!empty($tools) && count($tools) > 0): ?>
            <div class="table-wrapper-vp">
                <table>
                    <thead>
                        <tr>
                            <th>Tool Name</th>
                            <th>Date</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Bill #</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tools as $tool): ?>
                            <tr>
                                <td><?= htmlspecialchars($tool['item_name'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($tool['created_at'])) ?></td>
                                <td><?= $tool['quantity'] ?? 1 ?></td>
                                <td>TSh <?= number_format($tool['unit_price'] ?? 0, 0) ?></td>
                                <td><span style="font-weight:600;">TSh <?= number_format($tool['total_price'] ?? 0, 0) ?></span></td>
                                <td>
                                    <span class="badge-vp badge-<?= ($tool['status'] ?? 'pending') === 'paid' ? 'success' : 'warning' ?>-vp">
                                        <?= ucfirst($tool['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td><span style="font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars($tool['bill_number'] ?? 'N/A') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state-vp">
                <i class="fas fa-tools"></i>
                <p>No tools found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 12. BILLS -->
    <div class="detail-card-vp animate-fade-in-up-vp" style="animation-delay:0.65s;">
        <div class="card-title-vp">
            <i class="fas fa-receipt"></i>
            Bills
            <span style="font-size:0.7rem;color:var(--vp-text-secondary);">(Last 10 bills)</span>
        </div>
        
        <?php if (!empty($bills) && count($bills) > 0): ?>
            <div class="table-wrapper-vp">
                <table>
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Date</th>
                            <th>Visit</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td><span style="font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></span></td>
                                <td><?= date('d M Y', strtotime($bill['created_at'])) ?></td>
                                <td><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></td>
                                <td><span style="font-weight:600;">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></span></td>
                                <td style="color:var(--vp-success);">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                <td style="color:<?= ($bill['balance'] ?? 0) > 0 ? 'var(--vp-danger)' : 'var(--vp-success)' ?>;">
                                    TSh <?= number_format($bill['balance'] ?? 0, 0) ?>
                                </td>
                                <td>
                                    <span class="badge-vp badge-<?= ($bill['status'] ?? 'pending') === 'paid' ? 'success' : (($bill['status'] ?? '') === 'partial' ? 'warning' : (($bill['status'] ?? '') === 'cancelled' ? 'danger' : 'info')) ?>-vp">
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn-vp btn-outline-vp btn-sm-vp">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state-vp">
                <i class="fas fa-receipt"></i>
                <p>No bills found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- FOOTER -->
    <footer class="footer-vp no-print" style="border-top: 3px solid var(--vp-primary); padding: 20px 0; margin-top: 30px;">
        <div style="text-align: center;">
            <div style="font-size: 1.2rem; font-weight: 800; color: var(--vp-primary); letter-spacing: 1px;">
                BRAICK DISPENSARY
            </div>
            <div style="font-size: 0.9rem; font-weight: 600; color: var(--vp-text-secondary); margin-top: 4px;">
                <i class="fas fa-heart" style="color: #DC2626;"></i> 
                TUNAJARI AFYA YAKO 
                <i class="fas fa-heart" style="color: #DC2626;"></i>
            </div>
            <div style="font-size: 0.7rem; color: var(--vp-text-secondary); margin-top: 8px;">
                <span class="footer-brand-vp">Braick Dispensary</span> Management System
                <span style="color:#CBD5E1;margin:0 8px;">|</span>
                View Patient
                <span style="color:#CBD5E1;margin:0 8px;">|</span>
                <?= htmlspecialchars($patient['full_name']) ?>
                <span style="color:#CBD5E1;margin:0 8px;">|</span>
                <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
                <span style="color:#CBD5E1;margin:0 8px;">|</span>
                &copy; <?= date('Y') ?> All rights reserved
            </div>
        </div>
    </footer>
    
    <?php else: ?>
    
    <div class="detail-card-vp">
        <div class="empty-state-vp">
            <i class="fas fa-user-slash" style="font-size:3rem;"></i>
            <h3 style="margin-top:12px;color:var(--vp-text-primary);">Patient Not Found</h3>
            <p style="color:var(--vp-text-secondary);">The patient you are looking for does not exist.</p>
            <a href="patients.php" class="btn-vp btn-primary-vp" style="margin-top:12px;">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>
    </div>
    
    <?php endif; ?>

</main>

<!-- ================================================================ -->
<!-- PDF MODAL -->
<!-- ================================================================ -->
<div class="pdf-modal-overlay-vp" id="pdfModal">
    <div class="pdf-modal-vp">
        <div class="pdf-modal-header-vp">
            <div class="modal-title-vp">
                <i class="fas fa-file-pdf"></i>
                Patient PDF Preview - <?= htmlspecialchars($patient['full_name'] ?? 'Patient') ?>
            </div>
            <div class="modal-actions-vp">
                <button onclick="downloadPDF()" class="btn-vp" id="downloadPdfBtn">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn-vp">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn-vp" style="background:rgba(220,38,38,0.3);">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <div class="pdf-modal-body-vp" id="pdfModalBody">
            <div class="pdf-content-vp" id="pdfContent">
                <!-- PDF content generated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom-vp" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    // ================================================================
    // FOOTER TIMESTAMP UPDATE
    // ================================================================
    function updateFooterTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('footerTimestamp');
        if (el) el.textContent = 'Last updated: ' + timeStr;
    }
    setInterval(updateFooterTime, 1000);
    updateFooterTime();

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom-vp ' + type;
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
    // GENERATE PDF - WITH 7 VITAL SIGNS (SpO2)
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        var patientData = {
            patient_id: '<?= addslashes($patient['patient_id'] ?? 'N/A') ?>',
            full_name: '<?= addslashes($patient['full_name'] ?? 'N/A') ?>',
            gender: '<?= addslashes($patient['gender'] ?? 'N/A') ?>',
            marital_status: '<?= addslashes($patient['marital_status'] ?? 'N/A') ?>',
            date_of_birth: '<?= !empty($patient['date_of_birth']) ? date('d/m/Y', strtotime($patient['date_of_birth'])) : 'N/A' ?>',
            age: '<?= $age ?>',
            phone: '<?= addslashes($patient['phone'] ?? 'N/A') ?>',
            email: '<?= addslashes($patient['email'] ?? 'N/A') ?>',
            address: '<?= addslashes($patient['address'] ?? 'N/A') ?>',
            blood_group: '<?= addslashes($patient['blood_group'] ?? 'N/A') ?>',
            allergies: '<?= addslashes($patient['allergies'] ?? 'None') ?>',
            emergency_contact: '<?= addslashes($patient['emergency_contact'] ?? 'N/A') ?>',
            branch_name: '<?= addslashes($patient['branch_name'] ?? $branch_name) ?>',
            assigned_doctor: '<?= addslashes($patient['assigned_doctor_name'] ?? 'Not Assigned') ?>',
            created_at: '<?= date('d/m/Y h:i A', strtotime($patient['created_at'] ?? 'now')) ?>',
            created_by: '<?= addslashes($patient['created_by_name'] ?? 'System') ?>'
        };
        
        var vitals = <?= $latest_vitals ? json_encode($latest_vitals) : 'null' ?>;
        var visitHistory = <?= json_encode($visit_history) ?>;
        var bills = <?= json_encode($bills) ?>;
        var procedures = <?= json_encode($procedures) ?>;
        var tools = <?= json_encode($tools) ?>;
        var prescriptions = <?= json_encode($prescriptions) ?>;
        var labTests = <?= json_encode($lab_tests) ?>;
        var activeVisit = <?= $active_visit ? json_encode($active_visit) : 'null' ?>;
        
        var now = new Date();
        var reportDate = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        var reportTime = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
        
        // Build vitals HTML
        var vitalsHtml = '';
        if (vitals) {
            var spo2Value = vitals.oxygen_saturation;
            var spo2Label = 'N/A';
            var spo2Color = '#64748B';
            var spo2Bg = '#F1F5F9';
            var spo2Border = '#CBD5E1';
            if (spo2Value !== null && spo2Value !== '' && spo2Value !== undefined) {
                var spo2Int = parseInt(spo2Value);
                if (spo2Int >= 95) { spo2Label = 'NORMAL'; spo2Color = '#059669'; spo2Bg = '#D1FAE5'; spo2Border = '#6EE7B7'; }
                else if (spo2Int >= 90) { spo2Label = 'LOW'; spo2Color = '#D97706'; spo2Bg = '#FEF3C7'; spo2Border = '#FCD34D'; }
                else { spo2Label = 'CRITICAL'; spo2Color = '#DC2626'; spo2Bg = '#FEE2E2'; spo2Border = '#FCA5A5'; }
            }
            
            vitalsHtml = `
                <div class="pdf-vital-grid-vp">
                    <div class="pdf-vital-item-vp">
                        <div class="vital-label-vp">🌡️ Temperature</div>
                        <div class="vital-value-vp">${vitals.temperature || 'N/A'} <span class="vital-unit-vp">°C</span></div>
                    </div>
                    <div class="pdf-vital-item-vp">
                        <div class="vital-label-vp">❤️ Blood Pressure</div>
                        <div class="vital-value-vp">
                            ${vitals.blood_pressure_systolic && vitals.blood_pressure_diastolic ? 
                                vitals.blood_pressure_systolic + ' / ' + vitals.blood_pressure_diastolic + ' <span class="vital-unit-vp">mmHg</span>' : 'N/A'}
                        </div>
                    </div>
                    <div class="pdf-vital-item-vp">
                        <div class="vital-label-vp">💓 Pulse Rate</div>
                        <div class="vital-value-vp">${vitals.pulse_rate || 'N/A'} <span class="vital-unit-vp">bpm</span></div>
                    </div>
                    <div class="pdf-vital-item-vp" style="background:linear-gradient(135deg, #E0F2FE, #BAE6FD);border-left:4px solid #0EA5E9;">
                        <div class="vital-label-vp" style="color:#0284C7;">🫁 Oxygen (SpO2)</div>
                        <div class="vital-value-vp" style="color:#0284C7;">
                            ${spo2Value !== null && spo2Value !== '' && spo2Value !== undefined ? spo2Value : '--'} <span class="vital-unit-vp">%</span>
                        </div>
                        ${spo2Value !== null && spo2Value !== '' && spo2Value !== undefined ? 
                            `<div style="display:inline-block;font-size:0.5rem;font-weight:700;padding:1px 8px;border-radius:8px;margin-top:3px;letter-spacing:0.4px;background:${spo2Bg};color:${spo2Color};border:1px solid ${spo2Border};">${spo2Label}</div>` : ''}
                    </div>
                    <div class="pdf-vital-item-vp">
                        <div class="vital-label-vp">⚖️ Weight</div>
                        <div class="vital-value-vp">${vitals.weight || 'N/A'} <span class="vital-unit-vp">kg</span></div>
                    </div>
                    <div class="pdf-vital-item-vp">
                        <div class="vital-label-vp">📏 Height</div>
                        <div class="vital-value-vp">${vitals.height || 'N/A'} <span class="vital-unit-vp">cm</span></div>
                    </div>
                    <div class="pdf-vital-item-vp">
                        <div class="vital-label-vp">📊 BMI</div>
                        <div class="vital-value-vp">${vitals.bmi || 'N/A'} <span class="vital-unit-vp">kg/m²</span></div>
                    </div>
                </div>
                <div style="margin-top:8px;padding:6px 12px;background:#F0F9FF;border-radius:6px;border:1px dashed #0EA5E9;font-size:0.65rem;">
                    <span style="color:#0284C7;">🫁 SpO2 Normal Range: <strong>95-100%</strong></span>
                    <span style="color:#64748B;"> • 7 Vital Signs Tracked</span>
                </div>
            `;
        } else {
            vitalsHtml = `<p style="color:var(--vp-text-secondary);">No vital signs recorded</p>`;
        }
        
        // Build visit history HTML
        var visitHtml = '';
        if (visitHistory && visitHistory.length > 0) {
            visitHtml = `
                <table class="pdf-table-vp">
                    <thead><tr><th>Visit #</th><th>Date</th><th>Type</th><th>Doctor</th><th>Status</th><th>Bill</th></tr></thead>
                    <tbody>
                        ${visitHistory.map(function(v) {
                            return `<tr>
                                <td>${v.visit_number || 'N/A'}</td>
                                <td>${new Date(v.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                <td>${v.visit_type || 'N/A'}</td>
                                <td>${v.doctor_name || 'N/A'}</td>
                                <td>${v.status || 'N/A'}</td>
                                <td>${v.bill_amount ? 'TSh ' + Number(v.bill_amount).toLocaleString() : 'N/A'}</td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            visitHtml = `<p style="color:var(--vp-text-secondary);">No visit history</p>`;
        }
        
        // Build bills HTML
        var billsHtml = '';
        if (bills && bills.length > 0) {
            billsHtml = `
                <table class="pdf-table-vp">
                    <thead><tr><th>Bill #</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
                    <tbody>
                        ${bills.map(function(b) {
                            return `<tr>
                                <td>${b.bill_number || 'N/A'}</td>
                                <td>${new Date(b.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                <td>TSh ${Number(b.total_amount || 0).toLocaleString()}</td>
                                <td>TSh ${Number(b.paid_amount || 0).toLocaleString()}</td>
                                <td>TSh ${Number(b.balance || 0).toLocaleString()}</td>
                                <td>${b.status || 'N/A'}</td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            billsHtml = `<p style="color:var(--vp-text-secondary);">No bills found</p>`;
        }
        
        // Build prescriptions HTML
        var prescriptionsHtml = '';
        if (prescriptions && prescriptions.length > 0) {
            prescriptionsHtml = `
                <table class="pdf-table-vp">
                    <thead><tr><th>Prescription #</th><th>Date</th><th>Doctor</th><th>Medication</th><th>Status</th></tr></thead>
                    <tbody>
                        ${prescriptions.map(function(p) {
                            return `<tr>
                                <td>${p.prescription_number || 'N/A'}</td>
                                <td>${new Date(p.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                <td>${p.doctor_name || 'N/A'}</td>
                                <td>${p.medication || 'N/A'}</td>
                                <td>${p.status || 'N/A'}</td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            prescriptionsHtml = `<p style="color:var(--vp-text-secondary);">No prescriptions found</p>`;
        }
        
        // Build lab tests HTML
        var labTestsHtml = '';
        if (labTests && labTests.length > 0) {
            labTestsHtml = `
                <table class="pdf-table-vp">
                    <thead><tr><th>Test Name</th><th>Date</th><th>Doctor</th><th>Status</th><th>Results</th></tr></thead>
                    <tbody>
                        ${labTests.map(function(lt) {
                            return `<tr>
                                <td>${lt.test_name || 'N/A'}</td>
                                <td>${new Date(lt.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                <td>${lt.doctor_name || 'N/A'}</td>
                                <td>${lt.status || 'N/A'}</td>
                                <td>${lt.results ? '✅ Available' : '⏳ Pending'}</td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            labTestsHtml = `<p style="color:var(--vp-text-secondary);">No lab tests found</p>`;
        }
        
        // Build procedures HTML
        var proceduresHtml = '';
        if (procedures && procedures.length > 0) {
            proceduresHtml = `
                <table class="pdf-table-vp">
                    <thead><tr><th>Procedure</th><th>Date</th><th>Qty</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                        ${procedures.map(function(p) {
                            return `<tr>
                                <td>${p.item_name || 'N/A'}</td>
                                <td>${new Date(p.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                <td>${p.quantity || 1}</td>
                                <td>TSh ${Number(p.total_price || 0).toLocaleString()}</td>
                                <td>${p.status || 'N/A'}</td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            proceduresHtml = `<p style="color:var(--vp-text-secondary);">No procedures found</p>`;
        }
        
        // Build tools HTML
        var toolsHtml = '';
        if (tools && tools.length > 0) {
            toolsHtml = `
                <table class="pdf-table-vp">
                    <thead><tr><th>Tool Name</th><th>Date</th><th>Qty</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                        ${tools.map(function(t) {
                            return `<tr>
                                <td>${t.item_name || 'N/A'}</td>
                                <td>${new Date(t.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                <td>${t.quantity || 1}</td>
                                <td>TSh ${Number(t.total_price || 0).toLocaleString()}</td>
                                <td>${t.status || 'N/A'}</td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            `;
        } else {
            toolsHtml = `<p style="color:var(--vp-text-secondary);">No tools found</p>`;
        }
        
        // Build symptoms HTML
        var symptomsHtml = '';
        if (activeVisit) {
            symptomsHtml = `
                <div class="pdf-grid-2-vp">
                    <div class="pdf-row-vp"><span class="pdf-label-vp">Symptoms</span><span class="pdf-value-vp">${activeVisit.symptoms || 'N/A'}</span></div>
                    <div class="pdf-row-vp"><span class="pdf-label-vp">Complaint</span><span class="pdf-value-vp">${activeVisit.complaint || 'N/A'}</span></div>
                    ${activeVisit.notes ? `<div class="pdf-row-vp" style="grid-column: 1 / -1;"><span class="pdf-label-vp">Notes</span><span class="pdf-value-vp">${activeVisit.notes}</span></div>` : ''}
                </div>
            `;
        } else {
            symptomsHtml = `<p style="color:var(--vp-text-secondary);">No active visit symptoms</p>`;
        }
        
        // Build diagnosis HTML
        var diagnosisHtml = '';
        if (activeVisit && (activeVisit.diagnosis || activeVisit.treatment)) {
            diagnosisHtml = `
                <div class="pdf-grid-2-vp">
                    <div class="pdf-row-vp"><span class="pdf-label-vp">Diagnosis</span><span class="pdf-value-vp">${activeVisit.diagnosis || 'N/A'}</span></div>
                    <div class="pdf-row-vp"><span class="pdf-label-vp">Treatment</span><span class="pdf-value-vp">${activeVisit.treatment || 'N/A'}</span></div>
                </div>
            `;
        } else {
            diagnosisHtml = `<p style="color:var(--vp-text-secondary);">No diagnosis recorded</p>`;
        }
        
        var html = `
            <div class="pdf-header-vp">
                <div class="pdf-logo-vp">
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" onerror="this.style.display='none'">
                    <span class="clinic-name-vp">BRAICK DISPENSARY</span>
                </div>
                <div class="clinic-sub-vp">Quality Healthcare Services • ${patientData.branch_name}</div>
                <div class="doc-title-vp">📋 Patient Medical Record</div>
                <div style="font-size:0.75rem;color:var(--vp-text-secondary);margin-top:4px;">
                    Report Generated: ${reportDate} • ${reportTime}
                </div>
            </div>
            
            <div class="section-title-vp">👤 Personal Information</div>
            <div class="pdf-grid-2-vp">
                <div class="pdf-row-vp"><span class="pdf-label-vp">Full Name</span><span class="pdf-value-vp"><strong>${patientData.full_name}</strong></span></div>
                <div class="pdf-row-vp"><span class="pdf-label-vp">Patient ID</span><span class="pdf-value-vp">${patientData.patient_id}</span></div>
                <div class="pdf-row-vp"><span class="pdf-label-vp">Gender</span><span class="pdf-value-vp">${patientData.gender}</span></div>
                <div class="pdf-row-vp"><span class="pdf-label-vp">Marital Status</span><span class="pdf-value-vp">${patientData.marital_status}</span></div>
                <div class="pdf-row-vp"><span class="pdf-label-vp">Date of Birth</span><span class="pdf-value-vp">${patientData.date_of_birth} (${patientData.age} years)</span></div>
                <div class="pdf-row-vp"><span class="pdf-label-vp">Phone</span><span class="pdf-value-vp">${patientData.phone}</span></div>
                <div class="pdf-row-vp"><span class="pdf-label-vp">Email</span><span class="pdf-value-vp">${patientData.email}</span></div>
                <div class="pdf-row-vp"><span class="pdf-label-vp">Blood Group</span><span class="pdf-value-vp">${patientData.blood_group}</span></div>
                <div class="pdf-row-vp" style="grid-column: 1 / -1;"><span class="pdf-label-vp">Address</span><span class="pdf-value-vp">${patientData.address}</span></div>
                <div class="pdf-row-vp"><span class="pdf-label-vp">Registered</span><span class="pdf-value-vp">${patientData.created_at} by ${patientData.created_by}</span></div>
                <div class="pdf-row-vp"><span class="pdf-label-vp">Branch</span><span class="pdf-value-vp">${patientData.branch_name}</span></div>
            </div>
            
            <div class="section-title-vp">👨‍⚕️ Assigned Doctor & Active Visit</div>
            <div class="pdf-grid-2-vp">
                <div class="pdf-row-vp"><span class="pdf-label-vp">Assigned Doctor</span><span class="pdf-value-vp">${patientData.assigned_doctor}</span></div>
                <div class="pdf-row-vp"><span class="pdf-label-vp">Active Visit</span><span class="pdf-value-vp">${activeVisit ? activeVisit.visit_number + ' (' + activeVisit.status + ')' : 'No active visit'}</span></div>
            </div>
            
            <div class="section-title-vp">❤️ Vital Signs (7 Signs - including SpO2)</div>
            ${vitalsHtml}
            
            <div class="section-title-vp">📋 Visit History (Last 10)</div>
            ${visitHtml}
            
            <div class="section-title-vp">🩺 Symptoms & Complaint</div>
            ${symptomsHtml}
            
            <div class="section-title-vp">🧪 Lab Tests (Last 10)</div>
            ${labTestsHtml}
            
            <div class="section-title-vp">📋 Diagnosis & Treatment</div>
            ${diagnosisHtml}
            
            <div class="section-title-vp">🏥 Medical Information</div>
            <div class="pdf-grid-2-vp">
                <div class="pdf-row-vp"><span class="pdf-label-vp">Blood Group</span><span class="pdf-value-vp">${patientData.blood_group}</span></div>
                <div class="pdf-row-vp"><span class="pdf-label-vp">Emergency Contact</span><span class="pdf-value-vp">${patientData.emergency_contact}</span></div>
                <div class="pdf-row-vp" style="grid-column: 1 / -1;"><span class="pdf-label-vp">Allergies</span><span class="pdf-value-vp">${patientData.allergies}</span></div>
            </div>
            
            <div class="section-title-vp">💊 Prescriptions (Last 10)</div>
            ${prescriptionsHtml}
            
            <div class="section-title-vp">💉 Procedures (Last 10)</div>
            ${proceduresHtml}
            
            <div class="section-title-vp">🔧 Tools / Equipment (Last 10)</div>
            ${toolsHtml}
            
            <div class="section-title-vp">💰 Bills (Last 10)</div>
            ${billsHtml}
            
            <div class="pdf-footer-vp">
                <div class="footer-stamp-vp">
                    <div class="footer-left-vp">
                        <span>Technician: _________________</span>
                        <span style="margin-left:20px;">Date: ${reportDate}</span>
                    </div>
                    <div class="stamp-box-vp">
                        <div class="stamp-title-vp">Official Stamp</div>
                        <div class="stamp-name-vp">BRAICK DISPENSARY</div>
                        <div class="stamp-line-vp">Approved By: _________________</div>
                        <div class="stamp-date-vp">Date: ${reportDate}</div>
                    </div>
                </div>
                <div class="footer-bottom-vp">
                    <span class="footer-brand-vp">Braick Dispensary</span> • 
                    <span style="font-weight:600;color:#DC2626;">❤️ TUNAJARI AFYA YAKO</span> • 
                    Generated on ${reportDate} at ${reportTime} • 
                    All rights reserved
                </div>
            </div>
        `;
        
        content.innerHTML = html;
        modal.classList.add('active');
    }
    
    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }
    
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [10, 10, 10, 10],
            filename: 'Patient_<?= htmlspecialchars($patient['full_name'] ?? 'patient') ?>_<?= $patient['id'] ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: 'avoid-all' }
        };
        
        html2pdf().set(opt).from(element).save();
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closePDFModal();
    });

    // ================================================================
    // CLICK OUTSIDE TO CLOSE PDF MODAL
    // ================================================================
    document.getElementById('pdfModal')?.addEventListener('click', function(e) {
        if (e.target === this) closePDFModal();
    });

    // ================================================================
    // DOWNLOAD BUTTON
    // ================================================================
    document.getElementById('downloadPdfBtn')?.addEventListener('click', function() {
        showToast('📄 PDF Download', 'Downloading patient PDF...', 'info');
    });

    console.log('%c👤 Braick - View Patient (7 Vital Signs with SpO2)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c🆔 ID: <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c❤️ 7 Vital Signs: Temp, BP, Pulse, SpO2, Weight, Height, BMI', 'font-size:13px; color:#DC2626;');
    console.log('%c🫁 SpO2 (Oxygen Saturation): Normal 95-100%', 'font-size:13px; color:#0EA5E9;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>