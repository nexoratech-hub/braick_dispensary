<?php
// ================================================================
// FILE: frontend/pages/admin/reception_dashboard.php
// RECEPTION DASHBOARD - BLUE THEME WITH AJAX AUTO UPDATE
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Blue theme + full dark mode support via --page-* variables
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

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

// VERIFY USER
$stmt = $db->prepare("SELECT id, full_name, role, status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] !== 'active') {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET BRANCH ID
// ================================================================
$branch_id = isset($_GET['id']) ? (int)$_GET['id'] : ($_SESSION['branch_id'] ?? 1);

function branchExists($db, $branch_id) {
    try {
        $stmt = $db->prepare("SELECT id FROM branches WHERE id = ? AND status = 'active'");
        $stmt->execute([$branch_id]);
        return $stmt->fetch() ? true : false;
    } catch (Exception $e) {
        return false;
    }
}

if (!branchExists($db, $branch_id)) {
    $branch_id = 1;
}

// ================================================================
// FETCH STATISTICS
// ================================================================
$total_patients = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM patients WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $total_patients = $stmt->fetchColumn();
} catch (Exception $e) { $total_patients = 0; }

$today_patients = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM patients WHERE branch_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$branch_id]);
    $today_patients = $stmt->fetchColumn();
} catch (Exception $e) { $today_patients = 0; }

$today_visits = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM visits WHERE branch_id = ? AND DATE(created_at) = CURDATE() AND status != 'cancelled'");
    $stmt->execute([$branch_id]);
    $today_visits = $stmt->fetchColumn();
} catch (Exception $e) { $today_visits = 0; }

$today_appointments = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE branch_id = ? AND DATE(appointment_date) = CURDATE() AND status != 'cancelled'");
    $stmt->execute([$branch_id]);
    $today_appointments = $stmt->fetchColumn();
} catch (Exception $e) { $today_appointments = 0; }

$pending_appointments = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE branch_id = ? AND status = 'scheduled'");
    $stmt->execute([$branch_id]);
    $pending_appointments = $stmt->fetchColumn();
} catch (Exception $e) { $pending_appointments = 0; }

$today_revenue = 0;
try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE branch_id = ? AND status = 'paid' AND DATE(created_at) = CURDATE()");
    $stmt->execute([$branch_id]);
    $today_revenue = $stmt->fetchColumn();
} catch (Exception $e) { $today_revenue = 0; }

// ================================================================
// FETCH ALL PATIENTS
// ================================================================
$all_patients = [];
try {
    $stmt = $db->prepare("
        SELECT p.id, p.patient_id, p.full_name, p.gender, p.phone, p.email, p.created_at,
            p.assigned_doctor_id, u.full_name as assigned_doctor_name,
            (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND status != 'cancelled') as total_visits,
            (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND status != 'cancelled') as total_appointments
        FROM patients p
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        WHERE p.branch_id = ?
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$branch_id]);
    $all_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $all_patients = []; }

// ================================================================
// FETCH ASSIGNED PATIENTS
// ================================================================
$assigned_patients = [];
try {
    $stmt = $db->prepare("
        SELECT p.id, p.patient_id, p.full_name, p.gender, p.phone, p.email, p.created_at,
            p.assigned_doctor_id, u.full_name as assigned_doctor_name, u.specialty as doctor_specialty,
            (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND status != 'cancelled') as total_visits,
            (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND status != 'cancelled') as total_appointments
        FROM patients p
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        WHERE p.branch_id = ? AND p.assigned_doctor_id IS NOT NULL
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$branch_id]);
    $assigned_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $assigned_patients = []; }

// ================================================================
// FETCH RECENT ACTIVITIES
// ================================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("SELECT * FROM activity_logs WHERE branch_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$branch_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_activities = []; }

// ================================================================
// FETCH TODAY'S APPOINTMENTS
// ================================================================
$todays_appointments_list = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name, p.phone as patient_phone, u.full_name as doctor_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.branch_id = ? AND DATE(a.appointment_date) = CURDATE() AND a.status != 'cancelled'
        ORDER BY a.appointment_date ASC
        LIMIT 20
    ");
    $stmt->execute([$branch_id]);
    $todays_appointments_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $todays_appointments_list = []; }

