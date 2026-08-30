<?php
// ================================================================
// FILE: frontend/pages/admin/reception_dashboard.php
// RECEPTION DASHBOARD - BLUE THEME WITH AJAX AUTO UPDATE
// BRAICK DISPENSARY - USING EXISTING DB TABLES
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS ADMIN
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// VERIFY USER EXISTS IN DATABASE
// ================================================================
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
// GET UNREAD NOTIFICATIONS
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
// FETCH DASHBOARD STATISTICS
// ================================================================

// Total Patients
$total_patients = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM patients WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $total_patients = $stmt->fetchColumn();
} catch (Exception $e) {
    $total_patients = 0;
}

// Today's Patients
$today_patients = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM patients 
        WHERE branch_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$branch_id]);
    $today_patients = $stmt->fetchColumn();
} catch (Exception $e) {
    $today_patients = 0;
}

// Today's Visits
$today_visits = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM visits 
        WHERE branch_id = ? AND DATE(created_at) = CURDATE()
        AND status != 'cancelled'
    ");
    $stmt->execute([$branch_id]);
    $today_visits = $stmt->fetchColumn();
} catch (Exception $e) {
    $today_visits = 0;
}

// Today's Appointments
$today_appointments = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE branch_id = ? AND DATE(appointment_date) = CURDATE()
        AND status != 'cancelled'
    ");
    $stmt->execute([$branch_id]);
    $today_appointments = $stmt->fetchColumn();
} catch (Exception $e) {
    $today_appointments = 0;
}

// Pending Appointments
$pending_appointments = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE branch_id = ? AND status = 'scheduled'
    ");
    $stmt->execute([$branch_id]);
    $pending_appointments = $stmt->fetchColumn();
} catch (Exception $e) {
    $pending_appointments = 0;
}

// Today's Revenue (from bills table)
$today_revenue = 0;
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) FROM bills 
        WHERE branch_id = ? AND status = 'paid' 
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$branch_id]);
    $today_revenue = $stmt->fetchColumn();
} catch (Exception $e) {
    $today_revenue = 0;
}

// ================================================================
// FETCH ALL PATIENTS
// ================================================================
$all_patients = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.patient_id,
            p.full_name,
            p.gender,
            p.phone,
            p.email,
            p.created_at,
            p.assigned_doctor_id,
            u.full_name as assigned_doctor_name,
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
} catch (Exception $e) {
    $all_patients = [];
}

// ================================================================
// FETCH ASSIGNED PATIENTS
// ================================================================
$assigned_patients = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.patient_id,
            p.full_name,
            p.gender,
            p.phone,
            p.email,
            p.created_at,
            p.assigned_doctor_id,
            u.full_name as assigned_doctor_name,
            u.specialty as doctor_specialty,
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
} catch (Exception $e) {
    $assigned_patients = [];
}

// ================================================================
// FETCH UNASSIGNED PATIENTS
// ================================================================
$unassigned_patients = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.patient_id,
            p.full_name,
            p.gender,
            p.phone,
            p.email,
            p.created_at,
            (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND status != 'cancelled') as total_visits,
            (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND status != 'cancelled') as total_appointments
        FROM patients p
        WHERE p.branch_id = ? AND p.assigned_doctor_id IS NULL
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$branch_id]);
    $unassigned_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $unassigned_patients = [];
}

// ================================================================
// FETCH RECENT ACTIVITIES
// ================================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM activity_logs 
        WHERE branch_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$branch_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [];
}

// ================================================================
// FETCH TODAY'S APPOINTMENTS LIST
// ================================================================
$todays_appointments_list = [];
try {
    $stmt = $db->prepare("
        SELECT 
            a.*,
            p.full_name as patient_name,
            p.phone as patient_phone,
            u.full_name as doctor_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.branch_id = ? AND DATE(a.appointment_date) = CURDATE()
        AND a.status != 'cancelled'
        ORDER BY a.appointment_date ASC
        LIMIT 20
    ");
    $stmt->execute([$branch_id]);
    $todays_appointments_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $todays_appointments_list = [];
}

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
} catch (Exception $e) {
    $online_doctors = [];
}

