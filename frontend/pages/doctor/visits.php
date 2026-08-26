<?php
// ================================================================
// FILE: frontend/pages/doctor/visits.php
// DOCTOR - VISITS LIST (FILTERED BY STATUS)
// BRAICK DISPENSARY - USING ACTUAL DATABASE
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

// ================================================================
// INCLUDE DATABASE - CORRECT PATH
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// GET FILTERS
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'today';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// BUILD QUERY - USING ACTUAL DATABASE COLUMNS
// ================================================================
if ($is_admin) {
    // Admin can see all visits
    $sql = "
        SELECT 
            v.id,
            v.visit_number,
            v.visit_date,
            v.patient_id,
            v.doctor_id,
            v.receptionist_id,
            v.branch_id,
            v.visit_type,
            v.consultation_fee,
            v.status,
            v.symptoms,
            v.hpi,
            v.physical_exam,
            v.complaint,
            v.diagnosis,
            v.disease_id,
            v.disease_code,
            v.treatment,
            v.notes,
            v.created_at,
            v.updated_at,
            v.is_completed,
            v.completed_at,
            p.full_name as patient_name, 
            p.patient_id as patient_code, 
            p.phone,
            p.gender,
            p.date_of_birth,
            p.blood_group,
            p.address,
            u.full_name as doctor_name,
            u.specialty,
            b.name as branch_name
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE 1=1
    ";
    $params = [];
} else {
    // Doctor can only see their own visits
    $sql = "
        SELECT 
            v.id,
            v.visit_number,
            v.visit_date,
            v.patient_id,
            v.doctor_id,
            v.receptionist_id,
            v.branch_id,
            v.visit_type,
            v.consultation_fee,
            v.status,
            v.symptoms,
            v.hpi,
            v.physical_exam,
            v.complaint,
            v.diagnosis,
            v.disease_id,
            v.disease_code,
            v.treatment,
            v.notes,
            v.created_at,
            v.updated_at,
            v.is_completed,
            v.completed_at,
            p.full_name as patient_name, 
            p.patient_id as patient_code, 
            p.phone,
            p.gender,
            p.date_of_birth,
            p.blood_group,
            p.address,
            u.full_name as doctor_name,
            u.specialty,
            b.name as branch_name
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.doctor_id = ?
    ";
    $params = [$doctor_id];
}

// Filter by status
if (!empty($status_filter)) {
    $sql .= " AND v.status = ?";
    $params[] = $status_filter;
}

// Filter: Today
if ($filter === 'today') {
    $sql .= " AND DATE(v.created_at) = CURDATE()";
}

// Filter: Pending (all pending statuses)
if ($filter === 'pending') {
    $sql .= " AND v.status IN ('pending', 'assigned', 'with_doctor', 'lab_test')";
}

// Filter: Completed
if ($filter === 'completed') {
    $sql .= " AND v.status = 'completed'";
}

// Filter: All (no date filter)
if ($filter === 'all') {
    // No date filter - show all
}

// Search
if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ? OR v.visit_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY v.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATUS COUNTS - USING ACTUAL DATABASE
// ================================================================
$status_counts = [];
$statuses = ['pending', 'assigned', 'with_doctor', 'lab_test', 'lab_completed', 'prescribed', 'completed', 'cancelled'];

if ($is_admin) {
    // Admin counts - all visits
    foreach ($statuses as $status) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE status = ?");
        $stmt->execute([$status]);
        $status_counts[$status] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    }

    // Today count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Pending count (all pending statuses)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE status IN ('pending', 'assigned', 'with_doctor', 'lab_test')");
    $stmt->execute();
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Completed count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE status = 'completed'");
    $stmt->execute();
    $completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} else {
    // Doctor counts - only their visits
    foreach ($statuses as $status) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status = ?");
        $stmt->execute([$doctor_id, $status]);
        $status_counts[$status] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    }

    // Today count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$doctor_id]);
    $today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Pending count (all pending statuses)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status IN ('pending', 'assigned', 'with_doctor', 'lab_test')");
    $stmt->execute([$doctor_id]);
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Completed count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status = 'completed'");
    $stmt->execute([$doctor_id]);
    $completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

