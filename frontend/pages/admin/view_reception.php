<?php
// ================================================================
// FILE: frontend/pages/admin/view_reception.php
// ADMIN - VIEW RECEPTION BRANCH DETAILS
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Blue theme + full dark mode support via --page-* variables
// ✅ Audit role redirect included
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
$reception_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';

if ($reception_id <= 0) {
    header('Location: receptions.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH RECEPTION DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            b.*,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'reception' AND status = 'active') as active_receptionists,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'reception') as total_receptionists,
            (SELECT COUNT(*) FROM patients WHERE branch_id = b.id) as total_patients,
            (SELECT COUNT(*) FROM patients WHERE branch_id = b.id AND DATE(created_at) = CURDATE()) as today_patients,
            (SELECT COUNT(*) FROM visits WHERE branch_id = b.id AND status = 'pending') as pending_visits,
            (SELECT COUNT(*) FROM visits WHERE branch_id = b.id AND status = 'assigned') as assigned_visits,
            (SELECT COUNT(*) FROM visits WHERE branch_id = b.id AND status = 'with_doctor') as with_doctor_visits,
            (SELECT COUNT(*) FROM visits WHERE branch_id = b.id AND status = 'completed') as completed_visits,
            (SELECT COUNT(*) FROM visits WHERE branch_id = b.id AND status = 'cancelled') as cancelled_visits,
            (SELECT COUNT(*) FROM visits WHERE branch_id = b.id AND DATE(visit_date) = CURDATE()) as today_visits,
            (SELECT COUNT(*) FROM appointments WHERE branch_id = b.id AND status = 'scheduled') as scheduled_appointments,
            (SELECT COUNT(*) FROM appointments WHERE branch_id = b.id AND status = 'confirmed') as confirmed_appointments,
            (SELECT COUNT(*) FROM appointments WHERE branch_id = b.id AND status = 'completed') as completed_appointments,
            (SELECT COUNT(*) FROM appointments WHERE branch_id = b.id AND status = 'cancelled') as cancelled_appointments,
            (SELECT COUNT(*) FROM appointments WHERE branch_id = b.id AND DATE(appointment_date) = CURDATE()) as today_appointments,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND status = 'paid') as paid_bills,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND status = 'pending') as pending_bills,
            (SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE branch_id = b.id AND status = 'paid') as total_revenue
        FROM branches b
        WHERE b.id = ?
    ");
    $stmt->execute([$reception_id]);
    $reception = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching reception: " . $e->getMessage());
    header('Location: receptions.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

if (!$reception) {
    header('Location: receptions.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
    exit;
}

// ================================================================
// GET RECEPTIONISTS
// ================================================================
$receptionists = [];
try {
    $stmt = $db->prepare("
        SELECT id, full_name, email, phone, status, created_at, is_online, last_online
        FROM users 
        WHERE branch_id = ? AND role = 'reception'
        ORDER BY full_name
    ");
    $stmt->execute([$reception_id]);
    $receptionists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $receptionists = []; }

// ================================================================
// GET RECENT PATIENTS
// ================================================================
$recent_patients = [];
try {
    $stmt = $db->prepare("
        SELECT p.id, p.patient_id, p.full_name, p.phone, p.gender, p.created_at,
               u.full_name as registered_by
        FROM patients p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.branch_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$reception_id]);
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_patients = []; }

// ================================================================
// GET RECENT APPOINTMENTS
// ================================================================
$recent_appointments = [];
try {
    $stmt = $db->prepare("
        SELECT a.id, a.appointment_date, a.status, a.created_at,
               p.full_name as patient_name, u.full_name as doctor_name,
               a.visit_type, a.purpose
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.branch_id = ?
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$reception_id]);
    $recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_appointments = []; }

// ================================================================
// GET RECENT VISITS
// ================================================================
$recent_visits = [];
try {
    $stmt = $db->prepare("
        SELECT v.id, v.visit_number, v.visit_date, v.status, v.created_at,
               p.full_name as patient_name, u.full_name as doctor_name,
               v.visit_type
        FROM visits v
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.branch_id = ?
        ORDER BY v.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$reception_id]);
    $recent_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_visits = []; }