// ================================================================
// FETCH ONLINE DOCTORS
// ================================================================
$online_doctors = [];
try {
    $stmt = $db->prepare("
        SELECT id, full_name, specialty, is_online, last_online 
        FROM users 
        WHERE branch_id = ? AND role = 'doctor' AND status = 'active'
        ORDER BY is_online DESC, full_name ASC
    ");
    $stmt->execute([$branch_id]);
    $online_doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $online_doctors = []; }

// ================================================================
// BRANCH NAME
// ================================================================
$branch_name = 'Unknown';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $result = $stmt->fetch();
    if ($result) $branch_name = $result['name'];
} catch (Exception $e) { $branch_name = 'Unknown'; }

// ================================================================
// STATUS HELPERS
// ================================================================
function getStatusBadge($status) {
    $status = $status ?? 'unknown';
    $classes = [
        'active' => 'success', 'inactive' => 'danger', 'pending' => 'warning',
        'dispensed' => 'success', 'confirmed' => 'info', 'cancelled' => 'danger',
        'paid' => 'success', 'partial' => 'warning', 'scheduled' => 'info',
        'completed' => 'success', 'online' => 'success', 'offline' => 'danger',
        'new' => 'info', 'follow-up' => 'warning', 'emergency' => 'danger',
        'accepted' => 'success', 'rejected' => 'danger', 'unknown' => 'secondary'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $status = $status ?? 'unknown';
    $icons = [
        'active' => 'fa-check-circle', 'inactive' => 'fa-times-circle',
        'pending' => 'fa-clock', 'dispensed' => 'fa-check-circle',
        'confirmed' => 'fa-check-double', 'cancelled' => 'fa-times-circle',
        'paid' => 'fa-check-circle', 'partial' => 'fa-clock',
        'scheduled' => 'fa-calendar-check', 'completed' => 'fa-check-circle',
        'online' => 'fa-circle', 'offline' => 'fa-circle',
        'new' => 'fa-user-plus', 'follow-up' => 'fa-user-check',
        'emergency' => 'fa-ambulance', 'accepted' => 'fa-check-circle',
        'rejected' => 'fa-times-circle', 'unknown' => 'fa-circle'
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
    .page-header-recep {
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

    .page-header-recep::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-recep .page-title-recep {
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

    .page-header-recep .page-subtitle-recep {
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

    .page-header-recep .role-badge-display {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .page-header-recep .header-badge-recep {
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
        transition: all 0.3s ease;
    }

    .page-header-recep .header-badge-recep:hover {
        background: rgba(255,255,255,0.2);
        transform: translateY(-1px);
    }

    /* STATS CARDS */
    .stats-grid-recep {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card-recep {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 12px;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(11, 94, 215, 0.25);
        text-decoration: none;
        color: white;
        position: relative;
        overflow: hidden;
        min-height: 90px;
    }

    .stat-card-recep::before {
        content: '';
        position: absolute;
        top: -50%; right: -20%;
        width: 150px; height: 150px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .stat-card-recep:hover {
        transform: translateY(-6px);
        box-shadow: 0 8px 30px rgba(11, 94, 215, 0.4);
    }

    .stat-card-recep .stat-icon-recep {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.1);
        position: relative;
        z-index: 1;
    }

    .stat-card-recep .stat-content-recep {
        flex: 1;
        position: relative;
        z-index: 1;
    }

    .stat-card-recep .stat-label-recep {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.8);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
    }

    .stat-card-recep .stat-value-recep {
        font-size: 1.6rem;
        font-weight: 700;
        color: white;
        margin: 0;
        line-height: 1.2;
    }

    .stat-card-recep .stat-arrow-recep {
        opacity: 0;
        transition: all 0.3s ease;
        color: rgba(255,255,255,0.6);
        font-size: 0.8rem;
        flex-shrink: 0;
        position: relative;
        z-index: 1;
    }

    .stat-card-recep:hover .stat-arrow-recep {
        opacity: 1;
        transform: translateX(4px);
    }

    /* BADGES */
    .badge-recep {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        color: white;
        letter-spacing: 0.02em;
    }

    .badge-success-recep { background: #059669; }
    .badge-danger-recep { background: #DC2626; }
    .badge-warning-recep { background: #D97706; color: #1E293B; }
    .badge-info-recep { background: #0B5ED7; }
    .badge-secondary-recep { background: #64748B; }
    .badge-purple-recep { background: #7C3AED; }

    [data-theme="dark"] .badge-warning-recep { color: #1E293B; }

    /* DOCTOR STATUS CARD */
    .doctor-grid-recep {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 12px;
    }

    .doctor-card-recep {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: 12px;
        border: 2px solid var(--page-border, #E2E8F0);
        background: var(--page-bg-card, #FFFFFF);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .doctor-card-recep:hover {
        border-color: var(--page-primary, #0B5ED7);
        transform: translateY(-2px);
        box-shadow: var(--page-shadow-md);
    }

    .doctor-card-recep .doctor-avatar-recep {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: var(--page-primary-bg, #E8F0FE);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--page-primary, #0B5ED7);
        font-weight: 700;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .doctor-card-recep .doctor-info-recep {
        flex: 1;
        min-width: 0;
    }

    .doctor-card-recep .doctor-name-recep {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--page-text-primary, #1E293B);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .doctor-card-recep .doctor-specialty-recep {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }

    .doctor-card-recep .status-container-recep {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
    }

    .doctor-card-recep .status-dot-recep {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .doctor-card-recep .status-dot-recep.online {
        background: #059669;
        box-shadow: 0 0 12px rgba(5, 150, 105, 0.5);
        animation: pulse-dot-online-recep 2s infinite;
    }

    .doctor-card-recep .status-dot-recep.offline {
        background: #DC2626;
    }

    @keyframes pulse-dot-online-recep {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.3); opacity: 0.7; }
    }

    .doctor-card-recep .status-label-recep {
        font-size: 0.55rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .doctor-card-recep .status-label-recep.online { color: #059669; }
    .doctor-card-recep .status-label-recep.offline { color: #DC2626; }

    .doctor-card-recep.status-updated {
        animation: flash-update-recep 0.6s ease;
    }

    @keyframes flash-update-recep {
        0% { background: #E8F0FE; border-color: #0B5ED7; }
        100% { background: var(--page-bg-card, #FFFFFF); border-color: var(--page-border, #E2E8F0); }
    }

    /* TABLES */
    .table-container-recep {
        overflow-x: auto;
        max-height: 400px;
        overflow-y: auto;
    }

    .data-table-recep {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8rem;
    }

    .data-table-recep thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        font-weight: 600;
        padding: 10px 12px;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .data-table-recep thead th:first-child { border-radius: 8px 0 0 0; }
    .data-table-recep thead th:last-child { border-radius: 0 8px 0 0; }

    .data-table-recep td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
    }

    .data-table-recep tbody tr:hover td {
        background: var(--page-hover, #F8FAFC);
    }

    [data-theme="dark"] .data-table-recep tbody tr:hover td {
        background: #1E293B;
    }

    .data-table-recep tbody tr:last-child td { border-bottom: none; }

    /* CARD */
    .card-recep {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm);
        margin-bottom: 24px;
    }

    .card-recep:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: var(--page-shadow-md);
    }

    .card-header-recep {
        padding: 16px 24px;
        background: var(--page-hover, #F8FAFC);
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    [data-theme="dark"] .card-header-recep {
        background: #0F172A;
    }

    .card-title-recep {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
        display: flex;
        align-items: center;
    }

    .card-title-recep i {
        margin-right: 8px;
    }

    /* BUTTONS */
    .btn-recep {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 22px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
    }

    .btn-recep:hover {
        transform: translateY(-2px);
        box-shadow: var(--page-shadow-md);
    }

    .btn-primary-recep {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
    }

    .btn-primary-recep:hover {
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.35);
        color: white;
    }

    .btn-success-recep {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
    }

    .btn-success-recep:hover {
        background: linear-gradient(135deg, #047857, #065F46);
        color: white;
    }

    .btn-warning-recep {
        background: linear-gradient(135deg, #D97706, #B45309);
        color: white;
    }

    .btn-warning-recep:hover {
        background: linear-gradient(135deg, #B45309, #92400E);
        color: white;
    }

    .btn-outline-recep {
        background: transparent;
        color: var(--page-text-primary, #1E293B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    .btn-outline-recep:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
    }

    .btn-sm-recep {
        padding: 5px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
    }

    /* EMPTY STATE */
    .empty-state-recep {
        text-align: center;
        padding: 40px 20px;
        color: var(--page-text-secondary, #64748B);
    }

    .empty-state-recep i {
        font-size: 2.5rem;
        color: var(--page-text-muted, #94A3B8);
        margin-bottom: 12px;
        display: block;
    }

    .empty-state-recep h4 {
        font-size: 1rem;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 4px;
    }

    /* ACTIVITY ITEM */
    .activity-item-recep {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        transition: all 0.3s;
    }

    .activity-item-recep:hover {
        background: var(--page-hover, #F8FAFC);
    }

    [data-theme="dark"] .activity-item-recep:hover {
        background: #0F172A;
    }

    .activity-icon-recep {
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

    /* TOAST */
    .status-toast-recep {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 9999;
        padding: 12px 20px;
        border-radius: 12px;
        background: var(--page-bg-card, #FFFFFF);
        border: 2px solid var(--page-border, #E2E8F0);
        box-shadow: var(--page-shadow-lg);
        display: none;
        align-items: center;
        gap: 12px;
        min-width: 250px;
        animation: slideUp-recep 0.4s ease;
    }

    .status-toast-recep.show { display: flex; }

    .status-toast-recep .toast-icon-recep {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        flex-shrink: 0;
    }

    .status-toast-recep .toast-icon-recep.online { background: #D1FAE5; color: #059669; }
    .status-toast-recep .toast-icon-recep.offline { background: #FEE2E2; color: #DC2626; }

    .status-toast-recep .toast-title-recep {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--page-text-primary, #1E293B);
    }

    .status-toast-recep .toast-message-recep {
        font-size: 0.75rem;
        color: var(--page-text-secondary, #64748B);
    }

    @keyframes slideUp-recep {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* FOOTER */
    .footer-recep {
        padding: 14px 0;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-recep .footer-brand-recep {
        color: #0B5ED7;
        font-weight: 500;
    }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .doctor-grid-recep { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
        .stats-grid-recep { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-recep { padding: 16px 18px; }
        .page-header-recep .page-title-recep { font-size: 1.3rem; }
        .stats-grid-recep { grid-template-columns: 1fr 1fr; }
        .doctor-grid-recep { grid-template-columns: 1fr; }
    }

    @media (max-width: 480px) {
        .stats-grid-recep { grid-template-columns: 1fr; }
        .page-header-recep { flex-direction: column; align-items: flex-start !important; }
        .stat-card-recep { padding: 14px 16px; min-height: 70px; }
        .stat-card-recep .stat-value-recep { font-size: 1.2rem; }
        .stat-card-recep .stat-icon-recep { width: 40px; height: 40px; font-size: 1rem; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-recep">
        <div>
            <h1 class="page-title-recep">
                <i class="fas fa-clipboard-list"></i>
                Reception Dashboard
                <span class="role-badge-display">RECEPTION</span>
            </h1>
            <p class="page-subtitle-recep">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($branch_name) ?></strong>
                <span class="header-badge-recep">
                    <i class="fas fa-calendar-day"></i> <?= date('M d, Y') ?>
                </span>
                <span class="header-badge-recep" id="onlineCountBadge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-circle" style="color:#34D399;"></i>
                    <span id="onlineCount"><?= count(array_filter($online_doctors, function($d) { return ($d['is_online'] ?? 0) == 1; })) ?></span> Online
                </span>
                <span class="header-badge-recep" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?>
                </span>
            </p>
        </div>
    </div>

    <!-- STATS CARDS -->
    <div class="stats-grid-recep">
        <div class="stat-card-recep">
            <div class="stat-icon-recep"><i class="fas fa-users"></i></div>
            <div class="stat-content-recep">
                <p class="stat-label-recep">Total Patients</p>
                <p class="stat-value-recep"><?= number_format($total_patients) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-recep"></i>
        </div>
        
        <div class="stat-card-recep">
            <div class="stat-icon-recep"><i class="fas fa-user-plus"></i></div>
            <div class="stat-content-recep">
                <p class="stat-label-recep">Today's Patients</p>
                <p class="stat-value-recep"><?= number_format($today_patients) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-recep"></i>
        </div>
        
        <div class="stat-card-recep">
            <div class="stat-icon-recep"><i class="fas fa-hospital-user"></i></div>
            <div class="stat-content-recep">
                <p class="stat-label-recep">Today's Visits</p>
                <p class="stat-value-recep"><?= number_format($today_visits) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-recep"></i>
        </div>
        
        <div class="stat-card-recep">
            <div class="stat-icon-recep"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-content-recep">
                <p class="stat-label-recep">Today's Appointments</p>
                <p class="stat-value-recep"><?= number_format($today_appointments) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-recep"></i>
        </div>
        
        <div class="stat-card-recep">
            <div class="stat-icon-recep"><i class="fas fa-clock"></i></div>
            <div class="stat-content-recep">
                <p class="stat-label-recep">Pending Appointments</p>
                <p class="stat-value-recep"><?= number_format($pending_appointments) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-recep"></i>
        </div>
        
        <div class="stat-card-recep">
            <div class="stat-icon-recep"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-content-recep">
                <p class="stat-label-recep">Today's Revenue</p>
                <p class="stat-value-recep">TSh <?= number_format($today_revenue, 0) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-recep"></i>
        </div>
    </div>

    <!-- ONLINE DOCTORS -->
    <div class="card-recep">
        <div class="card-header-recep">
            <h3 class="card-title-recep">
                <i class="fas fa-user-md" style="color:#0B5ED7;"></i>
                Online Doctors
                <span style="font-size:0.7rem;color:var(--page-text-secondary);margin-left:8px;" id="doctorCountLabel">
                    (<span id="onlineDoctorCount"><?= count(array_filter($online_doctors, function($d) { return ($d['is_online'] ?? 0) == 1; })) ?></span> online)
                </span>
            </h3>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <span style="font-size:0.7rem;color:var(--page-text-secondary);" id="lastUpdateTime">Last update: just now</span>
                <button onclick="fetchDoctorStatusRecep()" class="btn-recep btn-sm-recep btn-primary-recep">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i> Refresh
                </button>
                <a href="doctors.php?branch_id=<?= $branch_id ?>" class="btn-recep btn-sm-recep btn-outline-recep">View All</a>
            </div>
        </div>
        <div style="padding:16px;">
            <div class="doctor-grid-recep" id="doctorGrid">
                <?php if (count($online_doctors) > 0): ?>
                    <?php foreach ($online_doctors as $doctor): 
                        $is_online = ($doctor['is_online'] ?? 0) == 1;
                        $initial = strtoupper(substr($doctor['full_name'] ?? 'D', 0, 1));
                    ?>
                        <div class="doctor-card-recep" data-doctor-id="<?= $doctor['id'] ?>" data-doctor-name="<?= htmlspecialchars($doctor['full_name'] ?? 'Unknown') ?>">
                            <div class="doctor-avatar-recep"><?= $initial ?></div>
                            <div class="doctor-info-recep">
                                <div class="doctor-name-recep"><?= htmlspecialchars($doctor['full_name'] ?? 'Unknown') ?></div>
                                <div class="doctor-specialty-recep"><?= htmlspecialchars($doctor['specialty'] ?? 'General') ?></div>
                            </div>
                            <div class="status-container-recep">
                                <div class="status-dot-recep <?= $is_online ? 'online' : 'offline' ?>"></div>
                                <div class="status-label-recep <?= $is_online ? 'online' : 'offline' ?>">
                                    <?= $is_online ? 'Online' : 'Offline' ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state-recep" style="grid-column: 1 / -1;">
                        <i class="fas fa-user-md"></i>
                        <h4>No Doctors Found</h4>
                        <p>No doctors are available in this branch.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TODAY'S APPOINTMENTS -->
    <div class="card-recep">
        <div class="card-header-recep">
            <h3 class="card-title-recep">
                <i class="fas fa-calendar-day" style="color:#7C3AED;"></i>
                Today's Appointments
            </h3>
            <a href="appointments.php?branch_id=<?= $branch_id ?>" class="btn-recep btn-sm-recep btn-outline-recep">View All</a>
        </div>
        <div style="overflow-x:auto;">
            <?php if (count($todays_appointments_list) > 0): ?>
                <table class="data-table-recep">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todays_appointments_list as $appointment): 
                            $status = $appointment['status'] ?? 'scheduled';
                        ?>
                            <tr>
                                <td style="font-weight:500;"><?= htmlspecialchars($appointment['patient_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($appointment['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= date('h:i A', strtotime($appointment['appointment_date'] ?? 'now')) ?></td>
                                <td>
                                    <span class="badge-recep badge-<?= getStatusBadge($appointment['visit_type'] ?? 'new') ?>-recep" style="font-size:0.6rem;padding:2px 10px;">
                                        <?= ucfirst($appointment['visit_type'] ?? 'New') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-recep badge-<?= getStatusBadge($status) ?>-recep" style="font-size:0.6rem;padding:2px 10px;">
                                        <i class="fas <?= getStatusIcon($status) ?>"></i>
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_appointment.php?id=<?= $appointment['id'] ?>&branch_id=<?= $branch_id ?>" class="btn-recep btn-sm-recep btn-primary-recep">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state-recep">
                    <i class="fas fa-calendar-day"></i>
                    <h4>No Appointments Today</h4>
                    <p>There are no appointments scheduled for today.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ALL PATIENTS TABLE -->
    <div class="card-recep">
        <div class="card-header-recep">
            <h3 class="card-title-recep">
                <i class="fas fa-users" style="color:#0B5ED7;"></i>
                All Patients
                <span style="font-size:0.7rem;color:var(--page-text-secondary);margin-left:8px;">(<?= count($all_patients) ?> patients)</span>
            </h3>
            <div style="display:flex;gap:8px;">
                <a href="patients.php?branch_id=<?= $branch_id ?>" class="btn-recep btn-sm-recep btn-outline-recep">View All</a>
            </div>
        </div>
        <div class="table-container-recep">
            <?php if (count($all_patients) > 0): ?>
                <table class="data-table-recep">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Full Name</th>
                            <th>Gender</th>
                            <th>Phone</th>
                            <th>Assigned Doctor</th>
                            <th>Visits</th>
                            <th>Appointments</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_patients as $patient): ?>
                            <tr>
                                <td style="font-family:monospace;font-size:0.7rem;font-weight:600;"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                <td style="font-weight:500;"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-recep badge-<?= ($patient['gender'] ?? '') === 'Male' ? 'info' : (($patient['gender'] ?? '') === 'Female' ? 'purple' : 'secondary') ?>-recep" style="font-size:0.55rem;padding:2px 10px;">
                                        <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if (!empty($patient['assigned_doctor_name'])): ?>
                                        <span class="badge-recep badge-success-recep" style="font-size:0.55rem;padding:2px 10px;">
                                            <i class="fas fa-user-md"></i> <?= htmlspecialchars($patient['assigned_doctor_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-recep badge-warning-recep" style="font-size:0.55rem;padding:2px 10px;">
                                            <i class="fas fa-user-slash"></i> Unassigned
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($patient['total_visits'] ?? 0) ?></td>
                                <td><?= number_format($patient['total_appointments'] ?? 0) ?></td>
                                <td>
                                    <a href="view_patient.php?id=<?= $patient['id'] ?>&branch_id=<?= $branch_id ?>" class="btn-recep btn-sm-recep btn-primary-recep">View</a>
                                    <?php if (empty($patient['assigned_doctor_id'])): ?>
                                        <a href="assign_doctor.php?patient_id=<?= $patient['id'] ?>&branch_id=<?= $branch_id ?>" class="btn-recep btn-sm-recep btn-success-recep">Assign</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state-recep">
                    <i class="fas fa-users"></i>
                    <h4>No Patients Found</h4>
                    <p>No patients registered in this branch yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ASSIGNED PATIENTS TABLE -->
    <div class="card-recep">
        <div class="card-header-recep">
            <h3 class="card-title-recep">
                <i class="fas fa-user-check" style="color:#059669;"></i>
                Assigned Patients
                <span style="font-size:0.7rem;color:var(--page-text-secondary);margin-left:8px;">(<?= count($assigned_patients) ?> assigned)</span>
            </h3>
            <div style="display:flex;gap:8px;">
                <a href="assigned_patients.php?branch_id=<?= $branch_id ?>" class="btn-recep btn-sm-recep btn-outline-recep">View All</a>
            </div>
        </div>
        <div class="table-container-recep">
            <?php if (count($assigned_patients) > 0): ?>
                <table class="data-table-recep">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Full Name</th>
                            <th>Gender</th>
                            <th>Phone</th>
                            <th>Assigned Doctor</th>
                            <th>Specialty</th>
                            <th>Visits</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assigned_patients as $patient): ?>
                            <tr>
                                <td style="font-family:monospace;font-size:0.7rem;font-weight:600;"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                <td style="font-weight:500;"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-recep badge-<?= ($patient['gender'] ?? '') === 'Male' ? 'info' : (($patient['gender'] ?? '') === 'Female' ? 'purple' : 'secondary') ?>-recep" style="font-size:0.55rem;padding:2px 10px;">
                                        <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-recep badge-success-recep" style="font-size:0.55rem;padding:2px 10px;">
                                        <i class="fas fa-user-md"></i> <?= htmlspecialchars($patient['assigned_doctor_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($patient['doctor_specialty'] ?? 'General') ?></td>
                                <td><?= number_format($patient['total_visits'] ?? 0) ?></td>
                                <td>
                                    <a href="view_patient.php?id=<?= $patient['id'] ?>&branch_id=<?= $branch_id ?>" class="btn-recep btn-sm-recep btn-primary-recep">View</a>
                                    <a href="reassign_doctor.php?patient_id=<?= $patient['id'] ?>&branch_id=<?= $branch_id ?>" class="btn-recep btn-sm-recep btn-warning-recep">Reassign</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state-recep">
                    <i class="fas fa-user-check"></i>
                    <h4>No Assigned Patients</h4>
                    <p>No patients have been assigned to doctors in this branch yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RECENT ACTIVITIES -->
    <div class="card-recep">
        <div class="card-header-recep">
            <h3 class="card-title-recep">
                <i class="fas fa-clock" style="color:#64748B;"></i>
                Recent Activities
            </h3>
            <a href="activity_logs.php?branch_id=<?= $branch_id ?>" class="btn-recep btn-sm-recep btn-outline-recep">View All</a>
        </div>
        <div style="max-height:240px;overflow-y:auto;">
            <?php if (count($recent_activities) > 0): ?>
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item-recep">
                        <div class="activity-icon-recep">
                            <i class="fas fa-circle" style="font-size:6px;"></i>
                        </div>
                        <div>
                            <p style="font-weight:500;font-size:0.85rem;color:var(--page-text-primary);margin:0;"><?= htmlspecialchars($activity['action'] ?? 'Action') ?></p>
                            <p style="font-size:0.75rem;color:var(--page-text-secondary);margin:2px 0;"><?= htmlspecialchars($activity['details'] ?? '') ?></p>
                            <p style="font-size:0.65rem;color:var(--page-text-muted);margin:0;">
                                <?= isset($activity['created_at']) ? date('M d, Y h:i A', strtotime($activity['created_at'])) : 'Just now' ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state-recep">
                    <i class="fas fa-clock"></i>
                    <h4>No Activities</h4>
                    <p>No recent activities found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TOAST -->
    <div class="status-toast-recep" id="statusToast">
        <div class="toast-icon-recep" id="toastIcon">
            <i class="fas fa-circle"></i>
        </div>
        <div style="flex:1;">
            <div class="toast-title-recep" id="toastTitle">Doctor Status Changed</div>
            <div class="toast-message-recep" id="toastMessage">Dr. John Mushi is now online</div>
        </div>
        <button onclick="hideToastRecep()" style="background:none;border:none;color:var(--page-text-secondary);cursor:pointer;font-size:1.2rem;padding:4px 8px;border-radius:6px;transition:all 0.3s;">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- FOOTER -->
    <footer class="footer-recep">
        <p>
            <span class="footer-brand-recep">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Reception Dashboard - <?= htmlspecialchars($branch_name) ?>
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
    // FOOTER TIME ONLY
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
    // TOAST
    // ================================================================
    var toastTimeoutRecep = null;

    function showToastRecep(doctorName, status) {
        var toast = document.getElementById('statusToast');
        var icon = document.getElementById('toastIcon');
        var title = document.getElementById('toastTitle');
        var message = document.getElementById('toastMessage');
        
        if (!toast || !icon || !title || !message) return;
        
        var isOnline = status === 'online';
        
        icon.className = 'toast-icon-recep ' + (isOnline ? 'online' : 'offline');
        icon.innerHTML = '<i class="fas fa-' + (isOnline ? 'check-circle' : 'times-circle') + '"></i>';
        
        title.textContent = isOnline ? '🟢 Doctor Online' : '🔴 Doctor Offline';
        message.textContent = 'Dr. ' + doctorName + ' is now ' + (isOnline ? 'online' : 'offline');
        
        toast.classList.add('show');
        
        if (toastTimeoutRecep) clearTimeout(toastTimeoutRecep);
        toastTimeoutRecep = setTimeout(function() {
            toast.classList.remove('show');
        }, 5000);
    }

    function hideToastRecep() {
        var toast = document.getElementById('statusToast');
        if (toast) toast.classList.remove('show');
        if (toastTimeoutRecep) {
            clearTimeout(toastTimeoutRecep);
            toastTimeoutRecep = null;
        }
    }

    // ================================================================
    // AJAX - FETCH DOCTOR STATUS
    // ================================================================
    var autoUpdateIntervalRecep = null;
    var isUpdatingRecep = false;
    var previousDoctorStatusRecep = {};

    document.querySelectorAll('.doctor-card-recep').forEach(function(card) {
        var id = card.getAttribute('data-doctor-id');
        var dot = card.querySelector('.status-dot-recep');
        var isOnline = dot ? dot.classList.contains('online') : false;
        previousDoctorStatusRecep[id] = isOnline;
    });

    function fetchDoctorStatusRecep() {
        if (isUpdatingRecep) return;
        isUpdatingRecep = true;
        
        var refreshIcon = document.getElementById('refreshIcon');
        if (refreshIcon) refreshIcon.classList.add('fa-spin');
        
        var branchId = <?= $branch_id ?>;
        var timestamp = Date.now();
        
        fetch('../../backend/ajax/get_doctor_status.php?branch_id=' + branchId + '&t=' + timestamp, {
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        })
        .then(function(response) {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.doctors) {
                updateDoctorUIRecep(data.doctors);
                updateLastUpdateTimeRecep();
            }
        })
        .catch(function(error) {
            console.error('Error fetching doctor status:', error);
        })
        .finally(function() {
            isUpdatingRecep = false;
            if (refreshIcon) refreshIcon.classList.remove('fa-spin');
        });
    }

    function updateDoctorUIRecep(doctors) {
        var grid = document.getElementById('doctorGrid');
        if (!grid) return;
        
        var onlineCount = 0;
        var cards = grid.querySelectorAll('.doctor-card-recep');
        
        doctors.forEach(function(doctor, index) {
            var card = cards[index];
            if (card) {
                var doctorId = doctor.id.toString();
                var isOnline = doctor.is_online == 1;
                var previousStatus = previousDoctorStatusRecep[doctorId] || false;
                
                if (previousStatus !== isOnline) {
                    showToastRecep(doctor.full_name, isOnline ? 'online' : 'offline');
                    
                    card.classList.remove('status-updated');
                    void card.offsetWidth;
                    card.classList.add('status-updated');
                    
                    previousDoctorStatusRecep[doctorId] = isOnline;
                }
                
                var dot = card.querySelector('.status-dot-recep');
                var label = card.querySelector('.status-label-recep');
                
                if (dot) dot.className = 'status-dot-recep ' + (isOnline ? 'online' : 'offline');
                if (label) {
                    label.className = 'status-label-recep ' + (isOnline ? 'online' : 'offline');
                    label.textContent = isOnline ? 'Online' : 'Offline';
                }
                
                if (isOnline) onlineCount++;
            }
        });
        
        var onlineCountBadge = document.getElementById('onlineCount');
        if (onlineCountBadge) onlineCountBadge.textContent = onlineCount;
        
        var onlineDoctorCount = document.getElementById('onlineDoctorCount');
        if (onlineDoctorCount) onlineDoctorCount.textContent = onlineCount;
        
        var doctorCountLabel = document.getElementById('doctorCountLabel');
        if (doctorCountLabel) {
            doctorCountLabel.innerHTML = '(<span id="onlineDoctorCount">' + onlineCount + '</span> online)';
        }
    }

    function updateLastUpdateTimeRecep() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('lastUpdateTime');
        if (el) el.textContent = 'Last update: ' + timeStr;
    }

    // ================================================================
    // START AUTO UPDATE (every 5 seconds)
    // ================================================================
    function startAutoUpdateRecep() {
        setTimeout(fetchDoctorStatusRecep, 1000);
        
        if (autoUpdateIntervalRecep) clearInterval(autoUpdateIntervalRecep);
        autoUpdateIntervalRecep = setInterval(fetchDoctorStatusRecep, 5000);
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            if (autoUpdateIntervalRecep) {
                clearInterval(autoUpdateIntervalRecep);
                autoUpdateIntervalRecep = null;
            }
        } else {
            startAutoUpdateRecep();
        }
    });

    startAutoUpdateRecep();

    console.log('%c🏥 Braick - Reception Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ NO duplicate header JavaScript', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c📊 Total Patients: <?= number_format($total_patients) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔄 Auto-update doctor status every 5s', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>