<?php
// ================================================================
// FILE: frontend/pages/laboratory/completed_tests.php
// LABORATORY - COMPLETED TESTS
// ✅ USING NEW DATABASE: dispensary_db
// ✅ ONLY ONE TABLE: lab_tests
// SHOWS: lab_tests with status = 'completed'
// WITH FULL LOGIN SESSION PROTECTION
// ✅ BLUE THEME - ONDOA PRINT - ONGEZA SIZE
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
// CHECK IF USER IS LABORATORY OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'laboratory' && $_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET LAB TECHNICIAN INFO FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE - NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

$message = '';
$message_type = '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// ================================================================
// GET COMPLETED DATA - FROM lab_tests ONLY
// ================================================================
$lab_items = [];
$total_completed = 0;

try {
    $query = "
        SELECT 
            lt.id as test_id,
            lt.visit_id,
            lt.doctor_id,
            lt.lab_technician_id,
            lt.technician_id,
            lt.test_name,
            lt.test_price,
            lt.test_type,
            lt.sample_type,
            lt.test_date,
            lt.results,
            lt.reference_range,
            lt.status as test_status,
            lt.notes,
            lt.branch_id,
            lt.created_at,
            lt.completed_at,
            lt.updated_at,
            lt.formatted_result,
            lt.printed_at,
            lt.printed_by,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone,
            p.gender,
            p.date_of_birth,
            p.blood_group,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            v.visit_number,
            v.visit_type,
            v.diagnosis,
            v.symptoms,
            v.status as visit_status,
            v.is_completed
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        WHERE lt.branch_id = ? 
        AND lt.status = 'completed'
    ";
    $params = [$user_branch_id];
    
    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($date_filter)) {
        $query .= " AND DATE(lt.completed_at) = ?";
        $params[] = $date_filter;
    }
    
    if ($sort === 'oldest') {
        $query .= " ORDER BY lt.completed_at ASC";
    } else {
        $query .= " ORDER BY lt.completed_at DESC";
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $lab_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_completed = count($lab_items);
    
} catch (Exception $e) {
    error_log("Completed tests error: " . $e->getMessage());
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $lab_items = [];
}

// ================================================================
// GET STATISTICS
// ================================================================

// Total completed
$total_completed_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed'");
    $stmt->execute([$user_branch_id]);
    $total_completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $total_completed_count = 0;
}

// Completed today
$completed_today = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()
    ");
    $stmt->execute([$user_branch_id]);
    $completed_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $completed_today = 0;
}

// Completed this week
$completed_week = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND status = 'completed' AND YEARWEEK(completed_at) = YEARWEEK(CURDATE())
    ");
    $stmt->execute([$user_branch_id]);
    $completed_week = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $completed_week = 0;
}

// Completed this month
$completed_month = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lab_tests 
        WHERE branch_id = ? AND status = 'completed' AND MONTH(completed_at) = MONTH(CURDATE()) AND YEAR(completed_at) = YEAR(CURDATE())
    ");
    $stmt->execute([$user_branch_id]);
    $completed_month = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $completed_month = 0;
}

// Pending count
$pending_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND (status IS NULL OR status = 'pending')");
    $stmt->execute([$user_branch_id]);
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_count = 0;
}