// ================================================================
// GET RECENT ACTIVITIES
// ================================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT al.*, u.full_name as user_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.branch_id = ?
        ORDER BY al.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$reception_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_activities = []; }

// ================================================================
// HELPERS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success', 'inactive' => 'danger',
        'pending' => 'warning', 'assigned' => 'info',
        'confirmed' => 'success', 'scheduled' => 'warning',
        'completed' => 'success', 'cancelled' => 'danger',
        'with_doctor' => 'info', 'paid' => 'success', 'partial' => 'warning'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'active' => 'fa-check-circle', 'inactive' => 'fa-times-circle',
        'pending' => 'fa-clock', 'assigned' => 'fa-user-check',
        'confirmed' => 'fa-check-double', 'scheduled' => 'fa-calendar-check',
        'completed' => 'fa-check-circle', 'cancelled' => 'fa-times-circle',
        'with_doctor' => 'fa-user-md', 'paid' => 'fa-check-circle', 'partial' => 'fa-clock'
    ];
    return $icons[$status] ?? 'fa-circle';
}

function getStatusLabel($status) {
    $labels = [
        'pending' => 'Pending', 'assigned' => 'Assigned',
        'confirmed' => 'Confirmed', 'scheduled' => 'Scheduled',
        'completed' => 'Completed', 'cancelled' => 'Cancelled',
        'with_doctor' => 'With Doctor', 'paid' => 'Paid', 'partial' => 'Partial',
        'active' => 'Active', 'inactive' => 'Inactive'
    ];
    return $labels[$status] ?? ucfirst($status);
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$total_employees = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_doctors = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_branches = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$pending_lab_tests = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
    $pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $pending_lab_tests = 0; }

$pending_prescriptions = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $pending_prescriptions = 0; }

