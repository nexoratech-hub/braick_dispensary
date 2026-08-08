<?php
// ================================================================
// FILE: frontend/pages/admin/view_reception.php
// SUPER ADMIN - VIEW RECEPTION BRANCH DETAILS
// BRAICK DISPENSARY - BLUE THEME
// ================================================================

session_start();

// ================================================================
// FORCE SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET BRANCH ID
// ================================================================
$reception_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

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
            (SELECT COUNT(*) FROM appointments WHERE branch_id = b.id AND status = 'scheduled') as scheduled_appointments,
            (SELECT COUNT(*) FROM appointments WHERE branch_id = b.id AND status = 'confirmed') as confirmed_appointments,
            (SELECT COUNT(*) FROM appointments WHERE branch_id = b.id AND DATE(appointment_date) = CURDATE()) as today_appointments,
            (SELECT COUNT(*) FROM visits WHERE branch_id = b.id AND DATE(visit_date) = CURDATE()) as today_visits
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
// GET RECEPTIONISTS FOR THIS BRANCH
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
} catch (Exception $e) {
    $receptionists = [];
}

// ================================================================
// GET RECENT PATIENTS REGISTERED
// ================================================================
$recent_patients = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.patient_id,
            p.full_name,
            p.phone,
            p.gender,
            p.created_at,
            u.full_name as registered_by
        FROM patients p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.branch_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$reception_id]);
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_patients = [];
}

// ================================================================
// GET RECENT APPOINTMENTS
// ================================================================
$recent_appointments = [];
try {
    $stmt = $db->prepare("
        SELECT 
            a.id,
            a.appointment_date,
            a.status,
            a.created_at,
            p.full_name as patient_name,
            u.full_name as doctor_name,
            a.visit_type
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.branch_id = ?
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$reception_id]);
    $recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_appointments = [];
}

