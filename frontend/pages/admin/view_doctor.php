<?php
// ================================================================
// FILE: frontend/pages/admin/view_doctor.php
// SUPER ADMIN - VIEW DOCTOR COMPLETE DASHBOARD
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Blue theme + full dark mode support via --page-* variables
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
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

// ================================================================
// GET ADMIN DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777', '#4F46E5', '#0891B2', '#2563EB'];
    return $colors[abs(crc32($name)) % count($colors)];
}

function getStatusBadge($status) {
    $classes = [
        'pending' => 'warning', 'paid' => 'success', 'partial' => 'warning',
        'cancelled' => 'danger', 'completed' => 'success', 'confirmed' => 'info',
        'dispensed' => 'success', 'in_progress' => 'info', 'scheduled' => 'info',
        'active' => 'success', 'inactive' => 'danger', 'online' => 'success',
        'offline' => 'danger', 'with_doctor' => 'primary', 'lab_test' => 'info',
        'lab_completed' => 'success', 'prescribed' => 'info'
    ];
    return $classes[$status] ?? 'secondary';
}

// ================================================================
// GET DOCTOR ID
// ================================================================
$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($doctor_id <= 0) {
    header('Location: doctors_list.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET DOCTOR DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT u.*, b.name as branch_name 
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.id = ? AND u.role = 'doctor'
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    header('Location: doctors_list.php?branch=' . $selected_branch_id . '&error=not_found');
    exit;
}

// Defaults for missing fields
$doctor['full_name'] = $doctor['full_name'] ?? 'Unknown Doctor';
$doctor['email'] = $doctor['email'] ?? 'No email provided';
$doctor['phone'] = $doctor['phone'] ?? 'No phone provided';
$doctor['specialty'] = $doctor['specialty'] ?? 'General Practitioner';
$doctor['branch_name'] = $doctor['branch_name'] ?? 'Not Assigned';
$doctor['status'] = $doctor['status'] ?? 'active';
$doctor['is_online'] = $doctor['is_online'] ?? 0;
$doctor['created_at'] = $doctor['created_at'] ?? date('Y-m-d H:i:s');
$doctor['username'] = $doctor['username'] ?? 'N/A';

// ================================================================
// GET ALL DOCTOR STATISTICS
// ================================================================
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$doctor_id]);
$today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND status = 'pending'");
$stmt->execute([$doctor_id]);
$pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE doctor_id = ? AND status = 'pending'");
$stmt->execute([$doctor_id]);
$pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COALESCE(SUM(bi.total_price), 0) as revenue 
    FROM bill_items bi
    INNER JOIN bills b ON bi.bill_id = b.id
    INNER JOIN visits v ON b.visit_id = v.id
    WHERE v.doctor_id = ? AND b.status = 'paid'
");
$stmt->execute([$doctor_id]);
$revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