// ================================================================
// ✅ SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS - TUMIA VARIABLES ZA HEADER (--page-*) -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE HEADER - BLUE GRADIENT
       ================================================================ */
    .page-header-recep {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
        border-radius: 18px;
        padding: 26px 34px;
        margin-bottom: 26px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-recep::before {
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

    .page-header-recep .page-title {
        color: white;
        font-size: 1.7rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-recep .page-subtitle {
        color: rgba(255,255,255,0.88);
        font-size: 0.9rem;
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
        backdrop-filter: blur(4px);
    }

    .page-header-recep .header-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.1);
        transition: all 0.3s ease;
    }

    .page-header-recep .header-badge:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-1px);
    }

    .page-header-recep .btn-outline-light {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: 12px;
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

    .page-header-recep .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       DETAIL CARD
       ================================================================ */
    .detail-card-recep {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        padding: 24px 28px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
        margin-bottom: 24px;
    }

    .detail-card-recep:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.08));
    }

    .detail-grid-recep {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 18px;
    }

    .detail-label-recep {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin: 0 0 4px 0;
    }

    .detail-label-recep i {
        margin-right: 4px;
    }

    .detail-value-recep {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
    }

    /* ================================================================
       STATS CARDS - BLUE BACKGROUND (8 cards, 3-col grid)
       ================================================================ */
    .stats-grid-recep {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card-recep {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 14px;
        padding: 18px 20px;
        border: 2px solid rgba(255,255,255,0.1);
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(11, 94, 215, 0.2);
        text-decoration: none;
        color: white;
        position: relative;
        overflow: hidden;
        min-height: 90px;
    }

    .stat-card-recep::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 150px;
        height: 150px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .stat-card-recep:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(11, 94, 215, 0.35);
        border-color: rgba(255,255,255,0.2);
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
        color: white;
        border: 1px solid rgba(255,255,255,0.1);
        position: relative;
        z-index: 1;
        transition: all 0.3s ease;
    }

    .stat-card-recep:hover .stat-icon-recep {
        transform: scale(1.05);
        background: rgba(255,255,255,0.25);
    }

    .stat-card-recep .stat-content-recep {
        flex: 1;
        position: relative;
        z-index: 1;
    }

    .stat-label-recep {
        font-size: 0.65rem;
        color: rgba(255,255,255,0.85);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
    }

    .stat-value-recep {
        font-size: 1.5rem;
        font-weight: 700;
        color: white;
        margin: 2px 0 0 0;
        line-height: 1.2;
    }

    .stat-sub-recep {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.75);
        margin-top: 2px;
    }

    .stat-arrow-recep {
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

    /* ================================================================
       CARD
       ================================================================ */
    .card-recep {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
        margin-bottom: 24px;
    }

    .card-recep:hover {
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.08));
    }

    .card-header-recep {
        padding: 14px 20px;
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
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .card-link-recep {
        font-size: 0.72rem;
        color: var(--page-primary, #0B5ED7);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s ease;
    }

    .card-link-recep:hover {
        text-decoration: underline;
    }

    /* ================================================================
       DATA TABLE
       ================================================================ */
    .data-table-recep {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.78rem;
    }

    .data-table-recep thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        font-weight: 600;
        padding: 10px 12px;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: none;
        white-space: nowrap;
        text-align: left;
    }

    .data-table-recep thead th:first-child {
        border-radius: 8px 0 0 0;
    }

    .data-table-recep thead th:last-child {
        border-radius: 0 8px 0 0;
    }

    .data-table-recep td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
        transition: background 0.2s ease;
    }

    .data-table-recep tbody tr:hover td {
        background: var(--page-hover, #F8FAFC);
    }

    [data-theme="dark"] .data-table-recep tbody tr:hover td {
        background: #0F172A;
    }

    .data-table-recep tbody tr:last-child td {
        border-bottom: none;
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge-recep {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
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
    .badge-teal-recep { background: #0D9488; }

    [data-theme="dark"] .badge-warning-recep {
        color: #1E293B;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-recep {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: 1.5px solid var(--page-border, #E2E8F0);
        text-decoration: none;
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
    }

    .btn-recep:hover {
        transform: translateY(-2px);
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.08));
    }

    .btn-recep.btn-sm-recep {
        padding: 3px 10px;
        font-size: 0.65rem;
        border-radius: 6px;
    }

    .btn-recep.btn-primary-recep {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        border-color: #0B5ED7;
    }

    .btn-recep.btn-primary-recep:hover {
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
        color: white;
    }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state-recep {
        text-align: center;
        padding: 30px 20px;
        color: var(--page-text-secondary, #64748B);
    }

    .empty-state-recep i {
        font-size: 2.5rem;
        color: var(--page-border, #E2E8F0);
        margin-bottom: 12px;
        display: block;
    }

    .empty-state-recep h4 {
        font-size: 1rem;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 4px;
    }

    .empty-state-recep p {
        font-size: 0.85rem;
        color: var(--page-text-secondary, #64748B);
    }

    /* ================================================================
       ACTIVITIES LIST
       ================================================================ */
    .activities-list-recep {
        max-height: 320px;
        overflow-y: auto;
    }

    .activity-item-recep {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 20px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        transition: background 0.2s ease;
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
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: white;
        font-size: 0.6rem;
    }

    .activity-content-recep {
        flex: 1;
        min-width: 0;
    }

    .activity-title-recep {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
    }

    .activity-details-recep {
        font-size: 0.72rem;
        color: var(--page-text-secondary, #64748B);
        margin: 2px 0 0 0;
    }

    .activity-time-recep {
        font-size: 0.65rem;
        color: var(--page-text-muted, #94A3B8);
        margin: 4px 0 0 0;
    }

    /* ================================================================
       QUICK ACTIONS GRID
       ================================================================ */
    .quick-actions-recep {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .quick-action-recep {
        background: var(--page-bg-card, #FFFFFF);
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 12px;
        padding: 18px;
        text-align: center;
        text-decoration: none;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }

    .quick-action-recep:hover {
        border-color: var(--page-primary, #0B5ED7);
        transform: translateY(-4px);
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.08));
    }

    .quick-action-recep i {
        font-size: 1.8rem;
        display: block;
    }

    .quick-action-recep span {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-recep {
        padding: 14px 0;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-recep .footer-brand-recep {
        color: var(--page-primary, #0B5ED7);
        font-weight: 700;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .stats-grid-recep {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 1024px) {
        .stats-grid-recep {
            grid-template-columns: repeat(2, 1fr);
        }
        .quick-actions-recep {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .page-header-recep {
            padding: 18px 20px;
            flex-direction: column;
            align-items: flex-start;
        }
        .page-header-recep .page-title {
            font-size: 1.3rem;
        }
        .stats-grid-recep {
            grid-template-columns: 1fr 1fr;
        }
        .detail-card-recep {
            padding: 16px;
        }
        .data-table-recep {
            font-size: 0.65rem;
        }
        .data-table-recep thead th,
        .data-table-recep td {
            padding: 6px 8px;
        }
        .stat-card-recep {
            padding: 14px 16px;
            min-height: 80px;
        }
        .stat-value-recep {
            font-size: 1.2rem;
        }
        .stat-icon-recep {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }
    }

    @media (max-width: 480px) {
        .stats-grid-recep {
            grid-template-columns: 1fr;
        }
        .quick-actions-recep {
            grid-template-columns: 1fr;
        }
        .card-header-recep {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUpRecep {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-recep {
        animation: fadeInUpRecep 0.5s ease forwards;
        opacity: 0;
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-recep animate-fade-in-up-recep">
        <div>
            <h1 class="page-title">
                <i class="fas fa-headset"></i>
                Reception Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($reception['name']) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-<?= $reception['status'] === 'active' ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= ucfirst($reception['status']) ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-users"></i> <?= $reception['total_patients'] ?? 0 ?> Patients
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-calendar-check"></i> <?= $reception['today_appointments'] ?? 0 ?> Today
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="edit_branch.php?id=<?= $reception['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="receptions.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- RECEPTION INFO CARD -->
    <div class="detail-card-recep animate-fade-in-up-recep" style="animation-delay:0.05s;">
        <div class="detail-grid-recep">
            <div>
                <p class="detail-label-recep"><i class="fas fa-map-marker-alt"></i> Location</p>
                <p class="detail-value-recep"><?= htmlspecialchars($reception['location'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-recep"><i class="fas fa-phone"></i> Phone</p>
                <p class="detail-value-recep"><?= htmlspecialchars($reception['phone'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-recep"><i class="fas fa-envelope"></i> Email</p>
                <p class="detail-value-recep"><?= htmlspecialchars($reception['email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-recep"><i class="fas fa-calendar-plus"></i> Created</p>
                <p class="detail-value-recep"><?= date('M d, Y h:i A', strtotime($reception['created_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label-recep"><i class="fas fa-clock"></i> Last Updated</p>
                <p class="detail-value-recep"><?= date('M d, Y h:i A', strtotime($reception['updated_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label-recep"><i class="fas fa-user-tie"></i> Receptionists</p>
                <p class="detail-value-recep"><?= $reception['active_receptionists'] ?? 0 ?> Active / <?= $reception['total_receptionists'] ?? 0 ?> Total</p>
            </div>
        </div>
    </div>

    <!-- 8 STATS CARDS -->
    <div class="stats-grid-recep animate-fade-in-up-recep" style="animation-delay:0.1s;">
        <!-- 1. Total Patients -->
        <a href="patients.php?branch=<?= $reception_id ?>" class="stat-card-recep">
            <div class="stat-icon-recep"><i class="fas fa-users"></i></div>
            <div class="stat-content-recep">
                <p class="stat-label-recep">Total Patients</p>
                <p class="stat-value-recep"><?= number_format($reception['total_patients'] ?? 0) ?></p>
                <p class="stat-sub-recep">+<?= number_format($reception['today_patients'] ?? 0) ?> today</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-recep"></i>
        </a>

        <!-- 2. Today's Visits -->
        <a href="visits.php?branch=<?= $reception_id ?>&date=today" class="stat-card-recep">
            <div class="stat-icon-recep"><i class="fas fa-clinic-medical"></i></div>
            <div class="stat-content-recep">
                <p class="stat-label-recep">Today's Visits</p>
                <p class="stat-value-recep"><?= number_format($reception['today_visits'] ?? 0) ?></p>
                <p class="stat-sub-recep">Pending: <?= $reception['pending_visits'] ?? 0 ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-recep"></i>
        </a>

        <!-- 3. Appointments -->
        <a href="appointments.php?branch=<?= $reception_id ?>" class="stat-card-recep">
            <div class="stat-icon-recep"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-content-recep">
                <p class="stat-label-recep">Appointments</p>
                <p class="stat-value-recep"><?= number_format(($reception['scheduled_appointments'] ?? 0) + ($reception['confirmed_appointments'] ?? 0)) ?></p>
                <p class="stat-sub-recep">Sched: <?= $reception['scheduled_appointments'] ?? 0 ?> | Conf: <?= $reception['confirmed_appointments'] ?? 0 ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-recep"></i>
        </a>

        <!-- 4. Today's Appointments -->
        <a href="appointments.php?branch=<?= $reception_id ?>&date=today" class="stat-card-recep">
            <div class="stat-icon-recep"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-content-recep">
                <p class="stat-label-recep">Today's Appointments</p>
                <p class="stat-value-recep"><?= number_format($reception['today_appointments'] ?? 0) ?></p>
                <p class="stat-sub-recep">Need attention</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-recep"></i>
        </a>

        <!-- 5. Pending Visits -->
        <a href="visits.php?branch=<?= $reception_id ?>&status=pending" class="stat-card-recep">
            <div class="stat-icon-recep"><i class="fas fa-clock"></i></div>
            <div class="stat-content-recep">
                <p class="stat-label-recep">Pending Visits</p>
                <p class="stat-value-recep"><?= number_format($reception['pending_visits'] ?? 0) ?></p>
                <p class="stat-sub-recep">Need doctor assignment</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-recep"></i>
        </a>

        <!-- 6. Assigned Visits -->
        <a href="visits.php?branch=<?= $reception_id ?>&status=assigned" class="stat-card-recep">
            <div class="stat-icon-recep"><i class="fas fa-user-check"></i></div>
            <div class="stat-content-recep">
                <p class="stat-label-recep">Assigned Visits</p>
                <p class="stat-value-recep"><?= number_format($reception['assigned_visits'] ?? 0) ?></p>
                <p class="stat-sub-recep">With doctors</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-recep"></i>
        </a>

        <!-- 7. Receptionists -->
        <a href="employees.php?branch=<?= $reception_id ?>&role=reception" class="stat-card-recep">
            <div class="stat-icon-recep"><i class="fas fa-user-tie"></i></div>
            <div class="stat-content-recep">
                <p class="stat-label-recep">Receptionists</p>
                <p class="stat-value-recep"><?= number_format($reception['total_receptionists'] ?? 0) ?></p>
                <p class="stat-sub-recep"><?= $reception['active_receptionists'] ?? 0 ?> active</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-recep"></i>
        </a>

        <!-- 8. Revenue -->
        <a href="reports.php?branch=<?= $reception_id ?>&type=reception" class="stat-card-recep">
            <div class="stat-icon-recep"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-content-recep">
                <p class="stat-label-recep">Revenue</p>
                <p class="stat-value-recep">TSh <?= number_format($reception['total_revenue'] ?? 0, 0) ?></p>
                <p class="stat-sub-recep"><?= $reception['paid_bills'] ?? 0 ?> paid | <?= $reception['pending_bills'] ?? 0 ?> pending</p>
            </div>
            <i class="fas fa-chevron-right stat-arrow-recep"></i>
        </a>
    </div>

    <!-- RECEPTIONISTS LIST -->
    <div class="card-recep animate-fade-in-up-recep" style="animation-delay:0.15s;">
        <div class="card-header-recep">
            <h3 class="card-title-recep">
                <i class="fas fa-user-tie" style="color:#0D9488;"></i>
                Receptionists (<?= count($receptionists) ?>)
            </h3>
            <a href="add_employee.php?branch=<?= $reception_id ?>&role=reception" class="btn-recep btn-sm-recep btn-primary-recep">
                <i class="fas fa-plus"></i> Add Receptionist
            </a>
        </div>
        <div style="overflow-x:auto;">
            <?php if (count($receptionists) > 0): ?>
                <table class="data-table-recep">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Online</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receptionists as $receptionist): ?>
                            <tr>
                                <td style="font-weight:500;"><?= htmlspecialchars($receptionist['full_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($receptionist['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($receptionist['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-recep badge-<?= $receptionist['status'] === 'active' ? 'success' : 'danger' ?>-recep" style="font-size:0.6rem;padding:2px 10px;">
                                        <?= ucfirst($receptionist['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-recep <?= ($receptionist['is_online'] ?? 0) ? 'badge-success-recep' : 'badge-secondary-recep' ?>" style="font-size:0.6rem;padding:2px 10px;">
                                        <i class="fas fa-circle"></i>
                                        <?= ($receptionist['is_online'] ?? 0) ? 'Online' : 'Offline' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_employee.php?id=<?= $receptionist['id'] ?>&branch=<?= $reception_id ?>" class="btn-recep btn-sm-recep btn-primary-recep">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state-recep">
                    <i class="fas fa-user-tie"></i>
                    <h4>No Receptionists</h4>
                    <p>No receptionists assigned to this branch.</p>
                    <a href="add_employee.php?branch=<?= $reception_id ?>&role=reception" class="btn-recep btn-sm-recep btn-primary-recep" style="margin-top:12px;">
                        <i class="fas fa-plus"></i> Add Receptionist
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RECENT PATIENTS -->
    <div class="card-recep animate-fade-in-up-recep" style="animation-delay:0.2s;">
        <div class="card-header-recep">
            <h3 class="card-title-recep">
                <i class="fas fa-user-plus" style="color:#0B5ED7;"></i>
                Recent Patients Registered
            </h3>
            <a href="patients.php?branch=<?= $reception_id ?>" class="card-link-recep">View All →</a>
        </div>
        <div style="overflow-x:auto;">
            <?php if (count($recent_patients) > 0): ?>
                <table class="data-table-recep">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Gender</th>
                            <th>Registered By</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_patients as $patient): ?>
                            <tr>
                                <td style="font-family:monospace;font-size:0.72rem;"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                <td style="font-weight:500;"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($patient['registered_by'] ?? 'N/A') ?></td>
                                <td style="font-size:0.7rem;"><?= date('M d, Y h:i A', strtotime($patient['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_patient.php?id=<?= $patient['id'] ?>&branch=<?= $reception_id ?>" class="btn-recep btn-sm-recep btn-primary-recep">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state-recep">
                    <i class="fas fa-user-plus"></i>
                    <h4>No Patients</h4>
                    <p>No patients registered yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RECENT APPOINTMENTS -->
    <div class="card-recep animate-fade-in-up-recep" style="animation-delay:0.25s;">
        <div class="card-header-recep">
            <h3 class="card-title-recep">
                <i class="fas fa-calendar-plus" style="color:#7C3AED;"></i>
                Recent Appointments
            </h3>
            <a href="appointments.php?branch=<?= $reception_id ?>" class="card-link-recep">View All →</a>
        </div>
        <div style="overflow-x:auto;">
            <?php if (count($recent_appointments) > 0): ?>
                <table class="data-table-recep">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_appointments as $appointment): ?>
                            <tr>
                                <td style="font-weight:500;"><?= htmlspecialchars($appointment['patient_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($appointment['doctor_name'] ?? 'N/A') ?></td>
                                <td style="font-size:0.72rem;"><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'] ?? 'now')) ?></td>
                                <td>
                                    <span class="badge-recep badge-info-recep" style="font-size:0.6rem;padding:2px 10px;">
                                        <?= ucfirst($appointment['visit_type'] ?? 'new') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-recep badge-<?= getStatusBadge($appointment['status'] ?? 'scheduled') ?>-recep" style="font-size:0.6rem;padding:2px 10px;">
                                        <i class="fas <?= getStatusIcon($appointment['status'] ?? 'scheduled') ?>"></i>
                                        <?= getStatusLabel($appointment['status'] ?? 'Scheduled') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_appointment.php?id=<?= $appointment['id'] ?>&branch=<?= $reception_id ?>" class="btn-recep btn-sm-recep btn-primary-recep">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state-recep">
                    <i class="fas fa-calendar-plus"></i>
                    <h4>No Appointments</h4>
                    <p>No appointments found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RECENT VISITS -->
    <div class="card-recep animate-fade-in-up-recep" style="animation-delay:0.3s;">
        <div class="card-header-recep">
            <h3 class="card-title-recep">
                <i class="fas fa-clinic-medical" style="color:#059669;"></i>
                Recent Visits
            </h3>
            <a href="visits.php?branch=<?= $reception_id ?>" class="card-link-recep">View All →</a>
        </div>
        <div style="overflow-x:auto;">
            <?php if (count($recent_visits) > 0): ?>
                <table class="data-table-recep">
                    <thead>
                        <tr>
                            <th>Visit #</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_visits as $visit): ?>
                            <tr>
                                <td style="font-family:monospace;font-size:0.72rem;"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></td>
                                <td style="font-weight:500;"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($visit['doctor_name'] ?? 'Not Assigned') ?></td>
                                <td>
                                    <span class="badge-recep badge-info-recep" style="font-size:0.6rem;padding:2px 10px;">
                                        <?= ucfirst($visit['visit_type'] ?? 'new') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-recep badge-<?= getStatusBadge($visit['status'] ?? 'pending') ?>-recep" style="font-size:0.6rem;padding:2px 10px;">
                                        <i class="fas <?= getStatusIcon($visit['status'] ?? 'pending') ?>"></i>
                                        <?= getStatusLabel($visit['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td style="font-size:0.72rem;"><?= date('M d, Y h:i A', strtotime($visit['visit_date'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_visit.php?id=<?= $visit['id'] ?>&branch=<?= $reception_id ?>" class="btn-recep btn-sm-recep btn-primary-recep">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state-recep">
                    <i class="fas fa-clinic-medical"></i>
                    <h4>No Visits</h4>
                    <p>No visits found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RECENT ACTIVITIES -->
    <div class="card-recep animate-fade-in-up-recep" style="animation-delay:0.35s;">
        <div class="card-header-recep">
            <h3 class="card-title-recep">
                <i class="fas fa-clock" style="color:#64748B;"></i>
                Recent Activities
            </h3>
            <a href="system_logs.php?branch=<?= $reception_id ?>" class="card-link-recep">View All →</a>
        </div>
        <div class="activities-list-recep">
            <?php if (count($recent_activities) > 0): ?>
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item-recep">
                        <div class="activity-icon-recep">
                            <i class="fas fa-circle"></i>
                        </div>
                        <div class="activity-content-recep">
                            <p class="activity-title-recep">
                                <?php 
                                    $action_display = $activity['action'] ?? 'Action';
                                    $action_display = ucwords(str_replace('_', ' ', $action_display));
                                    echo htmlspecialchars($action_display);
                                ?>
                            </p>
                            <p class="activity-details-recep">
                                <?= htmlspecialchars($activity['details'] ?? '') ?>
                                <?php if (!empty($activity['user_name'])): ?>
                                    <span style="color:#94A3B8;">by <?= htmlspecialchars($activity['user_name']) ?></span>
                                <?php endif; ?>
                            </p>
                            <p class="activity-time-recep">
                                <?= isset($activity['created_at']) ? date('M d, Y h:i A', strtotime($activity['created_at'])) : 'Just now' ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state-recep">
                    <i class="fas fa-clock"></i>
                    <h4>No Activities</h4>
                    <p>No activities found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="quick-actions-recep animate-fade-in-up-recep" style="animation-delay:0.4s;">
        <a href="add_patient.php?branch=<?= $reception_id ?>" class="quick-action-recep">
            <i class="fas fa-user-plus" style="color:#0B5ED7;"></i>
            <span>Register Patient</span>
        </a>
        <a href="add_appointment.php?branch=<?= $reception_id ?>" class="quick-action-recep">
            <i class="fas fa-calendar-plus" style="color:#7C3AED;"></i>
            <span>Schedule Appointment</span>
        </a>
        <a href="assign_doctor.php?branch=<?= $reception_id ?>" class="quick-action-recep">
            <i class="fas fa-user-md" style="color:#059669;"></i>
            <span>Assign Doctor</span>
        </a>
        <a href="reports.php?branch=<?= $reception_id ?>&type=reception" class="quick-action-recep">
            <i class="fas fa-chart-bar" style="color:#D97706;"></i>
            <span>View Reports</span>
        </a>
    </div>

    <!-- FOOTER -->
    <footer class="footer-recep">
        <p>
            <span class="footer-brand-recep">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Reception Details - <?= htmlspecialchars($reception['name']) ?>
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

    console.log('%c📋 Braick - View Reception', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c✅ NO duplicate dark mode JavaScript', 'font-size:13px; color:#059669;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($reception['name']) ?> (ID: <?= $reception_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c👥 Total Patients: <?= number_format($reception['total_patients'] ?? 0) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c💰 Revenue: TSh <?= number_format($reception['total_revenue'] ?? 0, 0) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>