// ================================================================
// GET RECENT VISITS
// ================================================================
$recent_visits = [];
try {
    $stmt = $db->prepare("
        SELECT 
            v.id,
            v.visit_number,
            v.visit_date,
            v.status,
            v.created_at,
            p.full_name as patient_name,
            u.full_name as doctor_name,
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
} catch (Exception $e) {
    $recent_visits = [];
}

// ================================================================
// GET RECENT ACTIVITIES
// ================================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM activity_logs 
        WHERE branch_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$reception_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [];
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
        'assigned' => 'info',
        'confirmed' => 'success',
        'scheduled' => 'warning',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'active' => 'fa-check-circle',
        'inactive' => 'fa-times-circle',
        'pending' => 'fa-clock',
        'assigned' => 'fa-user-check',
        'confirmed' => 'fa-check-double',
        'scheduled' => 'fa-calendar-check',
        'completed' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once '../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reception - Braick Dispensary</title>
    
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
            
            --indigo: #4F46E5;
            --indigo-bg: #EEF2FF;
            
            --teal: #0D9488;
            --teal-bg: #ECFDF5;
            
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
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .branch-selector:focus {
            border-color: var(--primary);
        }
        
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
        
        /* ================================================================
           DETAILS CARD
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .detail-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .detail-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* ================================================================
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            color: var(--text-primary);
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .stat-icon.blue { background: var(--primary-bg); color: var(--primary); }
        .stat-icon.green { background: #ECFDF5; color: #059669; }
        .stat-icon.orange { background: #FFFBEB; color: #F59E0B; }
        .stat-icon.purple { background: #F5F3FF; color: #7C3AED; }
        .stat-icon.indigo { background: #EEF2FF; color: #4F46E5; }
        .stat-icon.teal { background: #ECFDF5; color: #0D9488; }
        .stat-icon.red { background: #FEF2F2; color: #DC2626; }
        
        [data-theme="dark"] .stat-icon.blue { background: #1E3A5F; color: #3B82F6; }
        [data-theme="dark"] .stat-icon.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .stat-icon.orange { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .stat-icon.purple { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .stat-icon.indigo { background: #1E1B4B; color: #818CF8; }
        [data-theme="dark"] .stat-icon.teal { background: #0F3D3D; color: #5EEAD4; }
        [data-theme="dark"] .stat-icon.red { background: #3A1A1A; color: #F87171; }
        
        .stat-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin: 0;
        }
        
        .stat-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        
        .stat-value.blue-text { color: var(--primary); }
        .stat-value.green-text { color: #059669; }
        .stat-value.orange-text { color: #F59E0B; }
        .stat-value.purple-text { color: #7C3AED; }
        .stat-value.indigo-text { color: #4F46E5; }
        .stat-value.teal-text { color: #0D9488; }
        .stat-value.red-text { color: #DC2626; }
        
        .stat-sub {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin: 0;
            opacity: 0.8;
        }
        
        .stat-link-hint {
            font-size: 0.6rem;
            color: var(--primary);
            opacity: 0.7;
            margin-top: 2px;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
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
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        .badge-purple { background: #7C3AED; }
        .badge-teal { background: #0D9488; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        /* ================================================================
           DATA TABLE
           ================================================================ */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.78rem;
        }
        
        .data-table thead th {
            background: var(--primary-gradient);
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
        
        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table td {
            padding: 8px 12px;
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
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            padding: 14px 20px;
            background: var(--bg-body);
            border-bottom: 1px solid var(--border-color);
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
            font-size: 0.85rem;
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
           BUTTONS
           ================================================================ */
        .btn {
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
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1.5px solid var(--border-color);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-sm {
            padding: 3px 8px;
            font-size: 0.6rem;
            border-radius: 5px;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1.5px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
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
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .detail-card { padding: 16px; }
            .data-table { font-size: 0.65rem; }
            .data-table thead th,
            .data-table td { padding: 6px 8px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
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
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .detail-card { box-shadow: none !important; border: 1px solid #ddd; }
            .data-table thead th {
                background: #0B5ED7 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .header-badge, .role-badge-display {
                color: white !important;
            }
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
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
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
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="edit_reception.php?id=<?= $reception['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="receptions.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECEPTION INFO CARD -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="detail-label"><i class="fas fa-map-marker-alt mr-1"></i> Location</p>
                <p class="detail-value"><?= htmlspecialchars($reception['location'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-phone mr-1"></i> Phone</p>
                <p class="detail-value"><?= htmlspecialchars($reception['phone'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-envelope mr-1"></i> Email</p>
                <p class="detail-value"><?= htmlspecialchars($reception['email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calendar-plus mr-1"></i> Created</p>
                <p class="detail-value"><?= date('M d, Y h:i A', strtotime($reception['created_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-clock mr-1"></i> Last Updated</p>
                <p class="detail-value"><?= date('M d, Y h:i A', strtotime($reception['updated_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-tie mr-1"></i> Receptionists</p>
                <p class="detail-value"><?= $reception['active_receptionists'] ?? 0 ?> Active / <?= $reception['total_receptionists'] ?? 0 ?> Total</p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up" style="animation-delay:0.05s;">
        
        <!-- 1. Total Patients -->
        <a href="patients.php?branch=<?= $reception_id ?>" class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <p class="stat-label">Total Patients</p>
                <p class="stat-value blue-text"><?= number_format($reception['total_patients'] ?? 0) ?></p>
                <p class="stat-sub">+<?= number_format($reception['today_patients'] ?? 0) ?> today</p>
                <p class="stat-link-hint"><i class="fas fa-arrow-right"></i> View Patients</p>
            </div>
        </a>
        
        <!-- 2. Today's Visits -->
        <a href="visits.php?branch=<?= $reception_id ?>&date=today" class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-clinic-medical"></i>
            </div>
            <div>
                <p class="stat-label">Today's Visits</p>
                <p class="stat-value green-text"><?= number_format($reception['today_visits'] ?? 0) ?></p>
                <p class="stat-sub">Pending: <?= $reception['pending_visits'] ?? 0 ?></p>
                <p class="stat-link-hint"><i class="fas fa-arrow-right"></i> View Visits</p>
            </div>
        </a>
        
        <!-- 3. Appointments -->
        <a href="appointments.php?branch=<?= $reception_id ?>" class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div>
                <p class="stat-label">Appointments</p>
                <p class="stat-value purple-text"><?= number_format(($reception['scheduled_appointments'] ?? 0) + ($reception['confirmed_appointments'] ?? 0)) ?></p>
                <p class="stat-sub">Scheduled: <?= $reception['scheduled_appointments'] ?? 0 ?> | Confirmed: <?= $reception['confirmed_appointments'] ?? 0 ?></p>
                <p class="stat-link-hint"><i class="fas fa-arrow-right"></i> View Appointments</p>
            </div>
        </a>
        
        <!-- 4. Today's Appointments -->
        <a href="appointments.php?branch=<?= $reception_id ?>&date=today" class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div>
                <p class="stat-label">Today's Appointments</p>
                <p class="stat-value orange-text"><?= number_format($reception['today_appointments'] ?? 0) ?></p>
                <p class="stat-sub">Need attention</p>
                <p class="stat-link-hint"><i class="fas fa-arrow-right"></i> View Today</p>
            </div>
        </a>
        
        <!-- 5. Pending Visits -->
        <a href="visits.php?branch=<?= $reception_id ?>&status=pending" class="stat-card">
            <div class="stat-icon red">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <p class="stat-label">Pending Visits</p>
                <p class="stat-value <?= ($reception['pending_visits'] ?? 0) > 0 ? 'red-text' : 'green-text' ?>">
                    <?= number_format($reception['pending_visits'] ?? 0) ?>
                </p>
                <p class="stat-sub">Need doctor assignment</p>
                <p class="stat-link-hint"><i class="fas fa-arrow-right"></i> View Pending</p>
            </div>
        </a>
        
        <!-- 6. Assigned Visits -->
        <a href="visits.php?branch=<?= $reception_id ?>&status=assigned" class="stat-card">
            <div class="stat-icon indigo">
                <i class="fas fa-user-check"></i>
            </div>
            <div>
                <p class="stat-label">Assigned Visits</p>
                <p class="stat-value indigo-text"><?= number_format($reception['assigned_visits'] ?? 0) ?></p>
                <p class="stat-sub">With doctors</p>
                <p class="stat-link-hint"><i class="fas fa-arrow-right"></i> View Assigned</p>
            </div>
        </a>
        
        <!-- 7. Total Receptionists -->
        <a href="employees.php?branch=<?= $reception_id ?>&role=reception" class="stat-card">
            <div class="stat-icon teal">
                <i class="fas fa-user-tie"></i>
            </div>
            <div>
                <p class="stat-label">Receptionists</p>
                <p class="stat-value teal-text"><?= number_format($reception['total_receptionists'] ?? 0) ?></p>
                <p class="stat-sub"><?= $reception['active_receptionists'] ?? 0 ?> active</p>
                <p class="stat-link-hint"><i class="fas fa-arrow-right"></i> View Staff</p>
            </div>
        </a>
        
        <!-- 8. Registration Rate -->
        <a href="patients.php?branch=<?= $reception_id ?>" class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-chart-line"></i>
            </div>
            <div>
                <p class="stat-label">Registration Rate</p>
                <p class="stat-value blue-text">
                    <?php 
                        $total = $reception['total_patients'] ?? 0;
                        $today = $reception['today_patients'] ?? 0;
                        $rate = $total > 0 ? round(($today / $total) * 100, 1) : 0;
                        echo $rate . '%';
                    ?>
                </p>
                <p class="stat-sub">Today's registrations</p>
                <p class="stat-link-hint"><i class="fas fa-arrow-right"></i> View Reports</p>
            </div>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- RECEPTIONISTS LIST -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-tie text-teal-600"></i>
                Receptionists (<?= count($receptionists) ?>)
            </h3>
            <a href="add_employee.php?branch=<?= $reception_id ?>&role=reception" class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> Add Receptionist
            </a>
        </div>
        <div class="overflow-x-auto">
            <?php if (count($receptionists) > 0): ?>
                <table class="data-table">
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
                                <td class="font-medium"><?= htmlspecialchars($receptionist['full_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($receptionist['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($receptionist['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= $receptionist['status'] === 'active' ? 'success' : 'danger' ?>" style="font-size:0.6rem;padding:2px 10px;">
                                        <?= ucfirst($receptionist['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= ($receptionist['is_online'] ?? 0) ? 'badge-success' : 'badge-secondary' ?>" style="font-size:0.6rem;padding:2px 10px;">
                                        <i class="fas fa-<?= ($receptionist['is_online'] ?? 0) ? 'circle text-green-300' : 'circle' ?>"></i>
                                        <?= ($receptionist['is_online'] ?? 0) ? 'Online' : 'Offline' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_employee.php?id=<?= $receptionist['id'] ?>&branch=<?= $reception_id ?>" class="text-blue-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-user-tie text-2xl block mb-2"></i>
                    <p>No receptionists assigned to this branch</p>
                    <a href="add_employee.php?branch=<?= $reception_id ?>&role=reception" class="btn btn-sm btn-primary mt-2">
                        <i class="fas fa-plus"></i> Add Receptionist
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PATIENTS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-plus text-blue-600"></i>
                Recent Patients Registered
            </h3>
            <a href="patients.php?branch=<?= $reception_id ?>" class="text-xs text-blue-600 font-medium hover:underline">View All →</a>
        </div>
        <div class="overflow-x-auto">
            <?php if (count($recent_patients) > 0): ?>
                <table class="data-table">
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
                                <td class="font-mono text-xs"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                <td class="font-medium"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($patient['registered_by'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($patient['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_patient.php?id=<?= $patient['id'] ?>&branch=<?= $reception_id ?>" class="text-blue-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-user-plus text-2xl block mb-2"></i>
                    <p>No patients registered yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT APPOINTMENTS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-calendar-plus text-purple-600"></i>
                Recent Appointments
            </h3>
            <a href="appointments.php?branch=<?= $reception_id ?>" class="text-xs text-blue-600 font-medium hover:underline">View All →</a>
        </div>
        <div class="overflow-x-auto">
            <?php if (count($recent_appointments) > 0): ?>
                <table class="data-table">
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
                                <td class="font-medium"><?= htmlspecialchars($appointment['patient_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($appointment['doctor_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'] ?? 'now')) ?></td>
                                <td>
                                    <span class="badge badge-info" style="font-size:0.55rem;padding:1px 8px;">
                                        <?= ucfirst($appointment['visit_type'] ?? 'new') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($appointment['status'] ?? 'scheduled') ?>" style="font-size:0.55rem;padding:1px 8px;">
                                        <i class="fas <?= getStatusIcon($appointment['status'] ?? 'scheduled') ?>"></i>
                                        <?= ucfirst($appointment['status'] ?? 'Scheduled') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_appointment.php?id=<?= $appointment['id'] ?>&branch=<?= $reception_id ?>" class="text-blue-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-calendar-plus text-2xl block mb-2"></i>
                    <p>No appointments found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT VISITS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-clinic-medical text-green-600"></i>
                Recent Visits
            </h3>
            <a href="visits.php?branch=<?= $reception_id ?>" class="text-xs text-blue-600 font-medium hover:underline">View All →</a>
        </div>
        <div class="overflow-x-auto">
            <?php if (count($recent_visits) > 0): ?>
                <table class="data-table">
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
                                <td class="font-mono text-xs"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></td>
                                <td class="font-medium"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($visit['doctor_name'] ?? 'Not Assigned') ?></td>
                                <td>
                                    <span class="badge badge-info" style="font-size:0.55rem;padding:1px 8px;">
                                        <?= ucfirst($visit['visit_type'] ?? 'new') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($visit['status'] ?? 'pending') ?>" style="font-size:0.55rem;padding:1px 8px;">
                                        <i class="fas <?= getStatusIcon($visit['status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst($visit['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($visit['visit_date'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_visit.php?id=<?= $visit['id'] ?>&branch=<?= $reception_id ?>" class="text-blue-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-clinic-medical text-2xl block mb-2"></i>
                    <p>No visits found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT ACTIVITIES -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.3s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-clock text-gray-500"></i>
                Recent Activities
            </h3>
            <a href="system_logs.php?branch=<?= $reception_id ?>" class="text-xs text-blue-600 font-medium hover:underline">View All →</a>
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
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-clock text-2xl block mb-2"></i>
                    <p>No activities found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 animate-fade-in-up" style="animation-delay:0.35s;">
        <a href="add_patient.php?branch=<?= $reception_id ?>" 
           class="bg-card border border-border rounded-lg p-4 text-center hover:border-primary transition-all hover:shadow-md text-decoration-none">
            <i class="fas fa-user-plus text-2xl text-blue-600 block mb-2"></i>
            <span class="text-sm font-medium text-text-primary">Register Patient</span>
        </a>
        <a href="add_appointment.php?branch=<?= $reception_id ?>" 
           class="bg-card border border-border rounded-lg p-4 text-center hover:border-purple-500 transition-all hover:shadow-md text-decoration-none">
            <i class="fas fa-calendar-plus text-2xl text-purple-600 block mb-2"></i>
            <span class="text-sm font-medium text-text-primary">Schedule Appointment</span>
        </a>
        <a href="assign_doctor.php?branch=<?= $reception_id ?>" 
           class="bg-card border border-border rounded-lg p-4 text-center hover:border-green-500 transition-all hover:shadow-md text-decoration-none">
            <i class="fas fa-user-md text-2xl text-green-600 block mb-2"></i>
            <span class="text-sm font-medium text-text-primary">Assign Doctor</span>
        </a>
        <a href="reports.php?branch=<?= $reception_id ?>&type=reception" 
           class="bg-card border border-border rounded-lg p-4 text-center hover:border-orange-500 transition-all hover:shadow-md text-decoration-none">
            <i class="fas fa-chart-bar text-2xl text-orange-500 block mb-2"></i>
            <span class="text-sm font-medium text-text-primary">View Reports</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Reception Details - <?= htmlspecialchars($reception['name']) ?>
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
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

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

    console.log('%c📋 Braick Dispensary - View Reception (BLUE THEME)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($reception['name']) ?> (ID: <?= $reception_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c👥 Total Patients: <?= number_format($reception['total_patients'] ?? 0) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📅 Today\'s Appointments: <?= number_format($reception['today_appointments'] ?? 0) ?>', 'font-size:13px; color:#F59E0B;');
    console.log('%c🔄 Pending Visits: <?= number_format($reception['pending_visits'] ?? 0) ?>', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>