// In Progress count
$in_progress_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'in_progress'");
    $stmt->execute([$user_branch_id]);
    $in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $in_progress_count = 0;
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Tests - Laboratory</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563EB;
            --primary-dark: #1D4ED8;
            --primary-light: #60A5FA;
            --primary-bg: #DBEAFE;
            --primary-gradient-from: #1E3A5F;
            --primary-gradient-to: #2563EB;
            --success: #059669;
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
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --deep-blue: #1E3A5F;
            --deep-blue-dark: #172554;
            --deep-blue-light: #3B82F6;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary-bg: #1E3A5F;
            --deep-blue: #1E3A5F;
            --deep-blue-dark: #172554;
            --deep-blue-light: #3B82F6;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .page-header {
            background: linear-gradient(135deg, #1E3A5F, #2563EB, #3B82F6);
            border-radius: 16px;
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
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 14px;
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
            margin-top: 4px;
        }
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            backdrop-filter: blur(4px);
            letter-spacing: 0.05em;
        }
        
        .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .header-badge.blue-badge {
            background: rgba(59, 130, 246, 0.3);
            border-color: rgba(59, 130, 246, 0.2);
        }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 10px;
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
            cursor: pointer;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================ */
        /* STATS CARDS - BLUE THEME */
        /* ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        
        .stat-card-custom {
            border-radius: 14px;
            padding: 22px 26px;
            color: white;
            transition: var(--transition);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            cursor: default;
        }
        
        .stat-card-custom::before {
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
        
        .stat-card-custom:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card-custom .stat-icon {
            font-size: 2rem;
            margin-bottom: 6px;
            display: block;
            opacity: 0.9;
        }
        
        .stat-card-custom .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            display: block;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }
        
        .stat-card-custom .stat-label {
            font-size: 0.8rem;
            opacity: 0.85;
            font-weight: 500;
            display: block;
            margin-top: 2px;
        }
        
        .stat-card-custom .stat-sub {
            font-size: 0.7rem;
            opacity: 0.7;
            display: block;
            margin-top: 4px;
        }
        
        .stat-card-custom.blue-dark { background: linear-gradient(135deg, #1E3A5F, #2563EB); }
        .stat-card-custom.blue-light { background: linear-gradient(135deg, #2563EB, #3B82F6); }
        .stat-card-custom.blue-mid { background: linear-gradient(135deg, #1D4ED8, #2563EB); }
        .stat-card-custom.blue-teal { background: linear-gradient(135deg, #0D9488, #14B8A6); }
        .stat-card-custom.purple-blue { background: linear-gradient(135deg, #4F46E5, #7C3AED); }
        
        /* ================================================================ */
        /* CARD */
        /* ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        /* ================================================================ */
        /* FILTER FORM */
        /* ================================================================ */
        .filter-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-form input, .filter-form select {
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.9rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            min-width: 180px;
        }
        
        .filter-form input:focus, .filter-form select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }
        
        .filter-form .btn {
            padding: 10px 22px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            background: var(--primary);
            color: white;
        }
        
        .filter-form .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .filter-form .btn-reset {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .filter-form .btn-reset:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: transparent;
            box-shadow: none;
        }
        
        /* ================================================================ */
        /* TABLE - BIGGER SIZE */
        /* ================================================================ */
        .table-container {
            overflow-x: auto;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        
        .table-container thead {
            background: linear-gradient(135deg, #1E3A5F, #2563EB);
            color: white;
        }
        
        .table-container thead th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table-container tbody tr {
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }
        
        .table-container tbody tr:hover {
            background: var(--primary-bg);
        }
        
        .table-container tbody td {
            padding: 14px 20px;
            vertical-align: middle;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        
        /* ================================================================ */
        /* BADGES */
        /* ================================================================ */
        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        
        .status-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 14px;
            border-radius: 20px;
            text-transform: capitalize;
        }
        
        .status-badge.completed {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        /* ================================================================ */
        /* PATIENT CELL */
        /* ================================================================ */
        .patient-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .patient-avatar-sm {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        
        .patient-cell .patient-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-primary);
        }
        
        .patient-cell .patient-id {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-family: monospace;
        }
        
        /* ================================================================ */
        /* BUTTONS */
        /* ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
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
            background: #047857;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm {
            padding: 6px 14px;
            font-size: 0.75rem;
            border-radius: 6px;
        }
        
        .btn-xs {
            padding: 4px 10px;
            font-size: 0.7rem;
            border-radius: 4px;
        }
        
        /* ================================================================ */
        /* OTHER */
        /* ================================================================ */
        .font-mono { font-family: monospace; }
        .font-semibold { font-weight: 600; }
        .text-success { color: var(--success); }
        .text-warning { color: var(--warning); }
        .text-primary { color: var(--primary); }
        .text-gray-400 { color: var(--text-secondary); }
        .text-sm { font-size: 0.9rem; }
        .text-xs { font-size: 0.8rem; }
        .text-2xl { font-size: 1.5rem; }
        .font-bold { font-weight: 700; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mb-2 { margin-bottom: 8px; }
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-2 { gap: 8px; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            font-size: 1.3rem;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .empty-state p {
            font-size: 0.95rem;
        }
        
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
        }
        
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: var(--radius);
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
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        
        .footer {
            padding: 16px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 28px;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 700;
        }
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 18px 20px; }
            .page-header .page-title { font-size: 1.3rem; }
            .page-header .page-title i { font-size: 1.4rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-card-custom { padding: 16px 18px; }
            .stat-card-custom .stat-number { font-size: 1.6rem; }
            .filter-form { flex-direction: column; align-items: stretch; }
            .filter-form input, .filter-form select { width: 100%; min-width: unset; }
            .table-container thead th { padding: 10px 12px; font-size: 0.65rem; }
            .table-container tbody td { padding: 8px 12px; font-size: 0.75rem; }
            .card { padding: 16px 18px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card-custom { padding: 12px 14px; }
            .stat-card-custom .stat-number { font-size: 1.3rem; }
            .stat-card-custom .stat-icon { font-size: 1.4rem; }
            .stat-card-custom .stat-label { font-size: 0.65rem; }
            .page-header .page-title { font-size: 1.1rem; }
            .page-header .page-subtitle { font-size: 0.75rem; }
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
                <i class="fas fa-check-circle"></i>
                Completed Tests
                <span class="role-badge-display">LABORATORY</span>
                <span class="header-badge blue-badge">
                    <i class="fas fa-check"></i> <?= $total_completed_count ?> Total
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-flask"></i>
                View all completed lab tests
                <span class="header-badge">
                    <i class="fas fa-calendar-day"></i>
                    <?= $completed_today ?> Today
                </span>
                <span class="header-badge">
                    <i class="fas fa-calendar-week"></i>
                    <?= $completed_week ?> This Week
                </span>
                <span class="header-badge">
                    <i class="fas fa-calendar-alt"></i>
                    <?= $completed_month ?> This Month
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="in_progress_tests.php" class="btn-outline-light" style="background:rgba(255,255,255,0.15);">
                <i class="fas fa-spinner"></i> In Progress (<?= $in_progress_count ?>)
            </a>
            <a href="pending_requests.php" class="btn-outline-light" style="background:rgba(255,255,255,0.1);">
                <i class="fas fa-clock"></i> Pending (<?= $pending_count ?>)
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>" id="alertMessage">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATS CARDS - BLUE THEME -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card-custom blue-dark">
            <span class="stat-icon">✅</span>
            <span class="stat-number"><?= $total_completed_count ?></span>
            <span class="stat-label">Total Completed</span>
            <span class="stat-sub">All time</span>
        </div>
        
        <div class="stat-card-custom blue-light">
            <span class="stat-icon">📅</span>
            <span class="stat-number"><?= $completed_today ?></span>
            <span class="stat-label">Completed Today</span>
            <span class="stat-sub"><?= date('F d, Y') ?></span>
        </div>
        
        <div class="stat-card-custom blue-mid">
            <span class="stat-icon">📊</span>
            <span class="stat-number"><?= $completed_week ?></span>
            <span class="stat-label">This Week</span>
            <span class="stat-sub">Last 7 days</span>
        </div>
        
        <div class="stat-card-custom purple-blue">
            <span class="stat-icon">📈</span>
            <span class="stat-number"><?= $completed_month ?></span>
            <span class="stat-label">This Month</span>
            <span class="stat-sub"><?= date('F Y') ?></span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card">
        <form method="GET" action="" class="filter-form">
            <input type="text" name="search" placeholder="🔍 Search patient or test..." value="<?= htmlspecialchars($search) ?>">
            
            <input type="date" name="date" value="<?= htmlspecialchars($date_filter) ?>" title="Filter by date">
            
            <select name="sort">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            </select>
            
            <button type="submit" class="btn">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="completed_tests.php" class="btn btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:var(--primary);margin-right:10px;"></i>
                Completed Tests
                <span class="text-sm font-normal text-gray-400">(<?= $total_completed ?> records)</span>
            </h3>
            <span class="text-xs text-gray-400">
                <i class="fas fa-clock mr-1"></i> Updated: <?= date('h:i:s A') ?>
            </span>
        </div>
        
        <?php if (count($lab_items) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width:40px;text-align:center;">#</th>
                            <th style="min-width:200px;">Patient</th>
                            <th style="min-width:180px;">Test</th>
                            <th style="min-width:160px;">Doctor</th>
                            <th style="min-width:140px;">Completed At</th>
                            <th style="text-align:center;min-width:80px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($lab_items as $item): 
                            $color = '#' . substr(md5($item['patient_name'] ?? 'Unknown'), 0, 6);
                            $test_id = $item['test_id'];
                            $view_link = "view_test.php?id=" . $test_id;
                        ?>
                            <tr>
                                <td style="text-align:center;font-weight:700;"><?= $i++ ?></td>
                                <td>
                                    <div class="patient-cell">
                                        <div class="patient-avatar-sm" style="background: <?= $color ?>;">
                                            <?= strtoupper(substr($item['patient_name'] ?? 'U', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="patient-name"><?= htmlspecialchars($item['patient_name'] ?? 'Unknown') ?></div>
                                            <div class="patient-id"><?= htmlspecialchars($item['patient_code'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-semibold text-sm"><?= htmlspecialchars($item['test_name'] ?? 'N/A') ?></div>
                                    <?php if (!empty($item['test_type'])): ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($item['test_type']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['results'])): ?>
                                        <div class="text-xs text-gray-400 mt-1">
                                            <i class="fas fa-file-medical-alt"></i> 
                                            <?= strlen($item['results']) > 80 ? substr(htmlspecialchars($item['results']), 0, 80) . '...' : htmlspecialchars($item['results']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-sm font-semibold">Dr. <?= htmlspecialchars($item['doctor_name'] ?? 'N/A') ?></div>
                                    <?php if (!empty($item['doctor_specialty'])): ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($item['doctor_specialty']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-sm font-semibold"><?= date('d/m/Y', strtotime($item['completed_at'] ?? 'now')) ?></div>
                                    <div class="text-xs text-gray-400"><?= date('h:i A', strtotime($item['completed_at'] ?? 'now')) ?></div>
                                </td>
                                <td style="text-align:center;">
                                    <!-- ✅ Only View button - No Print -->
                                    <a href="<?= $view_link ?>" class="btn btn-primary btn-sm" title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3 flex justify-between items-center flex-wrap gap-2">
                <span class="text-xs text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i> Showing <?= $total_completed ?> completed test(s)
                </span>
                <span class="text-xs text-gray-400">
                    <i class="fas fa-check-circle mr-1" style="color:var(--success);"></i> All tests are marked as completed
                </span>
            </div>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: var(--primary);"></i>
                <h3>No Completed Tests</h3>
                <p>No lab tests have been completed yet.</p>
                <p class="text-xs text-gray-400 mt-2">
                    <i class="fas fa-info-circle"></i> Check <a href="in_progress_tests.php" class="text-primary hover:underline">In Progress Tests</a>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Completed Tests
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($user_full_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    var htmlElement = document.documentElement;
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
    }

    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    
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
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // AUTO REFRESH
    // ================================================================
    var autoRefreshInterval = null;

    function startAutoRefresh() {
        if (autoRefreshInterval) clearInterval(autoRefreshInterval);
        autoRefreshInterval = setInterval(function() {
            fetch(window.location.href + '&ajax=1')
                .then(function(response) { return response.text(); })
                .then(function(html) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');
                    var newTable = doc.querySelector('.table-container');
                    var currentTable = document.querySelector('.table-container');
                    if (newTable && currentTable) {
                        currentTable.innerHTML = newTable.innerHTML;
                    }
                })
                .catch(function() {});
        }, 30000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(startAutoRefresh, 5000);
    });

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }

    console.log('%c🔵 Completed Tests (BLUE THEME)', 'font-size:20px; font-weight:bold; color:#2563EB;');
    console.log('%c✅ Removed Print button - View only', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Increased font sizes for better visibility', 'font-size:13px; color:#34D399;');
    console.log('%c🔵 Blue theme applied throughout', 'font-size:13px; color:#2563EB;');
    console.log('%c📊 Total Completed: <?= $total_completed_count ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📅 Today: <?= $completed_today ?> | This Week: <?= $completed_week ?> | This Month: <?= $completed_month ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
</script>

</body>
</html>