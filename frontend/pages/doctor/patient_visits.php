<?php
// ================================================================
// FILE: frontend/pages/doctor/patient_visits.php
// DOCTOR - VIEW ALL PATIENT VISITS (REDESIGNED)
// WITH SAME DESIGN AS visits.php
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET DOCTOR INFO FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET PATIENT ID
// ================================================================
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    header('Location: my_patients.php');
    exit;
}

// ================================================================
// FUNCTIONS
// ================================================================
function time_ago($timestamp) {
    if (empty($timestamp)) return 'N/A';
    $time = strtotime($timestamp);
    if ($time === false) return 'N/A';
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
    return date('M d, Y', $time);
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777', '#2563EB', '#0891B2'];
    $index = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $index = ($index + ord($name[$i])) % count($colors);
    }
    return $colors[$index];
}

function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'assigned' => 'badge-info',
        'with_doctor' => 'badge-warning',
        'lab_test' => 'badge-warning',
        'lab_completed' => 'badge-info',
        'prescribed' => 'badge-purple',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-info';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'assigned' => '👨‍⚕️ Assigned',
        'with_doctor' => '🩺 With Doctor',
        'lab_test' => '🧪 Lab Test',
        'lab_completed' => '✅ Lab Done',
        'prescribed' => '💊 Prescribed',
        'completed' => '✅ Completed',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// GET PATIENT DETAILS
// ================================================================
if ($is_admin) {
    $stmt = $db->prepare("
        SELECT p.*, b.name as branch_name, u.full_name as doctor_name
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$patient_id]);
} else {
    $stmt = $db->prepare("
        SELECT p.*, b.name as branch_name, u.full_name as doctor_name
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        WHERE p.id = ? AND p.assigned_doctor_id = ?
    ");
    $stmt->execute([$patient_id, $doctor_id]);
}
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: my_patients.php?error=patient_not_found');
    exit;
}

// ================================================================
// GET ALL VISITS FOR THIS PATIENT
// ================================================================
if ($is_admin) {
    $stmt = $db->prepare("
        SELECT v.*, 
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               r.full_name as receptionist_name,
               d.disease_name,
               d.disease_code,
               (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id) as prescriptions_count,
               (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id) as lab_tests_count,
               (SELECT COUNT(*) FROM procedures WHERE visit_id = v.id) as procedures_count,
               (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status != 'cancelled') as bills_count
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        LEFT JOIN diseases d ON v.disease_id = d.id
        WHERE v.patient_id = ?
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$patient_id]);
} else {
    $stmt = $db->prepare("
        SELECT v.*, 
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               r.full_name as receptionist_name,
               d.disease_name,
               d.disease_code,
               (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id) as prescriptions_count,
               (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id) as lab_tests_count,
               (SELECT COUNT(*) FROM procedures WHERE visit_id = v.id) as procedures_count,
               (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status != 'cancelled') as bills_count
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        LEFT JOIN diseases d ON v.disease_id = d.id
        WHERE v.patient_id = ? AND v.doctor_id = ?
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$patient_id, $doctor_id]);
}
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$total_visits = count($visits);
$completed_visits = 0;
$pending_visits = 0;
$prescribed_visits = 0;
$lab_tests_total = 0;
$prescriptions_total = 0;
$procedures_total = 0;
$bills_total = 0;

foreach ($visits as $visit) {
    if ($visit['status'] === 'completed') $completed_visits++;
    if ($visit['status'] === 'pending' || $visit['status'] === 'assigned' || $visit['status'] === 'with_doctor' || $visit['status'] === 'lab_test') $pending_visits++;
    if ($visit['status'] === 'prescribed') $prescribed_visits++;
    $lab_tests_total += $visit['lab_tests_count'] ?? 0;
    $prescriptions_total += $visit['prescriptions_count'] ?? 0;
    $procedures_total += $visit['procedures_count'] ?? 0;
    $bills_total += $visit['bills_count'] ?? 0;
}