// ================================================================
// FETCH BRANCH NAME
// ================================================================
$branch_name = 'Unknown';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $result = $stmt->fetch();
    if ($result) {
        $branch_name = $result['name'];
    }
} catch (Exception $e) {
    $branch_name = 'Unknown';
}

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $status = $status ?? 'unknown';
    $classes = [
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
        'dispensed' => 'success',
        'confirmed' => 'info',
        'cancelled' => 'danger',
        'paid' => 'success',
        'partial' => 'warning',
        'scheduled' => 'info',
        'completed' => 'success',
        'online' => 'success',
        'offline' => 'danger',
        'new' => 'info',
        'follow-up' => 'warning',
        'emergency' => 'danger',
        'accepted' => 'success',
        'rejected' => 'danger',
        'unknown' => 'secondary'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $status = $status ?? 'unknown';
    $icons = [
        'active' => 'fa-check-circle',
        'inactive' => 'fa-times-circle',
        'pending' => 'fa-clock',
        'dispensed' => 'fa-check-circle',
        'confirmed' => 'fa-check-double',
        'cancelled' => 'fa-times-circle',
        'paid' => 'fa-check-circle',
        'partial' => 'fa-clock',
        'scheduled' => 'fa-calendar-check',
        'completed' => 'fa-check-circle',
        'online' => 'fa-circle',
        'offline' => 'fa-circle',
        'new' => 'fa-user-plus',
        'follow-up' => 'fa-user-check',
        'emergency' => 'fa-ambulance',
        'accepted' => 'fa-check-circle',
        'rejected' => 'fa-times-circle',
        'unknown' => 'fa-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reception Dashboard - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BLUE THEME
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-hover: linear-gradient(135deg, #0A4CA8, #083C8A);
            
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
            --purple-bg: #EDE9FE;
            
            --white: #FFFFFF;
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
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.12);
            
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --table-hover: #F8FAFC;
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
            --primary-light: #60A5FA;
            --primary-bg: #1E3A5F;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.5);
            --table-hover: #1E293B;
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
        
        /* ================================================================
           TOP NAV - SHARED HEADER
           ================================================================ */
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
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
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
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER - BLUE THEME
           ================================================================ */
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
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
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
        
        /* ================================================================
           STATS CARDS - BLUE BACKGROUND (3+3 GRID)
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: var(--radius);
            padding: 20px 24px;
            border: none;
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
        
        .stat-card::before {
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
        
        .stat-card::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 8px 30px rgba(11, 94, 215, 0.4);
        }
        
        .stat-card .stat-content {
            flex: 1;
            position: relative;
            z-index: 1;
        }
        
        .stat-icon {
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
        
        .stat-card:hover .stat-icon {
            transform: scale(1.05);
            background: rgba(255,255,255,0.25);
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.8);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
        }
        
        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: white;
            margin: 0;
            line-height: 1.2;
        }
        
        .stat-sub {
            font-size: 0.55rem;
            color: rgba(255,255,255,0.6);
            margin-top: 2px;
        }
        
        .stat-arrow {
            opacity: 0;
            transition: all 0.3s ease;
            color: rgba(255,255,255,0.6);
            font-size: 0.8rem;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }
        
        .stat-card:hover .stat-arrow {
            opacity: 1;
            transform: translateX(4px);
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
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
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        .badge-purple { background: #7C3AED; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        /* ================================================================
           BUTTONS - FULL CSS STYLED
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            box-shadow: var(--shadow-sm);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn:active {
            transform: translateY(0px);
        }
        
        /* Primary Button */
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-gradient-hover);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.35);
        }
        
        /* Success Button */
        .btn-success {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #047857, #065F46);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.35);
        }
        
        /* Danger Button */
        .btn-danger {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #B91C1C, #991B1B);
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.35);
        }
        
        /* Warning Button */
        .btn-warning {
            background: linear-gradient(135deg, #D97706, #B45309);
            color: white;
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #B45309, #92400E);
            box-shadow: 0 4px 16px rgba(217, 119, 6, 0.35);
        }
        
        /* Outline Button */
        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.15);
        }
        
        /* Button Sizes */
        .btn-sm {
            padding: 5px 12px;
            font-size: 0.7rem;
            border-radius: 6px;
        }
        
        .btn-lg {
            padding: 14px 32px;
            font-size: 1rem;
        }
        
        .btn-block {
            width: 100%;
            justify-content: center;
        }
        
        /* Button with icon */
        .btn i {
            font-size: 0.9rem;
        }
        
        .btn-sm i {
            font-size: 0.7rem;
        }
        
        .btn-lg i {
            font-size: 1.1rem;
        }
        
        /* Disabled button */
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* ================================================================
           DOCTOR STATUS CARD
           ================================================================ */
        .doctor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
        }
        
        .doctor-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .doctor-card::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .doctor-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .doctor-card:hover::before {
            opacity: 1;
        }
        
        .doctor-card .doctor-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--primary-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        
        .doctor-card:hover .doctor-avatar {
            transform: scale(1.05);
        }
        
        .doctor-card .doctor-info {
            flex: 1;
            min-width: 0;
        }
        
        .doctor-card .doctor-name {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .doctor-card .doctor-specialty {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .doctor-card .status-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }
        
        .doctor-card .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        
        .doctor-card .status-dot.online {
            background: #059669;
            box-shadow: 0 0 12px rgba(5, 150, 105, 0.5);
            animation: pulse-dot-online 2s infinite;
        }
        
        .doctor-card .status-dot.offline {
            background: #DC2626;
            box-shadow: 0 0 12px rgba(220, 38, 38, 0.3);
        }
        
        @keyframes pulse-dot-online {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.7; }
        }
        
        .doctor-card .status-label {
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .doctor-card .status-label.online { color: #059669; }
        .doctor-card .status-label.offline { color: #DC2626; }
        
        .doctor-card.status-updated {
            animation: flash-update 0.6s ease;
        }
        
        @keyframes flash-update {
            0% { background: var(--primary-bg); border-color: var(--primary); }
            100% { background: var(--bg-card); border-color: var(--border-color); }
        }
        
        /* ================================================================
           DATA TABLES
           ================================================================ */
        .table-container {
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.8rem;
        }
        
        .data-table thead th {
            background: var(--primary-gradient);
            color: white;
            font-weight: 600;
            padding: 10px 12px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: none;
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
        
        .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            transition: background 0.2s ease;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            padding: 16px 24px;
            background: var(--bg-body);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        [data-theme="dark"] .card-header {
            background: #0F172A;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 8px;
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
            font-size: 2.5rem;
            color: var(--border-color);
            margin-bottom: 12px;
        }
        
        .empty-state h4 {
            font-size: 1rem;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        /* ================================================================
           TOAST NOTIFICATION
           ================================================================ */
        .status-toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            padding: 12px 20px;
            border-radius: var(--radius);
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-xl);
            display: none;
            align-items: center;
            gap: 12px;
            min-width: 250px;
            animation: slideUp 0.4s ease;
        }
        
        .status-toast.show {
            display: flex;
        }
        
        .status-toast .toast-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        
        .status-toast .toast-icon.online { background: #D1FAE5; color: #059669; }
        .status-toast .toast-icon.offline { background: #FEE2E2; color: #DC2626; }
        
        .status-toast .toast-content {
            flex: 1;
        }
        
        .status-toast .toast-content .toast-title {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-primary);
        }
        
        .status-toast .toast-content .toast-message {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 500;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .doctor-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .doctor-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .stat-card { padding: 14px 16px; min-height: 70px; }
            .stat-value { font-size: 1.2rem; }
            .stat-icon { width: 40px; height: 40px; font-size: 1rem; }
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .stat-card:hover .stat-icon {
            animation: pulse 0.5s ease;
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - SHARED HEADER -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header (Buttons Removed) -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clipboard-list"></i>
                Reception Dashboard
                <span class="role-badge-display">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($branch_name) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-calendar-day"></i> <?= date('M d, Y') ?>
                </span>
                <span class="header-badge" id="onlineCountBadge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-circle" style="color:#34D399;"></i>
                    <span id="onlineCount"><?= count(array_filter($online_doctors, function($d) { return ($d['is_online'] ?? 0) == 1; })) ?></span> Online
                </span>
                <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?>
                </span>
            </p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS - 3 JUNA 3 CHINI (BLUE BACKGROUND) -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <!-- ROW 1: Cards 1-3 -->
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Total Patients</p>
                <p class="stat-value"><?= number_format($total_patients) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Today's Patients</p>
                <p class="stat-value"><?= number_format($today_patients) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-hospital-user"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Today's Visits</p>
                <p class="stat-value"><?= number_format($today_visits) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
        
        <!-- ROW 2: Cards 4-6 -->
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Today's Appointments</p>
                <p class="stat-value"><?= number_format($today_appointments) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Pending Appointments</p>
                <p class="stat-value"><?= number_format($pending_appointments) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Today's Revenue</p>
                <p class="stat-value">TSh <?= number_format($today_revenue, 0) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ONLINE DOCTORS - WITH AJAX AUTO UPDATE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-md text-blue-600"></i>
                Online Doctors
                <span class="text-xs text-gray-500 ml-2" id="doctorCountLabel">
                    (<span id="onlineDoctorCount"><?= count(array_filter($online_doctors, function($d) { return ($d['is_online'] ?? 0) == 1; })) ?></span> online)
                </span>
            </h3>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400" id="lastUpdateTime">Last update: just now</span>
                <button onclick="fetchDoctorStatus()" class="btn btn-sm btn-primary" title="Refresh doctor status">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i> Refresh
                </button>
                <a href="doctors.php?branch_id=<?= $branch_id ?>" class="btn btn-sm btn-outline">View All</a>
            </div>
        </div>
        <div class="p-4">
            <div class="doctor-grid" id="doctorGrid">
                <?php if (count($online_doctors) > 0): ?>
                    <?php foreach ($online_doctors as $doctor): 
                        $is_online = ($doctor['is_online'] ?? 0) == 1;
                        $initial = strtoupper(substr($doctor['full_name'] ?? 'D', 0, 1));
                    ?>
                        <div class="doctor-card" data-doctor-id="<?= $doctor['id'] ?>" data-doctor-name="<?= htmlspecialchars($doctor['full_name'] ?? 'Unknown') ?>">
                            <div class="doctor-avatar"><?= $initial ?></div>
                            <div class="doctor-info">
                                <div class="doctor-name"><?= htmlspecialchars($doctor['full_name'] ?? 'Unknown') ?></div>
                                <div class="doctor-specialty"><?= htmlspecialchars($doctor['specialty'] ?? 'General') ?></div>
                            </div>
                            <div class="status-container">
                                <div class="status-dot <?= $is_online ? 'online' : 'offline' ?>"></div>
                                <div class="status-label <?= $is_online ? 'online' : 'offline' ?>">
                                    <?= $is_online ? 'Online' : 'Offline' ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state col-span-full">
                        <i class="fas fa-user-md"></i>
                        <h4>No Doctors Found</h4>
                        <p>No doctors are available in this branch.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TODAY'S APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-calendar-day text-purple-600"></i>
                Today's Appointments
            </h3>
            <a href="appointments.php?branch_id=<?= $branch_id ?>" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="overflow-x-auto">
            <?php if (count($todays_appointments_list) > 0): ?>
                <table class="data-table">
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
                                <td class="font-medium"><?= htmlspecialchars($appointment['patient_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($appointment['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= date('h:i A', strtotime($appointment['appointment_date'] ?? 'now')) ?></td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($appointment['visit_type'] ?? 'new') ?>" style="font-size:0.6rem;padding:2px 10px;">
                                        <?= ucfirst($appointment['visit_type'] ?? 'New') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($status) ?>" style="font-size:0.6rem;padding:2px 10px;">
                                        <i class="fas <?= getStatusIcon($status) ?>"></i>
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_appointment.php?id=<?= $appointment['id'] ?>&branch_id=<?= $branch_id ?>" class="btn btn-sm btn-primary">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-day"></i>
                    <h4>No Appointments Today</h4>
                    <p>There are no appointments scheduled for today.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ALL PATIENTS TABLE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-users text-blue-600"></i>
                All Patients
                <span class="text-xs text-gray-500 ml-2">(<?= count($all_patients) ?> patients)</span>
            </h3>
            <div class="flex gap-2">
                <a href="patients.php?branch_id=<?= $branch_id ?>" class="btn btn-sm btn-outline">View All</a>
            </div>
        </div>
        <div class="table-container">
            <?php if (count($all_patients) > 0): ?>
                <table class="data-table">
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
                                <td class="font-mono text-xs font-semibold"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                <td class="font-medium"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= ($patient['gender'] ?? '') === 'Male' ? 'info' : (($patient['gender'] ?? '') === 'Female' ? 'purple' : 'secondary') ?>" style="font-size:0.55rem;padding:2px 10px;">
                                        <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if (!empty($patient['assigned_doctor_name'])): ?>
                                        <span class="badge badge-success" style="font-size:0.55rem;padding:2px 10px;">
                                            <i class="fas fa-user-md"></i> <?= htmlspecialchars($patient['assigned_doctor_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-warning" style="font-size:0.55rem;padding:2px 10px;">
                                            <i class="fas fa-user-slash"></i> Unassigned
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($patient['total_visits'] ?? 0) ?></td>
                                <td><?= number_format($patient['total_appointments'] ?? 0) ?></td>
                                <td>
                                    <a href="view_patient.php?id=<?= $patient['id'] ?>&branch_id=<?= $branch_id ?>" class="btn btn-sm btn-primary">View</a>
                                    <?php if (empty($patient['assigned_doctor_id'])): ?>
                                        <a href="assign_doctor.php?patient_id=<?= $patient['id'] ?>&branch_id=<?= $branch_id ?>" class="btn btn-sm btn-success">Assign</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h4>No Patients Found</h4>
                    <p>No patients registered in this branch yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ASSIGNED PATIENTS TABLE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-check text-green-600"></i>
                Assigned Patients
                <span class="text-xs text-gray-500 ml-2">(<?= count($assigned_patients) ?> assigned)</span>
            </h3>
            <div class="flex gap-2">
                <a href="assigned_patients.php?branch_id=<?= $branch_id ?>" class="btn btn-sm btn-outline">View All</a>
            </div>
        </div>
        <div class="table-container">
            <?php if (count($assigned_patients) > 0): ?>
                <table class="data-table">
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
                                <td class="font-mono text-xs font-semibold"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                <td class="font-medium"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= ($patient['gender'] ?? '') === 'Male' ? 'info' : (($patient['gender'] ?? '') === 'Female' ? 'purple' : 'secondary') ?>" style="font-size:0.55rem;padding:2px 10px;">
                                        <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-success" style="font-size:0.55rem;padding:2px 10px;">
                                        <i class="fas fa-user-md"></i> <?= htmlspecialchars($patient['assigned_doctor_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($patient['doctor_specialty'] ?? 'General') ?></td>
                                <td><?= number_format($patient['total_visits'] ?? 0) ?></td>
                                <td>
                                    <a href="view_patient.php?id=<?= $patient['id'] ?>&branch_id=<?= $branch_id ?>" class="btn btn-sm btn-primary">View</a>
                                    <a href="reassign_doctor.php?patient_id=<?= $patient['id'] ?>&branch_id=<?= $branch_id ?>" class="btn btn-sm btn-warning">Reassign</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-check"></i>
                    <h4>No Assigned Patients</h4>
                    <p>No patients have been assigned to doctors in this branch yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT ACTIVITIES -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-clock text-gray-500"></i>
                Recent Activities
            </h3>
            <a href="activity_logs.php?branch_id=<?= $branch_id ?>" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="max-h-60 overflow-y-auto">
            <?php if (count($recent_activities) > 0): ?>
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="flex items-start gap-3 p-3 border-b border-gray-100 dark:border-gray-700 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition">
                        <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0 text-white">
                            <i class="fas fa-circle text-[6px]"></i>
                        </div>
                        <div>
                            <p class="font-medium text-sm text-gray-800 dark:text-gray-200"><?= htmlspecialchars($activity['action'] ?? 'Action') ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($activity['details'] ?? '') ?></p>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">
                                <?= isset($activity['created_at']) ? date('M d, Y h:i A', strtotime($activity['created_at'])) : 'Just now' ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clock"></i>
                    <h4>No Activities</h4>
                    <p>No recent activities found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TOAST NOTIFICATION -->
    <!-- ================================================================ -->
    <div class="status-toast" id="statusToast">
        <div class="toast-icon" id="toastIcon">
            <i class="fas fa-circle"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title" id="toastTitle">Doctor Status Changed</div>
            <div class="toast-message" id="toastMessage">Dr. John Mushi is now online</div>
        </div>
        <button onclick="hideToast()" style="background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:1.2rem;padding:4px 8px;border-radius:6px;transition:all 0.3s;">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Reception Dashboard - <?= htmlspecialchars($branch_name) ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch_id=<?= $branch_id ?>';
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // TOAST NOTIFICATION
    // ================================================================
    var toastTimeout = null;

    function showToast(doctorName, status) {
        var toast = document.getElementById('statusToast');
        var icon = document.getElementById('toastIcon');
        var title = document.getElementById('toastTitle');
        var message = document.getElementById('toastMessage');
        
        if (!toast || !icon || !title || !message) return;
        
        var isOnline = status === 'online';
        
        icon.className = 'toast-icon ' + (isOnline ? 'online' : 'offline');
        icon.innerHTML = '<i class="fas fa-' + (isOnline ? 'check-circle' : 'times-circle') + '"></i>';
        
        title.textContent = isOnline ? '🟢 Doctor Online' : '🔴 Doctor Offline';
        message.textContent = 'Dr. ' + doctorName + ' is now ' + (isOnline ? 'online' : 'offline');
        
        toast.classList.add('show');
        
        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }
        toastTimeout = setTimeout(function() {
            toast.classList.remove('show');
        }, 5000);
    }

    function hideToast() {
        var toast = document.getElementById('statusToast');
        if (toast) {
            toast.classList.remove('show');
        }
        if (toastTimeout) {
            clearTimeout(toastTimeout);
            toastTimeout = null;
        }
    }

    // ================================================================
    // AJAX - FETCH DOCTOR STATUS
    // ================================================================
    var autoUpdateInterval = null;
    var isUpdating = false;
    var previousDoctorStatus = {};

    document.querySelectorAll('.doctor-card').forEach(function(card) {
        var id = card.getAttribute('data-doctor-id');
        var dot = card.querySelector('.status-dot');
        var isOnline = dot ? dot.classList.contains('online') : false;
        previousDoctorStatus[id] = isOnline;
    });

    function fetchDoctorStatus() {
        if (isUpdating) return;
        isUpdating = true;
        
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
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.doctors) {
                updateDoctorUI(data.doctors);
                updateLastUpdateTime();
            }
        })
        .catch(function(error) {
            console.error('Error fetching doctor status:', error);
        })
        .finally(function() {
            isUpdating = false;
            if (refreshIcon) refreshIcon.classList.remove('fa-spin');
        });
    }

    function updateDoctorUI(doctors) {
        var grid = document.getElementById('doctorGrid');
        if (!grid) return;
        
        var onlineCount = 0;
        var cards = grid.querySelectorAll('.doctor-card');
        
        doctors.forEach(function(doctor, index) {
            var card = cards[index];
            if (card) {
                var doctorId = doctor.id.toString();
                var isOnline = doctor.is_online == 1;
                var previousStatus = previousDoctorStatus[doctorId] || false;
                
                if (previousStatus !== isOnline) {
                    showToast(doctor.full_name, isOnline ? 'online' : 'offline');
                    
                    card.classList.remove('status-updated');
                    void card.offsetWidth;
                    card.classList.add('status-updated');
                    
                    previousDoctorStatus[doctorId] = isOnline;
                }
                
                var dot = card.querySelector('.status-dot');
                var label = card.querySelector('.status-label');
                
                if (dot) {
                    dot.className = 'status-dot ' + (isOnline ? 'online' : 'offline');
                }
                if (label) {
                    label.className = 'status-label ' + (isOnline ? 'online' : 'offline');
                    label.textContent = isOnline ? 'Online' : 'Offline';
                }
                
                if (isOnline) onlineCount++;
            }
        });
        
        var onlineCountBadge = document.getElementById('onlineCount');
        if (onlineCountBadge) {
            onlineCountBadge.textContent = onlineCount;
        }
        
        var onlineDoctorCount = document.getElementById('onlineDoctorCount');
        if (onlineDoctorCount) {
            onlineDoctorCount.textContent = onlineCount;
        }
        
        var doctorCountLabel = document.getElementById('doctorCountLabel');
        if (doctorCountLabel) {
            doctorCountLabel.innerHTML = '(<span id="onlineDoctorCount">' + onlineCount + '</span> online)';
        }
    }

    function updateLastUpdateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('lastUpdateTime');
        if (el) {
            el.textContent = 'Last update: ' + timeStr;
        }
    }

    // ================================================================
    // START AUTO UPDATE (every 5 seconds)
    // ================================================================
    function startAutoUpdate() {
        setTimeout(fetchDoctorStatus, 1000);
        
        if (autoUpdateInterval) {
            clearInterval(autoUpdateInterval);
        }
        autoUpdateInterval = setInterval(fetchDoctorStatus, 5000);
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            if (autoUpdateInterval) {
                clearInterval(autoUpdateInterval);
                autoUpdateInterval = null;
            }
        } else {
            startAutoUpdate();
        }
    });

    // ================================================================
    // INITIALIZE
    // ================================================================
    startAutoUpdate();

    console.log('%c🏥 Braick Dispensary - Reception Dashboard (3+3 Cards)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Patients: <?= number_format($total_patients) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👨‍⚕️ Assigned Patients: <?= count($assigned_patients) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📅 Today\'s Appointments: <?= number_format($today_appointments) ?>', 'font-size:13px; color:#F59E0B;');
    console.log('%c💰 Today\'s Revenue: TSh <?= number_format($today_revenue, 0) ?> (from bills table)', 'font-size:13px; color:#0D9488;');
    console.log('%c📊 Tables: patients, visits, appointments, bills, users, branches', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>