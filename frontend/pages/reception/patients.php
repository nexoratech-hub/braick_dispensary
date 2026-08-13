<?php
// ================================================================
// FILE: frontend/pages/reception/patients.php
// RECEPTION - PATIENT MANAGEMENT
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
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
// CHECK IF USER HAS ACCESS (Reception or Admin)
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'reception';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// GET PARAMETERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$selected_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

$error = isset($_GET['error']) ? $_GET['error'] : '';
$success = isset($_GET['success']) ? $_GET['success'] : '';

try {
    $db = Database::getInstance()->getConnection();
    
    // ✅ Get ALL patients with filters (including those without doctor)
    $sql = "
        SELECT p.*, 
               u.full_name as assigned_doctor_name,
               (SELECT COUNT(*) FROM visits WHERE patient_id = p.id) as total_visits,
               (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND status IN ('scheduled', 'confirmed')) as active_appointments,
               DATEDIFF(NOW(), p.created_at) as patient_days,
               CASE 
                   WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'new'
                   ELSE 'existing'
               END as patient_status
        FROM patients p
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        WHERE p.branch_id = ? 
    ";
    $params = [$branch_id];
    
    if (!empty($search)) {
        $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($filter === 'with_doctor') {
        $sql .= " AND p.assigned_doctor_id IS NOT NULL";
    } elseif ($filter === 'without_doctor') {
        $sql .= " AND p.assigned_doctor_id IS NULL";
    } elseif ($filter === 'new') {
        $sql .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($filter === 'no_visit') {
        $sql .= " AND NOT EXISTS (SELECT 1 FROM visits WHERE patient_id = p.id)";
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ✅ Get total count for pagination
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM patients p
        WHERE p.branch_id = ?
    ";
    $count_params = [$branch_id];
    
    if (!empty($search)) {
        $count_sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
        $count_params[] = $search_param;
        $count_params[] = $search_param;
        $count_params[] = $search_param;
    }
    
    if ($filter === 'with_doctor') {
        $count_sql .= " AND p.assigned_doctor_id IS NOT NULL";
    } elseif ($filter === 'without_doctor') {
        $count_sql .= " AND p.assigned_doctor_id IS NULL";
    } elseif ($filter === 'new') {
        $count_sql .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($filter === 'no_visit') {
        $count_sql .= " AND NOT EXISTS (SELECT 1 FROM visits WHERE patient_id = p.id)";
    }
    
    $stmt = $db->prepare($count_sql);
    $stmt->execute($count_params);
    $total_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_patients = $total_result['total'] ?? 0;
    $total_pages = ceil($total_patients / $limit);
    
    // ✅ Get stats - COUNT ALL PATIENTS
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN assigned_doctor_id IS NOT NULL THEN 1 ELSE 0 END) as with_doctor,
            SUM(CASE WHEN assigned_doctor_id IS NULL THEN 1 ELSE 0 END) as without_doctor,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_patients
        FROM patients 
        WHERE branch_id = ?
    ");
    $stmt->execute([$branch_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ✅ Get latest visits for each patient
    foreach ($patients as &$patient) {
        $stmt = $db->prepare("
            SELECT id, visit_number, status, created_at 
            FROM visits 
            WHERE patient_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$patient['id']]);
        $patient['latest_visit'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $patients = [];
    $stats = ['total' => 0, 'with_doctor' => 0, 'without_doctor' => 0, 'new_patients' => 0];
    $total_patients = 0;
    $total_pages = 0;
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_notifications = $stmt->fetch()['total'] ?? 0;
    }
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563EB;
            --primary-dark: #1D4ED8;
            --primary-light: #60A5FA;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
            
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            
            --purple: #7C3AED;
            --purple-dark: #5B21B6;
            --purple-light: #A78BFA;
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
            
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 30px rgba(0,0,0,0.12);
            
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-bg: #1E3A5F;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --purple-bg: #2D1B5F;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            transform: scale(1.02);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .datetime i {
            color: var(--primary-light);
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* Page Header */
        .page-header {
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(37, 99, 235, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
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
        
        .page-header::after {
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
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .role-badge-display {
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
        
        .page-header .header-badge {
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
            transition: all 0.3s ease;
        }
        
        .page-header .header-badge:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: var(--radius);
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
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* Stats Cards */
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-card .stat-number.green { color: var(--success); }
        .stat-card .stat-number.orange { color: var(--warning); }
        .stat-card .stat-number.purple { color: var(--purple); }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        /* Table Card */
        .table-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
        }
        
        .table-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-lg);
        }
        
        .table-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .table-card .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-card .card-title i {
            color: var(--primary);
        }
        
        /* ✅ TABLE HEADERS - BLUE BACKGROUND */
        .patient-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        .patient-table thead {
            background: var(--primary-gradient);
            color: white;
        }
        
        .patient-table thead th {
            padding: 12px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            border-bottom: none;
        }
        
        .patient-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .patient-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .patient-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .patient-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        
        .patient-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .patient-table .status-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 500;
            padding: 2px 10px;
            border-radius: 12px;
        }
        
        .patient-table .status-badge.new {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .patient-table .status-badge.existing {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .patient-table .status-badge.with_doctor {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .patient-table .status-badge.without_doctor {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        /* Days Badge - Blue */
        .days-badge-blue {
            display: inline-block;
            background: var(--primary) !important;
            color: #ffffff !important;
            padding: 2px 12px !important;
            border-radius: 12px !important;
            font-size: 0.6rem !important;
            font-weight: 600 !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
        .days-badge-blue.new {
            background: var(--success) !important;
            box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2);
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.78rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
        }
        .btn-sm { padding: 3px 10px; font-size: 0.7rem; border-radius: 6px; }
        .btn-purple {
            background: var(--purple);
            color: white;
        }
        .btn-purple:hover {
            background: var(--purple-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            gap: 4px;
            justify-content: center;
            margin-top: 16px;
        }
        .pagination .page-link {
            padding: 4px 12px;
            border-radius: 6px;
            border: 2px solid var(--border-color);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.78rem;
            transition: all 0.3s;
        }
        .pagination .page-link:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        .pagination .page-link.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        .pagination .page-link.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        /* Toast */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: var(--radius);
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* Footer */
        .footer-modern {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer-modern .footer-brand {
            color: var(--primary);
            font-weight: 500;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .table-card { padding: 16px; }
            .patient-table { font-size: 0.75rem; }
            .patient-table thead th, 
            .patient-table tbody td { padding: 6px 8px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .page-header .header-badge { font-size: 0.6rem; padding: 2px 10px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Search filter in header */
        .search-filter-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .search-filter-wrapper .filter-input {
            padding: 6px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.78rem;
            background: var(--bg-body);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
            min-width: 150px;
        }
        .search-filter-wrapper .filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .search-filter-wrapper .filter-input::placeholder {
            color: var(--text-secondary);
            opacity: 0.6;
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search patients..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime">
            <i class="fas fa-clock" style="color:var(--primary-light);"></i>
            <span id="clockDisplay" style="font-weight:500;"><?= date('d M Y • h:i:s A') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-users"></i>
                Patients
                <span class="role-badge-display">RECEPTION</span>
                <span class="update-badge-light">
                    <span class="live-indicator-modern" style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#34D399;animation:pulse-dot 1.5s infinite;margin-right:4px;"></span> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Manage patients in <strong><?= htmlspecialchars($branch_name) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-users"></i>
                    <span id="totalPatients"><?= $stats['total'] ?? 0 ?></span> Total
                </span>
                <span class="header-badge">
                    <i class="fas fa-user-md"></i>
                    <span class="online-count" style="color:#34D399;"><?= $stats['with_doctor'] ?? 0 ?></span> With Doctor
                </span>
                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    <span class="offline-count" style="color:#F87171;"><?= $stats['without_doctor'] ?? 0 ?></span> No Doctor
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-bolt"></i>
                    <?= $stats['new_patients'] ?? 0 ?> New (7 days)
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="new_patient.php" class="btn-outline-light">
                <i class="fas fa-plus"></i> New Patient
            </a>
            <a href="appointments.php" class="btn-outline-light">
                <i class="fas fa-calendar-alt"></i> Appointments
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5 animate-fade-in-up">
        <div class="stat-card">
            <p class="stat-number"><?= $stats['total'] ?? 0 ?></p>
            <p class="stat-label">Total Patients</p>
        </div>
        <div class="stat-card">
            <p class="stat-number green"><?= $stats['with_doctor'] ?? 0 ?></p>
            <p class="stat-label">With Doctor</p>
        </div>
        <div class="stat-card">
            <p class="stat-number orange"><?= $stats['without_doctor'] ?? 0 ?></p>
            <p class="stat-label">No Doctor</p>
        </div>
        <div class="stat-card">
            <p class="stat-number purple"><?= $stats['new_patients'] ?? 0 ?></p>
            <p class="stat-label">New (7 days)</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE CARD WITH HEADER FILTERS -->
    <!-- ================================================================ -->
    <div class="table-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-list"></i> Patient List
                <span class="card-badge" style="background:var(--primary-bg);color:var(--primary);padding:2px 12px;border-radius:20px;font-size:0.7rem;font-weight:500;"><?= $total_patients ?> patients</span>
            </div>
            
            <!-- ✅ SEARCH FILTER IN HEADER -->
            <div class="search-filter-wrapper">
                <form action="patients.php" method="GET" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                    <input type="text" name="search" class="filter-input" placeholder="Search by name, ID, phone..." value="<?= htmlspecialchars($search) ?>">
                    <select name="filter" class="filter-input" style="min-width:120px;padding:6px 12px;">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Patients</option>
                        <option value="new" <?= $filter === 'new' ? 'selected' : '' ?>>New (7 days)</option>
                        <option value="with_doctor" <?= $filter === 'with_doctor' ? 'selected' : '' ?>>With Doctor</option>
                        <option value="without_doctor" <?= $filter === 'without_doctor' ? 'selected' : '' ?>>No Doctor</option>
                        <option value="no_visit" <?= $filter === 'no_visit' ? 'selected' : '' ?>>No Visit</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <?php if (!empty($search) || $filter !== 'all'): ?>
                        <a href="patients.php" class="btn btn-outline btn-sm">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- PATIENT TABLE - BLUE HEADERS - SHOWS ALL PATIENTS -->
        <!-- ================================================================ -->
        <div style="overflow-x:auto;">
            <?php if (!empty($patients)): ?>
            <table class="patient-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-user mr-1"></i> Patient</th>
                        <th><i class="fas fa-id-card mr-1"></i> Patient ID</th>
                        <th><i class="fas fa-phone mr-1"></i> Contact</th>
                        <th><i class="fas fa-calendar mr-1"></i> Days</th>
                        <th><i class="fas fa-user-md mr-1"></i> Doctor</th>
                        <th><i class="fas fa-notes-medical mr-1"></i> Visits</th>
                        <th><i class="fas fa-circle mr-1"></i> Status</th>
                        <th><i class="fas fa-cog mr-1"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): 
                        $patient_days = isset($patient['patient_days']) ? (int)$patient['patient_days'] : 0;
                        $days_text = $patient_days > 0 ? '<span class="days-badge-blue">📅 ' . $patient_days . ' days</span>' : '<span class="days-badge-blue new">📅 New</span>';
                        
                        $status_class = $patient['patient_status'] ?? 'existing';
                        $status_text = $status_class === 'new' ? 'New' : 'Existing';
                        
                        // Show doctor name or "No Doctor" badge
                        if (!empty($patient['assigned_doctor_name'])) {
                            $doctor_status = '<span class="status-badge with_doctor">✅ Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) . '</span>';
                        } else {
                            $doctor_status = '<span class="status-badge without_doctor">⚠️ No Doctor</span>';
                        }
                        
                        $appointment_count = $patient['active_appointments'] ?? 0;
                    ?>
                        <tr>
                            <td>
                                <a href="view_patient.php?id=<?= $patient['id'] ?>" class="font-medium text-primary hover:underline">
                                    <?= htmlspecialchars($patient['full_name']) ?>
                                </a>
                            </td>
                            <td style="font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                            <td>
                                <?php if (!empty($patient['phone'])): ?>
                                    <a href="tel:<?= $patient['phone'] ?>" class="text-gray-600 hover:text-primary"><?= htmlspecialchars($patient['phone']) ?></a>
                                <?php else: ?>
                                    <span class="text-gray-400">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $days_text ?></td>
                            <td><?= $doctor_status ?></td>
                            <td>
                                <span class="font-medium text-gray-600"><?= $patient['total_visits'] ?? 0 ?></span>
                                <?php if (!empty($patient['latest_visit']['status'])): ?>
                                    <span class="text-xs text-gray-400 block"><?= ucfirst($patient['latest_visit']['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                <?php if ($appointment_count > 0): ?>
                                    <span class="text-xs text-purple-600 block">📅 <?= $appointment_count ?> appt(s)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                    <a href="view_patient.php?id=<?= $patient['id'] ?>" class="btn btn-primary btn-sm" title="View Patient">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="new_appointment.php?patient_id=<?= $patient['id'] ?>" class="btn btn-purple btn-sm" title="New Appointment">
                                        <i class="fas fa-calendar-plus"></i>
                                    </a>
                                    <a href="assign_doctor.php?patient_id=<?= $patient['id'] ?>" class="btn btn-success btn-sm" title="Assign Doctor">
                                        <i class="fas fa-user-md"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <a href="?page=1&search=<?= urlencode($search) ?>&filter=<?= $filter ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter=<?= $filter ?>" class="page-link <?= $i == $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&filter=<?= $filter ?>" class="page-link <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="text-center py-8">
                <i class="fas fa-users text-4xl text-gray-300 block mb-2"></i>
                <p class="text-gray-500">No patients found</p>
                <?php if (!empty($search)): ?>
                    <p class="text-sm text-gray-400">Try adjusting your search criteria</p>
                <?php endif; ?>
                <a href="patients.php" class="btn btn-primary mt-3">Clear Filters</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-modern">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Patients
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp"><?= date('h:i:s A') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // CLOCK
    // ================================================================
    function updateClock() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('clockDisplay');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = timeStr;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ================================================================
    // DARK MODE
    // ================================================================
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'patients.php?search=' + encodeURIComponent(query) + '&filter=<?= $filter ?>';
        } else {
            window.location.href = 'patients.php?filter=<?= $filter ?>';
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + type;
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

    <?php if ($error === 'invalid_patient'): ?>
        showToast('❌ Error', 'Patient not found. Please try again.', 'error');
    <?php endif; ?>
    
    <?php if ($success === 'updated'): ?>
        showToast('✅ Success', 'Patient updated successfully!', 'success');
    <?php endif; ?>

    console.log('%c👤 Braick - Patients', 'font-size:18px; font-weight:bold; color:#2563EB;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👥 Total Patients: <?= $stats['total'] ?? 0 ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Shows ALL patients (with and without doctor)', 'font-size:13px; color:#2563EB;');
    console.log('%c📅 New Patients: <?= $stats['new_patients'] ?? 0 ?>', 'font-size:13px; color:#2563EB;');
    console.log('%c📅 New Appointment button added (replaced Edit)', 'font-size:13px; color:#7C3AED;');
    console.log('%c🔍 Search filter in header', 'font-size:13px; color:#059669;');
</script>

</body>
</html>