// ================================================================
// GET DOCTOR'S BRANCH NAME
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
// INCLUDE DOCTOR HEADER & SIDEBAR - CORRECT PATHS
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visits - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BLUE THEME
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
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
            text-decoration: none;
            cursor: pointer;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: white;
            flex-shrink: 0;
        }
        
        .stat-card.blue .stat-icon { background: var(--primary); }
        .stat-card.yellow .stat-icon { background: var(--warning); }
        .stat-card.green .stat-icon { background: var(--success); }
        .stat-card.purple .stat-icon { background: var(--purple); }
        .stat-card.red .stat-icon { background: var(--danger); }
        
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-card.active {
            border-color: var(--primary);
            background: var(--primary-bg);
        }
        
        [data-theme="dark"] .stat-card.active {
            background: #1E3A5F;
            border-color: #6EA8FE;
        }
        
        /* ================================================================
           FILTER CARD
           ================================================================ */
        .filter-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }
        
        .filter-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            width: 100%;
        }
        
        .filter-search {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 200px;
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            transition: all 0.3s;
            padding: 0 12px;
        }
        
        .filter-search:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .filter-search .fa-search {
            color: var(--text-secondary);
            font-size: 0.85rem;
            opacity: 0.5;
        }
        
        .filter-input {
            border: none;
            background: transparent;
            padding: 10px 12px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .filter-input::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        .filter-select {
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.85rem;
            background: var(--bg-body);
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 140px;
        }
        
        .filter-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        /* ================================================================
           TABLE
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
        
        .btn-consult {
            background: var(--purple);
            color: #fff;
        }
        .btn-consult:hover {
            background: #6D28D9;
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
                grid-template-columns: repeat(2, 1fr);
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
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-search {
                min-width: 100%;
            }
            .filter-select {
                width: 100%;
            }
            .filter-form .btn {
                width: 100%;
                justify-content: center;
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
                grid-template-columns: 1fr;
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
                <i class="fas fa-clinic-medical"></i> Visits
                <?php if ($is_admin): ?>
                    <span class="page-badge admin">👑 Admin Mode</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                View all patient visits
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
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <a href="?filter=today" class="stat-card blue <?= $filter === 'today' ? 'active' : '' ?>">
            <div>
                <p class="stat-label">Today</p>
                <p class="stat-number"><?= $today_count ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
        </a>
        
        <a href="?filter=pending" class="stat-card yellow <?= $filter === 'pending' ? 'active' : '' ?>">
            <div>
                <p class="stat-label">Pending</p>
                <p class="stat-number"><?= $pending_count ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </a>
        
        <a href="?filter=completed" class="stat-card green <?= $filter === 'completed' ? 'active' : '' ?>">
            <div>
                <p class="stat-label">Completed</p>
                <p class="stat-number"><?= $completed_count ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </a>
        
        <a href="?filter=all" class="stat-card purple <?= $filter === 'all' ? 'active' : '' ?>">
            <div>
                <p class="stat-label">All Visits</p>
                <p class="stat-number"><?= count($visits) ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-list"></i></div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- SEARCH & FILTER -->
    <!-- ================================================================ -->
    <div class="filter-card">
        <form method="GET" class="filter-form">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            
            <div class="filter-group">
                <div class="filter-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" class="filter-input" placeholder="Search by patient name, ID, phone or visit number..." value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                    <option value="assigned" <?= $status_filter === 'assigned' ? 'selected' : '' ?>>👨‍⚕️ Assigned</option>
                    <option value="with_doctor" <?= $status_filter === 'with_doctor' ? 'selected' : '' ?>>🩺 With Doctor</option>
                    <option value="lab_test" <?= $status_filter === 'lab_test' ? 'selected' : '' ?>>🧪 Lab Test</option>
                    <option value="lab_completed" <?= $status_filter === 'lab_completed' ? 'selected' : '' ?>>✅ Lab Done</option>
                    <option value="prescribed" <?= $status_filter === 'prescribed' ? 'selected' : '' ?>>💊 Prescribed</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>✅ Completed</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                </select>
                
                <button type="submit" class="btn btn-blue">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search) || !empty($status_filter)): ?>
                    <a href="?filter=<?= htmlspecialchars($filter) ?>" class="btn btn-outline">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
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
                        <th>Patient</th>
                        <th>Patient ID</th>
                        <th>Phone</th>
                        <th>Doctor</th>
                        <th>Visit Type</th>
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
                                    <div class="flex items-center gap-3">
                                        <div class="avatar-sm" style="background: <?= getUserColor($visit['patient_name']) ?>;">
                                            <?= strtoupper(substr($visit['patient_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-medium"><?= htmlspecialchars($visit['patient_name']) ?></div>
                                            <div class="text-xs text-muted"><?= htmlspecialchars($visit['gender'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="font-mono"><?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($visit['doctor_name']): ?>
                                        <div class="text-sm">Dr. <?= htmlspecialchars($visit['doctor_name']) ?></div>
                                        <div class="text-xs text-muted"><?= htmlspecialchars($visit['specialty'] ?? 'GP') ?></div>
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
                                        <a href="view_patient.php?id=<?= $visit['patient_id'] ?>" class="btn btn-blue btn-sm" title="View Patient">
                                            <i class="fas fa-user"></i>
                                        </a>
                                        <?php if ($visit['status'] !== 'completed' && $visit['status'] !== 'cancelled'): ?>
                                            <a href="consultation.php?visit_id=<?= $visit['id'] ?>" class="btn btn-consult btn-sm" title="Consult">
                                                <i class="fas fa-stethoscope"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-8">
                                <div class="empty-state">
                                    <i class="fas fa-clinic-medical"></i>
                                    <h4>No visits found</h4>
                                    <p>
                                        <?php if (!empty($search)): ?>
                                            No results for "<strong><?= htmlspecialchars($search) ?></strong>"
                                        <?php elseif ($filter === 'today'): ?>
                                            No visits for today
                                        <?php elseif ($filter === 'pending'): ?>
                                            No pending visits
                                        <?php elseif ($filter === 'completed'): ?>
                                            No completed visits
                                        <?php else: ?>
                                            No visits recorded yet
                                        <?php endif; ?>
                                    </p>
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
            Visits
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

    console.log('%c🏥 Braick - Visits List', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User ID: <?= $doctor_id ?> | Role: <?= $_SESSION['role'] ?>', 'font-size:12px; color:#64748B;');
    <?php if ($is_admin): ?>
    console.log('%c👑 Admin Mode - Viewing All Visits', 'font-size:12px; color:#DC2626;');
    <?php endif; ?>
    console.log('%c📊 Total Visits: <?= count($visits) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔄 Filter: <?= ucfirst($filter) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Using Actual Database: visits, patients, users, branches', 'font-size:12px; color:#7C3AED;');
</script>

</body>
</html>