// ================================================================
// GET BRANCH NAME
// ================================================================
$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $doctor_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $doctor_branch_name = 'Branch';
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE DOCTOR HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Visits - <?= htmlspecialchars($patient['full_name']) ?> - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BLUE THEME (Same as visits.php)
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
            --bg-body: #F1F5F9;
            --bg-card: #ffffff;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 30px rgba(0,0,0,0.4);
        }
        
        * { box-sizing: border-box; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
            padding: 20px 24px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border-bottom: 3px solid var(--primary);
            box-shadow: var(--shadow);
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .page-title i { color: var(--primary); }
        .page-badge {
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 4px 16px;
            border-radius: 20px;
            font-family: monospace;
        }
        .page-badge.admin { background: #DC2626; color: white; }
        .page-subtitle {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 6px;
        }
        
        .branch-tag {
            background: var(--success);
            color: #fff;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .admin-tag {
            background: #DC2626;
            color: #fff;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        /* ================================================================
           PATIENT INFO CARD
           ================================================================ */
        .patient-info-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            transition: all 0.3s ease;
        }
        
        .patient-info-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .patient-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }
        
        .patient-info-details { flex: 1; }
        .patient-info-details .name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .patient-info-details .details {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 20px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        .patient-info-details .details i { width: 18px; color: var(--primary); }
        
        .patient-info-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* ================================================================
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-number {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.2;
        }
        .stat-card .stat-number.green { color: var(--success); }
        .stat-card .stat-number.orange { color: var(--warning); }
        .stat-card .stat-number.purple { color: var(--purple); }
        .stat-card .stat-number.teal { color: #0D9488; }
        .stat-card .stat-number.red { color: var(--danger); }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-top: 2px;
        }
        
        .stat-card .stat-icon {
            font-size: 1.2rem;
            margin-bottom: 4px;
        }
        
        [data-theme="dark"] .stat-card {
            background: #1E293B;
            border-color: #334155;
        }
        [data-theme="dark"] .stat-card:hover { border-color: var(--primary); }
        [data-theme="dark"] .stat-card .stat-number { color: #6EA8FE; }
        [data-theme="dark"] .stat-card .stat-number.green { color: #34D399; }
        [data-theme="dark"] .stat-card .stat-number.orange { color: #FBBF24; }
        
        /* ================================================================
           TABLE CARD
           ================================================================ */
        .table-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .table-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .table-wrap {
            overflow-x: auto;
            padding: 0;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 700px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #fff;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table tbody tr {
            transition: background 0.2s ease;
        }
        
        .data-table tbody tr:nth-child(even) {
            background: var(--gray-50);
        }
        .data-table tbody tr:nth-child(odd) {
            background: var(--bg-card);
        }
        .data-table tbody tr:hover {
            background: var(--primary-bg);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) {
            background: #1E293B;
        }
        [data-theme="dark"] .data-table tbody tr:nth-child(odd) {
            background: #0F172A;
        }
        [data-theme="dark"] .data-table tbody tr:hover {
            background: #1A3A5F;
        }
        
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .badge-status {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 3px 12px;
            border-radius: 12px;
            display: inline-block;
        }
        
        .badge-status.badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-status.badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-status.badge-success { background: var(--success-bg); color: var(--success); }
        .badge-status.badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-status.badge-purple { background: var(--purple-bg); color: var(--purple); }
        
        /* ================================================================
           AVATAR
           ================================================================ */
        .avatar-sm {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.7rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-blue {
            background: var(--primary);
            color: #fff;
        }
        .btn-blue:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
        
        .btn-green {
            background: var(--success);
            color: #fff;
        }
        .btn-green:hover {
            background: var(--success-dark);
            transform: scale(1.05);
        }
        
        .btn-view {
            background: var(--primary);
            color: #fff;
        }
        .btn-view:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
            transform: scale(1.05);
        }
        
        .btn-sm {
            padding: 4px 10px;
            font-size: 0.65rem;
            border-radius: 6px;
        }
        
        .action-buttons {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: nowrap;
            justify-content: center;
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .empty-state p {
            font-size: 0.85rem;
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        .text-gray-300 { color: var(--gray-300); }
        .text-gray-400 { color: var(--gray-400); }
        .text-muted { color: var(--text-secondary); }
        .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }
        .ml-2 { margin-left: 0.5rem; }
        .mr-1 { margin-right: 0.25rem; }
        .mr-2 { margin-right: 0.5rem; }
        .mt-2 { margin-top: 0.5rem; }
        .mb-3 { margin-bottom: 0.75rem; }
        .mb-5 { margin-bottom: 1.25rem; }
        .text-xs { font-size: 0.75rem; }
        .text-sm { font-size: 0.85rem; }
        .text-center { text-align: center; }
        .font-medium { font-weight: 500; }
        .font-mono { font-family: monospace; }
        .capitalize { text-transform: capitalize; }
        .block { display: block; }
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-2 { gap: 0.5rem; }
        .gap-3 { gap: 0.75rem; }
        .gap-4 { gap: 1rem; }
        .py-8 { padding-top: 2rem; padding-bottom: 2rem; }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: 10px;
            z-index: 9999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .data-table {
                font-size: 0.75rem;
                min-width: 600px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            .patient-info-card {
                flex-direction: column;
                text-align: center;
            }
            .patient-info-details .details {
                justify-content: center;
            }
            .patient-info-actions {
                justify-content: center;
                width: 100%;
            }
            .data-table {
                font-size: 0.7rem;
                min-width: 500px;
            }
            .data-table th,
            .data-table td {
                padding: 6px 10px;
            }
            .action-buttons {
                flex-wrap: wrap;
            }
            .action-buttons .btn {
                flex: 1;
                justify-content: center;
            }
            .btn-sm {
                padding: 3px 8px;
                font-size: 0.6rem;
            }
            .avatar-sm {
                width: 28px;
                height: 28px;
                font-size: 0.7rem;
            }
            .page-title {
                font-size: 1.2rem;
            }
            .stat-card .stat-number {
                font-size: 1.2rem;
            }
            .page-header {
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 12px; }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            .data-table {
                font-size: 0.65rem;
                min-width: 420px;
            }
            .data-table th,
            .data-table td {
                padding: 4px 8px;
            }
            .action-buttons {
                flex-direction: column;
                gap: 2px;
            }
            .action-buttons .btn {
                width: 100%;
            }
            .patient-avatar {
                width: 48px;
                height: 48px;
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clinic-medical"></i> Patient Visits
                <?php if ($is_admin): ?>
                    <span class="page-badge admin">👑 Admin Mode</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                View all visits for this patient
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-list mr-1"></i> <?= count($visits) ?> visits
                </span>
                <?php if ($is_admin): ?>
                    <span class="ml-2 admin-tag">
                        <i class="fas fa-user-shield mr-1"></i> Admin View
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <span class="text-sm text-gray-500 flex items-center">
                <i class="fas fa-user-md mr-1"></i> <?= htmlspecialchars($doctor_name) ?>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFO CARD -->
    <!-- ================================================================ -->
    <div class="patient-info-card">
        <div class="patient-avatar" style="background: <?= getUserColor($patient['full_name'] ?? 'Unknown') ?>;">
            <?= strtoupper(substr($patient['full_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="patient-info-details">
            <div class="name"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></div>
            <div class="details">
                <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                <span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                <span><i class="fas fa-birthday-cake"></i> <?= calculateAge($patient['date_of_birth'] ?? '') ?> years</span>
                <span><i class="fas fa-tint"></i> <?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
                <span><i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span>
                <?php if (!empty($patient['allergies'])): ?>
                    <span style="color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($patient['allergies']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="patient-info-actions">
            <a href="patient_details.php?id=<?= $patient['id'] ?>" class="btn btn-blue btn-sm">
                <i class="fas fa-user"></i> Details
            </a>
            <a href="my_patients.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📋</div>
            <p class="stat-number"><?= $total_visits ?></p>
            <p class="stat-label">Total Visits</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <p class="stat-number green"><?= $completed_visits ?></p>
            <p class="stat-label">Completed</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <p class="stat-number orange"><?= $pending_visits ?></p>
            <p class="stat-label">Pending</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💊</div>
            <p class="stat-number purple"><?= $prescriptions_total ?></p>
            <p class="stat-label">Prescriptions</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🧪</div>
            <p class="stat-number teal"><?= $lab_tests_total ?></p>
            <p class="stat-label">Lab Tests</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <p class="stat-number green"><?= $bills_total ?></p>
            <p class="stat-label">Bills</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISITS TABLE -->
    <!-- ================================================================ -->
    <div class="table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Visit #</th>
                        <th>Doctor</th>
                        <th>Type</th>
                        <th>Diagnosis</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($visits) > 0): ?>
                        <?php foreach ($visits as $index => $visit): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <span class="font-mono text-xs font-bold" style="color:var(--primary);">
                                        <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                                    </span>
                                    <?php if (($visit['bills_count'] ?? 0) > 0): ?>
                                        <div class="text-xs text-muted">
                                            <i class="fas fa-receipt"></i> <?= $visit['bills_count'] ?> bills
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($visit['doctor_name']): ?>
                                        <div class="text-sm">Dr. <?= htmlspecialchars($visit['doctor_name']) ?></div>
                                        <div class="text-xs text-muted"><?= htmlspecialchars($visit['doctor_specialty'] ?? 'GP') ?></div>
                                    <?php else: ?>
                                        <span class="text-xs text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-xs capitalize">
                                        <?= ucfirst($visit['visit_type'] ?? 'N/A') ?>
                                    </span>
                                    <?php if (!empty($visit['consultation_fee']) && $visit['consultation_fee'] > 0): ?>
                                        <div class="text-xs text-muted">TSh <?= number_format($visit['consultation_fee'], 0) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($visit['disease_name']) || !empty($visit['diagnosis'])): ?>
                                        <div class="text-sm font-semibold" style="color:var(--primary);">
                                            <?= htmlspecialchars($visit['disease_name'] ?? $visit['diagnosis'] ?? 'N/A') ?>
                                        </div>
                                        <?php if (!empty($visit['disease_code'])): ?>
                                            <div class="text-xs text-muted"><?= htmlspecialchars($visit['disease_code']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-muted">No diagnosis</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= getStatusBadgeClass($visit['status']) ?>">
                                        <?= getStatusLabel($visit['status'] ?? 'pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs">
                                    <?= isset($visit['created_at']) ? date('M d, Y h:i A', strtotime($visit['created_at'])) : 'N/A' ?>
                                    <?php if (!empty($visit['completed_at'])): ?>
                                        <div class="text-xs text-muted">Completed: <?= date('M d, Y', strtotime($visit['completed_at'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_visit.php?id=<?= $visit['id'] ?>" class="btn btn-view btn-sm" title="View Visit">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($visit['status'] !== 'completed' && $visit['status'] !== 'cancelled'): ?>
                                            <a href="consultation.php?visit_id=<?= $visit['id'] ?>" class="btn btn-blue btn-sm" title="Continue Consultation">
                                                <i class="fas fa-stethoscope"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8">
                                <div class="empty-state">
                                    <i class="fas fa-clinic-medical"></i>
                                    <h4>No visits found</h4>
                                    <p>This patient has no visits recorded yet</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Patient Visits - <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>
            <?php if ($is_admin): ?>
                <span class="text-gray-300 mx-2">|</span>
                <span style="color:#DC2626;">👑 Admin Mode</span>
            <?php endif; ?>
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle" style="font-weight:600;font-size:0.85rem;margin:0;">Notification</p>
        <p id="toastMessage" style="font-size:0.75rem;opacity:0.9;margin:0;"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom';
        if (type === 'success') { toast.style.background = '#059669'; }
        else if (type === 'error') { toast.style.background = '#DC2626'; }
        else { toast.style.background = '#0B5ED7'; }
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        setTimeout(function() { toast.classList.add('show'); }, 50);
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 4000);
    }

    console.log('%c🏥 Braick - Patient Visits', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User ID: <?= $doctor_id ?> | Role: <?= $_SESSION['role'] ?>', 'font-size:12px; color:#64748B;');
    <?php if ($is_admin): ?>
    console.log('%c👑 Admin Mode - Viewing All Visits', 'font-size:12px; color:#DC2626;');
    <?php endif; ?>
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Visits: <?= count($visits) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Using Actual Database: visits, patients, users, diseases', 'font-size:12px; color:#7C3AED;');
</script>

</body>
</html>