$stmt = $db->prepare("
    SELECT a.*, p.full_name as patient_name, p.phone, p.patient_id 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ? AND DATE(a.appointment_date) = CURDATE()
    ORDER BY a.appointment_date
");
$stmt->execute([$doctor_id]);
$today_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL PATIENTS
// ================================================================
$all_patients = [];
$stmt = $db->prepare("
    SELECT DISTINCT 
        p.id, p.patient_id, p.full_name, p.gender, p.date_of_birth, p.phone, p.email,
        p.address, p.blood_group, p.allergies, p.created_at, p.assigned_doctor_id,
        (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND doctor_id = ?) as total_visits,
        (SELECT COUNT(*) FROM lab_tests lt JOIN visits v ON lt.visit_id = v.id WHERE v.patient_id = p.id AND lt.doctor_id = ?) as total_lab_tests,
        (SELECT COUNT(*) FROM prescriptions pr JOIN visits v ON pr.visit_id = v.id WHERE v.patient_id = p.id AND pr.doctor_id = ?) as total_prescriptions,
        (SELECT COUNT(*) FROM bills b JOIN visits v ON b.visit_id = v.id WHERE v.patient_id = p.id) as total_bills
    FROM patients p
    JOIN visits v ON p.id = v.patient_id
    WHERE v.doctor_id = ?
    ORDER BY p.full_name ASC
");
$stmt->execute([$doctor_id, $doctor_id, $doctor_id, $doctor_id]);
$all_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL VISITS
// ================================================================
$all_visits = [];
$stmt = $db->prepare("
    SELECT v.*, p.full_name as patient_name, p.patient_id as patient_code, u.full_name as doctor_name
    FROM visits v
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE v.doctor_id = ?
    ORDER BY v.created_at DESC
");
$stmt->execute([$doctor_id]);
$all_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL LAB TESTS
// ================================================================
$all_lab_tests = [];
$stmt = $db->prepare("
    SELECT lt.*, p.full_name as patient_name, p.patient_id as patient_code,
           v.visit_number, u.full_name as technician_name
    FROM lab_tests lt
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON lt.lab_technician_id = u.id
    WHERE lt.doctor_id = ?
    ORDER BY lt.created_at DESC
");
$stmt->execute([$doctor_id]);
$all_lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL PRESCRIPTIONS
// ================================================================
$all_prescriptions = [];
$stmt = $db->prepare("
    SELECT pr.*, p.full_name as patient_name, p.patient_id as patient_code,
           v.visit_number,
           (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = pr.id) as item_count
    FROM prescriptions pr
    LEFT JOIN visits v ON pr.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    WHERE pr.doctor_id = ?
    ORDER BY pr.created_at DESC
");
$stmt->execute([$doctor_id]);
$all_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL PROCEDURES & TOOLS
// ================================================================
$all_procedures = [];
$stmt = $db->prepare("
    SELECT bi.*, p.full_name as patient_name, p.patient_id as patient_code,
           b.bill_number, v.visit_number
    FROM bill_items bi
    LEFT JOIN bills b ON bi.bill_id = b.id
    LEFT JOIN visits v ON b.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    WHERE bi.item_type IN ('procedure', 'tool') AND v.doctor_id = ?
    ORDER BY bi.created_at DESC
");
$stmt->execute([$doctor_id]);
$all_procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ALL BILLS
// ================================================================
$all_bills = [];
$stmt = $db->prepare("
    SELECT b.*, p.full_name as patient_name, p.patient_id as patient_code,
           u.full_name as created_by_name, v.visit_number
    FROM bills b
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN visits v ON b.visit_id = v.id
    WHERE v.doctor_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$doctor_id]);
$all_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// WEEKLY VISITS CHART DATA
// ================================================================
$stmt = $db->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM visits 
    WHERE doctor_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at) ORDER BY date
");
$stmt->execute([$doctor_id]);
$weekly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$chart_labels = [];
$chart_values = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date));
    $found = false;
    foreach ($weekly_data as $data) {
        if ($data['date'] == $date) {
            $chart_values[] = (int)$data['count'];
            $found = true;
            break;
        }
    }
    if (!$found) $chart_values[] = 0;
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
    /* DOCTOR HEADER */
    .doctor-header-vd {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        padding: 24px 28px;
        border: 2px solid var(--page-border, #E2E8F0);
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
    }

    [data-theme="dark"] .doctor-header-vd { background: #1E293B; border-color: #334155; }

    .doctor-header-vd:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.1);
    }

    .doctor-info-vd {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .doctor-avatar-large-vd {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: 700;
        color: #FFFFFF;
        flex-shrink: 0;
        box-shadow: 0 4px 14px rgba(0,0,0,0.2);
    }

    .doctor-name-vd {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .doctor-specialty-badge-vd {
        display: inline-block;
        background: #E8F0FE;
        color: var(--page-primary, #0B5ED7);
        padding: 2px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    [data-theme="dark"] .doctor-specialty-badge-vd { background: #1E3A5F; color: #6EA8FE; }

    .doctor-meta-vd {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 8px;
    }

    .doctor-meta-vd span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
        color: var(--page-text-secondary, #64748B);
        background: var(--page-hover, #F8FAFC);
        padding: 4px 12px;
        border-radius: 8px;
        border: 1px solid var(--page-border, #E2E8F0);
    }

    [data-theme="dark"] .doctor-meta-vd span { background: #0F172A; border-color: #334155; }

    .doctor-meta-vd span i {
        font-size: 0.8rem;
        color: var(--page-primary, #0B5ED7);
    }

    .online-dot-vd {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #059669;
        animation: pulse-dot-vd 1.5s infinite;
    }

    .offline-dot-vd {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #94A3B8;
    }

    @keyframes pulse-dot-vd {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }

    .badge-status-vd {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #FFFFFF;
    }

    .badge-status-vd.success { background: #059669; }
    .badge-status-vd.danger { background: #DC2626; }

    /* STATS GRID - 6 CARDS WITH BLUE BACKGROUND */
    .stats-grid-vd {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card-blue-vd {
        background: linear-gradient(135deg, #0A4CA8, #073B8A);
        border-radius: 14px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(10, 76, 168, 0.25);
    }

    .stat-card-blue-vd:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(10, 76, 168, 0.35);
    }

    .stat-card-blue-vd .stat-icon-vd {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
        background: rgba(255,255,255,0.15);
        color: #FFFFFF;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .stat-card-blue-vd .stat-number-vd {
        font-size: 1.5rem;
        font-weight: 700;
        color: #FFFFFF !important;
    }

    .stat-card-blue-vd .stat-label-vd {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.8) !important;
        font-weight: 500;
        margin-top: 2px;
    }

    /* DASHBOARD GRID */
    .dashboard-grid-vd {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    /* CARDS */
    .card-clean-vd {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 14px;
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }

    [data-theme="dark"] .card-clean-vd { background: #1E293B; border-color: #334155; }

    .card-clean-vd:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }

    .card-clean-vd .card-body-vd {
        padding: 16px 18px;
    }

    /* SECTION HEADER */
    .section-header-clean-vd {
        padding: 12px 18px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .section-header-clean-vd .section-title-vd {
        color: var(--page-text-primary, #1E293B);
        font-size: 0.95rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-header-clean-vd .section-title-vd i {
        color: var(--page-primary, #0B5ED7);
    }

    .section-header-clean-vd .section-badge-vd {
        background: var(--page-hover, #F8FAFC);
        color: var(--page-text-secondary, #64748B);
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        border: 1px solid var(--page-border, #E2E8F0);
    }

    /* EMPTY STATE */
    .empty-state-vd {
        text-align: center;
        padding: 15px;
        color: var(--page-text-secondary, #64748B);
    }

    .empty-state-vd i {
        font-size: 2rem;
        color: var(--page-text-muted, #94A3B8);
        display: block;
        margin-bottom: 6px;
    }

    /* CHART */
    .chart-container-vd {
        height: 110px !important;
        max-height: 110px !important;
    }
    .chart-container-vd canvas {
        height: 100% !important;
        max-height: 110px !important;
    }

    /* APPOINTMENTS */
    .appointments-container-vd {
        max-height: 160px;
        overflow-y: auto;
    }

    .appointments-container-vd::-webkit-scrollbar { width: 4px; }
    .appointments-container-vd::-webkit-scrollbar-thumb { background: #0B5ED7; border-radius: 4px; }

    .appointment-item-vd {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 6px 10px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
    }
    .appointment-item-vd:hover { background: var(--page-hover, #F8FAFC); }
    .appointment-item-vd:last-child { border-bottom: none; }

    .appointment-time-vd { font-weight: 600; font-size: 0.8rem; min-width: 55px; color: var(--page-text-primary); }
    .appointment-patient-vd .name-vd { font-weight: 500; font-size: 0.85rem; color: var(--page-text-primary); }
    .appointment-patient-vd .phone-vd { font-size: 0.65rem; color: var(--page-text-secondary); }

    .appointment-status-vd {
        font-size: 0.65rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 12px;
    }
    .appointment-status-vd.scheduled { background: #E8F0FE; color: #0B5ED7; }
    .appointment-status-vd.confirmed { background: #D1FAE5; color: #059669; }
    .appointment-status-vd.completed { background: #D1FAE5; color: #059669; }
    .appointment-status-vd.cancelled { background: #FEE2E2; color: #DC2626; }
    .appointment-status-vd.pending { background: #FEF3C7; color: #D97706; }

    [data-theme="dark"] .appointment-status-vd.scheduled { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .appointment-status-vd.confirmed,
    [data-theme="dark"] .appointment-status-vd.completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .appointment-status-vd.cancelled { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .appointment-status-vd.pending { background: #3A2A1A; color: #FBBF24; }

    /* DATA TABLE - BLUE HEADERS */
    .table-container-vd {
        overflow-x: auto;
        padding: 0;
    }

    .data-table-vd {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
    }

    .data-table-vd thead th {
        background: linear-gradient(135deg, #0A4CA8, #073B8A);
        color: white;
        font-weight: 700;
        padding: 12px 14px;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
        border-bottom: 3px solid #0A4CA8;
        text-align: left;
    }

    .data-table-vd thead th:first-child { border-radius: 8px 0 0 0; }
    .data-table-vd thead th:last-child { border-radius: 0 8px 0 0; }

    .data-table-vd td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
        font-size: 0.8rem;
    }

    .data-table-vd tbody tr:nth-child(even) { background: var(--page-hover, #F8FAFC); }
    .data-table-vd tbody tr:hover { background: #E8F0FE; }
    [data-theme="dark"] .data-table-vd tbody tr:nth-child(even) { background: #0F172A; }
    [data-theme="dark"] .data-table-vd tbody tr:hover { background: #1E3A5F; }

    .data-table-vd tbody tr:last-child td { border-bottom: none; }

    /* ACTION BUTTONS */
    .action-btns-vd {
        display: flex;
        flex-direction: row;
        gap: 4px;
        flex-wrap: wrap;
        align-items: center;
    }

    .btn-action-vd {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.65rem;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        border: none;
        cursor: pointer;
        white-space: nowrap;
        min-height: 30px;
        font-family: inherit;
    }

    .btn-action-vd i { font-size: 0.65rem; }

    .btn-action-vd.view {
        background: #E8F0FE;
        color: #0B5ED7;
        border: 1px solid rgba(11, 94, 215, 0.15);
    }
    .btn-action-vd.view:hover {
        background: #0B5ED7;
        color: white;
        transform: translateY(-1px);
    }

    .btn-action-vd.edit {
        background: #FEF3C7;
        color: #D97706;
        border: 1px solid rgba(217, 119, 6, 0.15);
    }
    .btn-action-vd.edit:hover {
        background: #D97706;
        color: white;
        transform: translateY(-1px);
    }

    .btn-action-vd.delete {
        background: #FEE2E2;
        color: #DC2626;
        border: 1px solid rgba(220, 38, 38, 0.15);
    }
    .btn-action-vd.delete:hover {
        background: #DC2626;
        color: white;
        transform: translateY(-1px);
    }

    [data-theme="dark"] .btn-action-vd.view { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .btn-action-vd.edit { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .btn-action-vd.delete { background: #3A1A1A; color: #F87171; }

    /* BADGES */
    .badge-vd {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        color: white;
    }
    .badge-info-vd { background: #0B5ED7; }
    .badge-success-vd { background: #059669; }
    .badge-danger-vd { background: #DC2626; }
    .badge-warning-vd { background: #D97706; color: #1E293B; }
    .badge-purple-vd { background: #7C3AED; }
    .badge-teal-vd { background: #0D9488; }
    .badge-secondary-vd { background: #64748B; }

    /* BUTTONS */
    .btn-vd {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
        color: #FFFFFF !important;
        font-family: inherit;
    }

    .btn-blue-vd { background: #0B5ED7; }
    .btn-blue-vd:hover { background: #0A4CA8; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); color: white; }

    .btn-green-vd { background: #059669; }
    .btn-green-vd:hover { background: #047857; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); color: white; }

    .btn-red-vd { background: #DC2626; }
    .btn-red-vd:hover { background: #B91C1C; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); color: white; }

    .btn-outline-vd {
        background: transparent;
        color: var(--page-text-secondary, #64748B) !important;
        border: 2px solid var(--page-border, #E2E8F0);
    }
    .btn-outline-vd:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7) !important;
    }

    .btn-sm-vd { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }

    /* FOOTER */
    .footer-vd {
        padding: 14px 0;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }
    .footer-vd .footer-brand-vd { color: var(--page-primary, #0B5ED7); font-weight: 600; }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .dashboard-grid-vd { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        .doctor-header-vd { flex-direction: column; align-items: flex-start; gap: 16px; }
        .doctor-info-vd { flex-wrap: wrap; }
        .doctor-avatar-large-vd { width: 60px; height: 60px; font-size: 1.5rem; }
        .doctor-name-vd { font-size: 1.2rem; }
        .stats-grid-vd { grid-template-columns: repeat(2, 1fr); }
        .chart-container-vd { height: 90px !important; }
        .data-table-vd td { font-size: 0.7rem; padding: 8px 8px; }
        .data-table-vd thead th { font-size: 0.5rem; padding: 6px 8px; }
        .btn-action-vd { font-size: 0.55rem; padding: 3px 6px; }
    }

    @media (max-width: 480px) {
        .stats-grid-vd { grid-template-columns: 1fr; }
        .doctor-meta-vd span { font-size: 0.7rem; padding: 2px 8px; }
        .stat-card-blue-vd .stat-number-vd { font-size: 1.2rem; }
        .chart-container-vd { height: 80px !important; }
        .data-table-vd td { font-size: 0.6rem; padding: 6px 6px; }
        .btn-action-vd { font-size: 0.5rem; padding: 2px 5px; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- DOCTOR HEADER -->
    <div class="doctor-header-vd">
        <div class="doctor-info-vd">
            <div class="doctor-avatar-large-vd" style="background: <?= getUserColor($doctor['full_name']) ?>;">
                <?= strtoupper(substr($doctor['full_name'], 0, 2)) ?>
            </div>
            <div>
                <div class="doctor-name-vd">
                    <?= htmlspecialchars($doctor['full_name']) ?>
                    <span class="doctor-specialty-badge-vd"><?= htmlspecialchars($doctor['specialty']) ?></span>
                </div>
                <div class="doctor-meta-vd">
                    <span><i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor['branch_name']) ?></span>
                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($doctor['email']) ?></span>
                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($doctor['phone']) ?></span>
                    <span>
                        <?php if ($doctor['is_online']): ?>
                            <span class="online-dot-vd"></span> Online
                        <?php else: ?>
                            <span class="offline-dot-vd"></span> Offline
                        <?php endif; ?>
                    </span>
                    <span><i class="fas fa-calendar-alt"></i> Joined: <?= date('M d, Y', strtotime($doctor['created_at'])) ?></span>
                    <span><span class="badge-status-vd <?= $doctor['status'] === 'active' ? 'success' : 'danger' ?>">
                        <i class="fas fa-circle" style="font-size:6px;"></i> <?= ucfirst($doctor['status']) ?>
                    </span></span>
                </div>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="edit_employee.php?id=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-vd btn-green-vd btn-sm-vd">
                <i class="fas fa-edit"></i> Edit
            </a>
            <button onclick="deactivateDoctorVd(<?= $doctor['id'] ?>)" class="btn-vd btn-red-vd btn-sm-vd">
                <i class="fas fa-user-slash"></i> Deactivate
            </button>
            <a href="doctors_list.php?branch=<?= $selected_branch_id ?>" class="btn-vd btn-outline-vd btn-sm-vd">
                <i class="fas fa-arrow-left"></i> All Doctors
            </a>
        </div>
    </div>

    <!-- STATISTICS CARDS -->
    <div class="stats-grid-vd">
        <div class="stat-card-blue-vd">
            <div class="stat-icon-vd"><i class="fas fa-users"></i></div>
            <div>
                <p class="stat-number-vd"><?= number_format($total_patients) ?></p>
                <p class="stat-label-vd">Total Patients</p>
            </div>
        </div>
        <div class="stat-card-blue-vd">
            <div class="stat-icon-vd"><i class="fas fa-calendar-day"></i></div>
            <div>
                <p class="stat-number-vd"><?= number_format($today_visits) ?></p>
                <p class="stat-label-vd">Today's Visits</p>
            </div>
        </div>
        <div class="stat-card-blue-vd">
            <div class="stat-icon-vd"><i class="fas fa-notes-medical"></i></div>
            <div>
                <p class="stat-number-vd"><?= number_format($total_visits) ?></p>
                <p class="stat-label-vd">Total Visits</p>
            </div>
        </div>
        <div class="stat-card-blue-vd">
            <div class="stat-icon-vd"><i class="fas fa-prescription"></i></div>
            <div>
                <p class="stat-number-vd"><?= number_format($pending_prescriptions) ?></p>
                <p class="stat-label-vd">Pending Prescriptions</p>
            </div>
        </div>
        <div class="stat-card-blue-vd">
            <div class="stat-icon-vd"><i class="fas fa-flask"></i></div>
            <div>
                <p class="stat-number-vd"><?= number_format($pending_lab_tests) ?></p>
                <p class="stat-label-vd">Pending Lab Tests</p>
            </div>
        </div>
        <div class="stat-card-blue-vd">
            <div class="stat-icon-vd"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <p class="stat-number-vd">TSh <?= number_format($revenue) ?></p>
                <p class="stat-label-vd">Revenue Generated</p>
            </div>
        </div>
    </div>

    <!-- CHART & APPOINTMENTS -->
    <div class="dashboard-grid-vd">
        <div class="card-clean-vd">
            <div class="section-header-clean-vd">
                <h4 class="section-title-vd"><i class="fas fa-chart-line"></i> Weekly Visits</h4>
            </div>
            <div class="card-body-vd">
                <div class="chart-container-vd">
                    <canvas id="visitsChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="card-clean-vd">
            <div class="section-header-clean-vd">
                <h4 class="section-title-vd"><i class="fas fa-calendar-check"></i> Today's Appointments</h4>
                <span class="section-badge-vd"><?= count($today_appointments) ?></span>
            </div>
            <div class="card-body-vd">
                <div class="appointments-container-vd">
                    <?php if (count($today_appointments) > 0): ?>
                        <?php foreach ($today_appointments as $appt): ?>
                            <div class="appointment-item-vd">
                                <span class="appointment-time-vd"><?= date('h:i A', strtotime($appt['appointment_date'])) ?></span>
                                <div class="appointment-patient-vd">
                                    <span class="name-vd"><?= htmlspecialchars($appt['patient_name']) ?></span>
                                    <span class="phone-vd"><?= htmlspecialchars($appt['patient_id'] ?? '') ?></span>
                                </div>
                                <span class="appointment-status-vd <?= $appt['status'] ?? 'scheduled' ?>">
                                    <?= ucfirst($appt['status'] ?? 'Scheduled') ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state-vd">
                            <i class="fas fa-calendar-check"></i>
                            <p>No appointments scheduled for today</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 1. ALL PATIENTS -->
    <div class="card-clean-vd">
        <div class="section-header-clean-vd">
            <h4 class="section-title-vd"><i class="fas fa-users"></i> All Patients</h4>
            <span class="section-badge-vd"><?= count($all_patients) ?></span>
        </div>
        <div class="table-container-vd">
            <table class="data-table-vd">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>ID</th>
                        <th>Gender</th>
                        <th>Blood Group</th>
                        <th>Visits</th>
                        <th>Lab Tests</th>
                        <th>Prescriptions</th>
                        <th>Bills</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_patients) > 0): ?>
                        <?php $i = 1; foreach ($all_patients as $patient): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($patient['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($patient['patient_id']) ?></td>
                                <td><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></td>
                                <td><span class="badge-vd badge-info-vd"><?= $patient['total_visits'] ?? 0 ?></span></td>
                                <td><span class="badge-vd badge-purple-vd"><?= $patient['total_lab_tests'] ?? 0 ?></span></td>
                                <td><span class="badge-vd badge-teal-vd"><?= $patient['total_prescriptions'] ?? 0 ?></span></td>
                                <td><span class="badge-vd badge-success-vd"><?= $patient['total_bills'] ?? 0 ?></span></td>
                                <td>
                                    <div class="action-btns-vd">
                                        <a href="view_patient.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action-vd view"><i class="fas fa-eye"></i> View</a>
                                        <a href="edit_patient.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action-vd edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button onclick="deleteItemVd('patient', <?= $patient['id'] ?>)" class="btn-action-vd delete"><i class="fas fa-trash"></i> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="10" style="text-align:center;padding:32px;color:var(--page-text-secondary);">No patients found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2. ALL VISITS -->
    <div class="card-clean-vd">
        <div class="section-header-clean-vd">
            <h4 class="section-title-vd"><i class="fas fa-notes-medical"></i> All Visits</h4>
            <span class="section-badge-vd"><?= count($all_visits) ?></span>
        </div>
        <div class="table-container-vd">
            <table class="data-table-vd">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Visit #</th>
                        <th>Patient</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Diagnosis</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_visits) > 0): ?>
                        <?php $i = 1; foreach ($all_visits as $visit): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td style="font-family:monospace;font-size:0.7rem;"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></td>
                                <td><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></td>
                                <td><span class="badge-vd badge-<?= getStatusBadge($visit['status'] ?? 'pending') ?>-vd"><?= ucfirst($visit['status'] ?? 'Pending') ?></span></td>
                                <td><?= htmlspecialchars(substr($visit['diagnosis'] ?? '', 0, 30)) ?><?= strlen($visit['diagnosis'] ?? '') > 30 ? '...' : '' ?></td>
                                <td><?= date('M d, Y', strtotime($visit['created_at'])) ?></td>
                                <td>
                                    <div class="action-btns-vd">
                                        <a href="view_visit.php?id=<?= $visit['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action-vd view"><i class="fas fa-eye"></i> View</a>
                                        <a href="edit_visit.php?id=<?= $visit['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action-vd edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button onclick="deleteItemVd('visit', <?= $visit['id'] ?>)" class="btn-action-vd delete"><i class="fas fa-trash"></i> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--page-text-secondary);">No visits found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. LAB TESTS -->
    <div class="card-clean-vd">
        <div class="section-header-clean-vd">
            <h4 class="section-title-vd"><i class="fas fa-flask"></i> Lab Tests</h4>
            <span class="section-badge-vd"><?= count($all_lab_tests) ?></span>
        </div>
        <div class="table-container-vd">
            <table class="data-table-vd">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>Test Name</th>
                        <th>Result</th>
                        <th>Status</th>
                        <th>Technician</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_lab_tests) > 0): ?>
                        <?php $i = 1; foreach ($all_lab_tests as $test): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?></td>
                                <td><strong><?= htmlspecialchars($test['test_name']) ?></strong></td>
                                <td><?= htmlspecialchars(substr($test['results'] ?? '', 0, 20)) ?><?= strlen($test['results'] ?? '') > 20 ? '...' : '' ?></td>
                                <td><span class="badge-vd badge-<?= getStatusBadge($test['status'] ?? 'pending') ?>-vd"><?= ucfirst($test['status'] ?? 'Pending') ?></span></td>
                                <td><?= htmlspecialchars($test['technician_name'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                                <td>
                                    <div class="action-btns-vd">
                                        <a href="view_lab_test.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action-vd view"><i class="fas fa-eye"></i> View</a>
                                        <a href="edit_lab_test.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action-vd edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button onclick="deleteItemVd('lab_test', <?= $test['id'] ?>)" class="btn-action-vd delete"><i class="fas fa-trash"></i> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--page-text-secondary);">No lab tests found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. PRESCRIPTIONS -->
    <div class="card-clean-vd">
        <div class="section-header-clean-vd">
            <h4 class="section-title-vd"><i class="fas fa-prescription"></i> Prescriptions</h4>
            <span class="section-badge-vd"><?= count($all_prescriptions) ?></span>
        </div>
        <div class="table-container-vd">
            <table class="data-table-vd">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>Prescription #</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_prescriptions) > 0): ?>
                        <?php $i = 1; foreach ($all_prescriptions as $presc): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($presc['patient_name'] ?? 'N/A') ?></td>
                                <td style="font-family:monospace;font-size:0.7rem;"><?= htmlspecialchars($presc['prescription_number'] ?? 'N/A') ?></td>
                                <td><span class="badge-vd badge-info-vd"><?= $presc['item_count'] ?? 0 ?></span></td>
                                <td><span class="badge-vd badge-<?= getStatusBadge($presc['status'] ?? 'pending') ?>-vd"><?= ucfirst($presc['status'] ?? 'Pending') ?></span></td>
                                <td><?= date('M d, Y', strtotime($presc['created_at'])) ?></td>
                                <td>
                                    <div class="action-btns-vd">
                                        <a href="view_prescription.php?id=<?= $presc['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action-vd view"><i class="fas fa-eye"></i> View</a>
                                        <a href="edit_prescription.php?id=<?= $presc['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action-vd edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button onclick="deleteItemVd('prescription', <?= $presc['id'] ?>)" class="btn-action-vd delete"><i class="fas fa-trash"></i> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--page-text-secondary);">No prescriptions found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 5. PROCEDURES & TOOLS -->
    <div class="card-clean-vd">
        <div class="section-header-clean-vd">
            <h4 class="section-title-vd"><i class="fas fa-syringe"></i> Procedures & Tools</h4>
            <span class="section-badge-vd"><?= count($all_procedures) ?></span>
        </div>
        <div class="table-container-vd">
            <table class="data-table-vd">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_procedures) > 0): ?>
                        <?php $i = 1; foreach ($all_procedures as $proc): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($proc['patient_name'] ?? 'N/A') ?></td>
                                <td><strong><?= htmlspecialchars($proc['item_name']) ?></strong></td>
                                <td><?= ucfirst($proc['item_type'] ?? 'N/A') ?></td>
                                <td><?= $proc['quantity'] ?? 1 ?></td>
                                <td style="font-weight:700;">TSh <?= number_format($proc['total_price'] ?? 0, 0) ?></td>
                                <td>
                                    <div class="action-btns-vd">
                                        <a href="view_bill_item.php?id=<?= $proc['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action-vd view"><i class="fas fa-eye"></i> View</a>
                                        <a href="edit_bill_item.php?id=<?= $proc['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action-vd edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button onclick="deleteItemVd('bill_item', <?= $proc['id'] ?>)" class="btn-action-vd delete"><i class="fas fa-trash"></i> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--page-text-secondary);">No procedures/tools found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 6. ALL BILLS -->
    <div class="card-clean-vd">
        <div class="section-header-clean-vd">
            <h4 class="section-title-vd"><i class="fas fa-file-invoice"></i> All Bills</h4>
            <span class="section-badge-vd"><?= count($all_bills) ?></span>
        </div>
        <div class="table-container-vd">
            <table class="data-table-vd">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Bill #</th>
                        <th>Patient</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_bills) > 0): ?>
                        <?php $i = 1; foreach ($all_bills as $bill): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td style="font-family:monospace;font-size:0.7rem;"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></td>
                                <td style="font-weight:700;">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                                <td style="color:#059669;">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                <td style="color:<?= ($bill['balance'] ?? 0) > 0 ? '#DC2626' : '#059669' ?>;">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></td>
                                <td><span class="badge-vd badge-<?= getStatusBadge($bill['status'] ?? 'pending') ?>-vd"><?= ucfirst($bill['status'] ?? 'Pending') ?></span></td>
                                <td>
                                    <div class="action-btns-vd">
                                        <a href="view_bill.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action-vd view"><i class="fas fa-eye"></i> View</a>
                                        <a href="edit_bill.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-action-vd edit"><i class="fas fa-edit"></i> Edit</a>
                                        <button onclick="deleteItemVd('bill', <?= $bill['id'] ?>)" class="btn-action-vd delete"><i class="fas fa-trash"></i> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--page-text-secondary);">No bills found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer-vd">
        <p>
            <span class="footer-brand-vd">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Doctor Dashboard - <?= htmlspecialchars($doctor['full_name']) ?>
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

    // ================================================================
    // DEACTIVATE DOCTOR
    // ================================================================
    function deactivateDoctorVd(doctorId) {
        if (confirm('⚠️ Are you sure you want to DEACTIVATE this doctor?\n\nThis action can be reversed later.')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_user.php';
            form.innerHTML = `
                <input type="hidden" name="user_id" value="${doctorId}">
                <input type="hidden" name="action" value="deactivate">
                <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // ================================================================
    // DELETE ITEM
    // ================================================================
    function deleteItemVd(type, id) {
        var typeLabels = {
            'patient': 'patient', 'visit': 'visit', 'lab_test': 'lab test',
            'prescription': 'prescription', 'bill_item': 'procedure/tool', 'bill': 'bill'
        };
        var label = typeLabels[type] || 'item';
        
        if (confirm('⚠️ Are you sure you want to DELETE this ' + label + '?\n\nThis action CANNOT be undone!')) {
            window.location.href = 'delete_item.php?type=' + type + '&id=' + id + '&branch=<?= $selected_branch_id ?>&doctor_id=<?= $doctor_id ?>';
        }
    }

    // ================================================================
    // CHART
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('visitsChart')?.getContext('2d');
        if (ctx && typeof Chart !== 'undefined') {
            var labels = <?= json_encode($chart_labels) ?>;
            var values = <?= json_encode($chart_values) ?>;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Visits',
                        data: values,
                        backgroundColor: '#0B5ED7',
                        borderColor: '#0A4CA8',
                        borderWidth: 1,
                        borderRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.05)' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    });

    console.log('%c👨‍⚕️ Braick - View Doctor Complete Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ NO duplicate header JavaScript', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 Doctor: <?= htmlspecialchars($doctor['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Patients: <?= number_format($total_patients) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Total Visits: <?= number_format($total_visits) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>