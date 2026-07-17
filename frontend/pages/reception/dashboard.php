<?php
// ================================================================
// FILE: frontend/pages/reception/dashboard.php
// RECEPTION DASHBOARD - WITH REAL-TIME AUTO-UPDATE
// USING GLOBAL_STATS.JS (3 SECONDS)
// WITH CLICKABLE STAT CARDS - NAVIGATE TO RELEVANT PAGES
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Rose Mwangi (Reception)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['is_admin'] = false;
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Rose Mwangi';
$unread_notifications = 0;

try {
    $db = getDB();
    $today = date('Y-m-d');
    
    // ================================================================
    // GET UNREAD NOTIFICATIONS
    // ================================================================
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_notifications = $stmt->fetch()['total'] ?? 0;
    }
    
    // ================================================================
    // GET DOCTORS DATA FOR INITIAL LOAD
    // ================================================================
    $stmt = $db->prepare("
        SELECT id, full_name, specialty, is_online 
        FROM users 
        WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
        ORDER BY is_online DESC, full_name
    ");
    $stmt->execute([$selected_branch_id]);
    $doctors = $stmt->fetchAll();
    
    $online_doctors_count = 0;
    foreach ($doctors as $doc) {
        if ($doc['is_online'] == 1) {
            $online_doctors_count++;
        }
    }
    $total_doctors = count($doctors);
    
    // ================================================================
    // GET ONLINE DOCTORS LIST
    // ================================================================
    $online_doctors_list = array_filter($doctors, function($doc) {
        return $doc['is_online'] == 1;
    });
    
    // ================================================================
    // GET RECENT ACTIVITIES
    // ================================================================
    try {
        $stmt = $db->prepare("
            SELECT action, details, created_at 
            FROM activity_logs 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $recent_activities = $stmt->fetchAll();
    } catch (Exception $e) {
        $recent_activities = [];
    }
    
    // ================================================================
    // GET TODAY'S APPOINTMENTS
    // ================================================================
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name, p.patient_id, u.full_name as doctor_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.doctor_id = u.id
        WHERE DATE(a.appointment_date) = CURDATE() AND a.branch_id = ?
        ORDER BY a.appointment_date
        LIMIT 10
    ");
    $stmt->execute([$selected_branch_id]);
    $today_appointments_list = $stmt->fetchAll();
    
    // ================================================================
    // GET RECENT PATIENTS
    // ================================================================
    $stmt = $db->prepare("
        SELECT * FROM patients 
        WHERE branch_id = ?
        ORDER BY created_at DESC 
        LIMIT 8
    ");
    $stmt->execute([$selected_branch_id]);
    $recent_patients = $stmt->fetchAll();
    
} catch (Exception $e) {
    $doctors = [];
    $online_doctors_count = 0;
    $total_doctors = 0;
    $online_doctors_list = [];
    $recent_activities = [];
    $today_appointments_list = [];
    $recent_patients = [];
    $unread_notifications = 0;
}

// ================================================================
// STATS FOR INITIAL LOAD
// ================================================================
$stats = [
    'online_doctors' => $online_doctors_count,
    'total_doctors' => $total_doctors,
    'total_patients' => 0,
    'total_visits' => 0,
    'total_appointments' => 0,
    'today_patients' => 0,
    'today_visits' => 0,
    'today_appointments' => count($today_appointments_list),
    'pending_appointments' => 0
];

try {
    // Get stats from database for initial load
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
    $stmt->execute([$selected_branch_id]);
    $stats['total_patients'] = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ?");
    $stmt->execute([$selected_branch_id]);
    $stats['total_visits'] = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ?");
    $stmt->execute([$selected_branch_id]);
    $stats['total_appointments'] = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE branch_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$selected_branch_id, $today]);
    $stats['today_patients'] = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$selected_branch_id, $today]);
    $stats['today_visits'] = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND status IN ('scheduled', 'pending')");
    $stmt->execute([$selected_branch_id]);
    $stats['pending_appointments'] = $stmt->fetch()['count'] ?? 0;
    
} catch (Exception $e) {
    // Keep default values
}

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
    <title>Reception Dashboard - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
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
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --table-stripe: #1E293B;
            --table-hover: #1A3A2A;
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
           TOP NAV
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
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
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
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
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
            border-radius: 10px;
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
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
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
        
        .page-header .header-badge .online-count {
            color: #34D399;
            font-weight: 700;
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
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
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .update-badge-light {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.8);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        /* ================================================================
           STAT CARDS - BEAUTIFUL DESIGN
           ================================================================ */
        .stat-card {
            border-radius: 16px;
            padding: 20px 24px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: block;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
            color: white;
        }
        
        .stat-card:active {
            transform: scale(0.97);
        }
        
        /* Card Colors */
        .stat-card.blue { 
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8); 
        }
        .stat-card.green { 
            background: linear-gradient(135deg, #059669, #047857); 
        }
        .stat-card.purple { 
            background: linear-gradient(135deg, #7C3AED, #6D28D9); 
        }
        .stat-card.orange { 
            background: linear-gradient(135deg, #D97706, #B45309); 
        }
        .stat-card.red { 
            background: linear-gradient(135deg, #DC2626, #B91C1C); 
        }
        .stat-card.teal { 
            background: linear-gradient(135deg, #0D9488, #0F766E); 
        }
        .stat-card.pink { 
            background: linear-gradient(135deg, #DB2777, #BE185D); 
        }
        .stat-card.indigo { 
            background: linear-gradient(135deg, #4F46E5, #4338CA); 
        }
        
        /* Card Decoration */
        .stat-card .card-decoration {
            position: absolute;
            top: -30px;
            right: -30px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            pointer-events: none;
        }
        
        .stat-card .card-decoration-2 {
            position: absolute;
            bottom: -40px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.03);
            pointer-events: none;
        }
        
        .stat-card .stat-icon {
            font-size: 1.8rem;
            opacity: 0.9;
            margin-bottom: 8px;
            display: block;
        }
        
        .stat-card .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.8);
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-card .stat-update {
            font-size: 0.55rem;
            color: rgba(255,255,255,0.5);
            margin-top: 6px;
        }
        
        .stat-card .stat-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255,255,255,0.15);
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
            backdrop-filter: blur(4px);
        }
        
        .stat-card .stat-arrow {
            position: absolute;
            bottom: 12px;
            right: 16px;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.4);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-arrow {
            transform: translateX(4px);
            color: rgba(255,255,255,0.8);
        }
        
        /* ================================================================
           APPOINTMENT ITEMS
           ================================================================ */
        .appointment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .appointment-item:hover {
            background: var(--bg-body);
        }
        
        .appointment-item:last-child {
            border-bottom: none;
        }
        
        .appointment-time {
            font-weight: 600;
            font-size: 0.75rem;
            color: var(--text-primary);
            min-width: 60px;
        }
        
        .appointment-patient .name {
            font-weight: 500;
            font-size: 0.8rem;
            color: var(--text-primary);
        }
        
        .appointment-patient .doctor {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .appointment-status {
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 12px;
        }
        
        .appointment-status.scheduled { background: #E8F0FE; color: #0B5ED7; }
        .appointment-status.confirmed { background: #D1FAE5; color: #059669; }
        .appointment-status.completed { background: #D1FAE5; color: #059669; }
        .appointment-status.cancelled { background: #FEE2E2; color: #DC2626; }
        .appointment-status.pending { background: #FEF3C7; color: #D97706; }
        
        [data-theme="dark"] .appointment-status.scheduled { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .appointment-status.confirmed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .appointment-status.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .appointment-status.cancelled { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .appointment-status.pending { background: #3D2E0A; color: #FBBF24; }
        
        /* ================================================================
           ACTIVITY ITEMS
           ================================================================ */
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            background: var(--bg-body);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            flex-shrink: 0;
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .activity-content .action {
            font-weight: 500;
            font-size: 0.8rem;
            color: var(--text-primary);
        }
        
        .activity-content .details {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .activity-content .time {
            font-size: 0.6rem;
            color: var(--text-secondary);
            opacity: 0.7;
        }
        
        /* ================================================================
           PATIENT AVATAR
           ================================================================ */
        .patient-avatar-sm {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.7rem;
            flex-shrink: 0;
        }
        
        /* ================================================================
           QUICK ACTION BUTTONS
           ================================================================ */
        .quick-action {
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
        }
        
        .quick-action:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
            box-shadow: 0 8px 25px rgba(11, 94, 215, 0.12);
        }
        
        .quick-action .icon {
            font-size: 1.6rem;
            display: block;
            margin-bottom: 6px;
        }
        
        .quick-action .icon.blue { color: var(--primary); }
        .quick-action .icon.green { color: var(--success); }
        .quick-action .icon.purple { color: #7C3AED; }
        .quick-action .icon.orange { color: #D97706; }
        .quick-action .icon.red { color: var(--danger); }
        
        .quick-action .label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .quick-action:hover .icon.blue { color: white; }
        .quick-action:hover .icon.green { color: white; }
        .quick-action:hover .icon.purple { color: white; }
        .quick-action:hover .icon.orange { color: white; }
        .quick-action:hover .icon.red { color: white; }
        
        .quick-action.blue:hover { background: var(--primary); border-color: var(--primary); }
        .quick-action.green:hover { background: var(--success); border-color: var(--success); }
        .quick-action.purple:hover { background: #7C3AED; border-color: #7C3AED; }
        .quick-action.orange:hover { background: #D97706; border-color: #D97706; }
        .quick-action.red:hover { background: var(--danger); border-color: var(--danger); }
        
        .quick-action:hover .label { color: white; }
        
        /* ================================================================
           SCROLL CONTAINERS
           ================================================================ */
        .scroll-container {
            max-height: 220px;
            overflow-y: auto;
        }
        
        .scroll-container::-webkit-scrollbar {
            width: 4px;
        }
        
        .scroll-container::-webkit-scrollbar-track {
            background: var(--bg-body);
            border-radius: 4px;
        }
        
        .scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .role-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
        }
        
        [data-theme="dark"] .role-badge-display {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge-display {
            background: #1A3A2A;
            color: #34D399;
        }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-blue { color: var(--primary); }
        .card-title .title-green { color: var(--success); }
        .card-title .title-orange { color: #D97706; }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
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
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
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
            font-weight: 600; 
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
            .stat-card .stat-number { font-size: 1.6rem; }
            .stat-card { padding: 14px 16px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .stat-card .stat-number { font-size: 1.3rem; }
            .stat-card .stat-icon { font-size: 1.4rem; }
            .stat-card { padding: 12px 14px; }
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
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .online-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #059669;
            animation: pulse-dot 1.5s infinite;
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
            <input type="text" id="searchInput" placeholder="Search patients by name, ID or phone...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-home"></i>
                Reception Dashboard
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">RECEPTION</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                Welcome back, <strong><?= htmlspecialchars($user_full_name) ?></strong>!
                
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-calendar-day"></i> <?= date('F d, Y') ?>
                </span>
                
                <span class="header-badge" id="onlineDoctorBadge">
                    <i class="fas fa-user-md"></i>
                    <span class="online-count" id="onlineDoctorCount"><?= $online_doctors_count ?></span> Online
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="new_patient.php" class="btn-outline-light">
                <i class="fas fa-user-plus"></i> Register Patient
            </a>
            <a href="new_appointment.php" class="btn-outline-light">
                <i class="fas fa-calendar-plus"></i> New Appointment
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS - CLICKABLE & BEAUTIFUL -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-5">
        
        <!-- Card 1: Online Doctors -> online_doctors.php -->
        <a href="online_doctors.php" class="stat-card blue" id="onlineDoctorsCard">
            <span class="card-decoration"></span>
            <span class="card-decoration-2"></span>
            <span class="stat-icon">🟢</span>
            <div class="stat-number" id="onlineDoctorsStat"><?= $online_doctors_count ?></div>
            <div class="stat-label">Online Doctors</div>
            <div class="stat-update" id="onlineDoctorsStatTime">Updated now</div>
            <span class="stat-badge"><?= $total_doctors ?> Total</span>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- Card 2: Total Patients -> patients.php -->
        <a href="patients.php" class="stat-card purple" id="totalPatientsCard">
            <span class="card-decoration"></span>
            <span class="card-decoration-2"></span>
            <span class="stat-icon">👥</span>
            <div class="stat-number" id="totalPatients"><?= number_format($stats['total_patients']) ?></div>
            <div class="stat-label">Total Patients</div>
            <div class="stat-update" id="totalPatientsUpdate">Updated now</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- Card 3: Total Visits -> visits.php -->
        <a href="visits.php" class="stat-card green" id="totalVisitsCard">
            <span class="card-decoration"></span>
            <span class="card-decoration-2"></span>
            <span class="stat-icon">🏥</span>
            <div class="stat-number" id="totalVisits"><?= number_format($stats['total_visits']) ?></div>
            <div class="stat-label">Total Visits</div>
            <div class="stat-update" id="totalVisitsUpdate">Updated now</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- Card 4: Total Appointments -> appointments.php -->
        <a href="appointments.php" class="stat-card indigo" id="totalAppointmentsCard">
            <span class="card-decoration"></span>
            <span class="card-decoration-2"></span>
            <span class="stat-icon">📅</span>
            <div class="stat-number" id="totalAppointments"><?= number_format($stats['total_appointments']) ?></div>
            <div class="stat-label">Total Appointments</div>
            <div class="stat-update" id="totalAppointmentsUpdate">Updated now</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- Card 5: Today's Patients -> visits.php?filter=today -->
        <a href="visits.php?filter=today" class="stat-card orange" id="todayPatientsCard">
            <span class="card-decoration"></span>
            <span class="card-decoration-2"></span>
            <span class="stat-icon">👤</span>
            <div class="stat-number" id="todayPatientsTotal"><?= $stats['today_patients'] ?></div>
            <div class="stat-label">Today's Patients</div>
            <div class="stat-update" id="todayPatientsUpdate">Updated now</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- Card 6: Today's Visits -> visits.php?filter=today -->
        <a href="visits.php?filter=today" class="stat-card teal" id="todayVisitsCard">
            <span class="card-decoration"></span>
            <span class="card-decoration-2"></span>
            <span class="stat-icon">🩺</span>
            <div class="stat-number" id="todayVisitsTotal"><?= $stats['today_visits'] ?></div>
            <div class="stat-label">Today's Visits</div>
            <div class="stat-update" id="todayVisitsUpdate">Updated now</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- Card 7: Today's Appointments -> appointments.php?filter=today -->
        <a href="appointments.php?filter=today" class="stat-card pink" id="todayAppointmentsCard">
            <span class="card-decoration"></span>
            <span class="card-decoration-2"></span>
            <span class="stat-icon">📋</span>
            <div class="stat-number" id="todayAppointmentsTotal"><?= $stats['today_appointments'] ?></div>
            <div class="stat-label">Today's Appointments</div>
            <div class="stat-update" id="todayAppointmentsUpdate">Updated now</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- Card 8: Pending Appointments -> appointments.php?status=pending -->
        <a href="appointments.php?status=pending" class="stat-card red" id="pendingAppointmentsCard">
            <span class="card-decoration"></span>
            <span class="card-decoration-2"></span>
            <span class="stat-icon">⏳</span>
            <div class="stat-number" id="pendingAppointments"><?= $stats['pending_appointments'] ?></div>
            <div class="stat-label">Pending Appointments</div>
            <div class="stat-update" id="pendingAppointmentsUpdate">Updated now</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- APPOINTMENTS & ONLINE DOCTORS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <!-- Today's Appointments -->
        <div class="card lg:col-span-2">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-check title-blue mr-2"></i> Today's Appointments
                    <span class="text-sm font-normal text-gray-400" id="appointmentsCount">(<?= count($today_appointments_list) ?>)</span>
                </h3>
                <a href="appointments.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            
            <div class="scroll-container" id="appointmentsList">
                <?php if (count($today_appointments_list) > 0): ?>
                    <?php foreach ($today_appointments_list as $appt): ?>
                        <div class="appointment-item">
                            <span class="appointment-time"><?= date('h:i A', strtotime($appt['appointment_date'])) ?></span>
                            <div class="appointment-patient flex-1 ml-3">
                                <span class="name"><?= htmlspecialchars($appt['patient_name']) ?></span>
                                <span class="doctor block">Dr. <?= htmlspecialchars($appt['doctor_name']) ?></span>
                            </div>
                            <span class="appointment-status <?= $appt['status'] ?>">
                                <?= ucfirst($appt['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-6 text-gray-400">
                        <i class="fas fa-calendar-check text-2xl block mb-2"></i>
                        <p class="text-sm">No appointments scheduled for today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Online Doctors -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-md title-green mr-2"></i> Online Doctors
                    <span class="text-sm font-normal text-gray-400" id="onlineDoctorsCount">(<?= count($online_doctors_list) ?> online)</span>
                </h3>
                <a href="online_doctors.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            
            <div class="scroll-container" id="onlineDoctorsList">
                <?php if (count($online_doctors_list) > 0): ?>
                    <?php foreach ($online_doctors_list as $doc): ?>
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-primary-bg transition mb-1">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white text-sm font-bold">
                                    <?= strtoupper(substr($doc['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="font-medium text-sm text-gray-800"><?= htmlspecialchars($doc['full_name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($doc['specialty'] ?? 'General Practitioner') ?></p>
                                </div>
                            </div>
                            <span class="online-dot" title="Online"></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-user-md text-2xl block mb-2"></i>
                        <p class="text-sm">No doctors online</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PATIENTS & ACTIVITIES -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- Recent Patients -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-injured title-blue mr-2"></i> Recent Patients
                </h3>
                <a href="patients.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            
            <div class="scroll-container" id="recentPatientsList">
                <?php if (count($recent_patients) > 0): ?>
                    <?php foreach ($recent_patients as $patient): ?>
                        <div class="flex items-center justify-between p-2 border-b border-gray-100 hover:bg-gray-50 rounded-lg transition">
                            <div class="flex items-center gap-3">
                                <div class="patient-avatar-sm" style="background: <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
                                    <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="font-medium text-sm text-gray-800"><?= htmlspecialchars($patient['full_name']) ?></p>
                                    <p class="text-xs text-gray-500">
                                        <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?> • 
                                        <?= htmlspecialchars($patient['phone'] ?? 'No phone') ?>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-400"><?= isset($patient['created_at']) ? time_ago($patient['created_at']) : 'N/A' ?></p>
                                <a href="view_patient.php?id=<?= $patient['id'] ?>" class="text-primary text-xs hover:underline">View</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-6 text-gray-400">
                        <i class="fas fa-users text-2xl block mb-2"></i>
                        <p class="text-sm">No patients registered yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock title-orange mr-2"></i> Recent Activities
                </h3>
                <a href="activities.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            
            <div class="scroll-container" id="recentActivities">
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-circle text-[6px]"></i>
                            </div>
                            <div class="activity-content">
                                <p class="action"><?= htmlspecialchars($activity['action'] ?? 'Action') ?></p>
                                <p class="details"><?= htmlspecialchars($activity['details'] ?? '') ?></p>
                                <p class="time"><?= isset($activity['created_at']) ? time_ago($activity['created_at']) : 'Just now' ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-clock text-2xl block mb-2"></i>
                        <p class="text-sm">No recent activities</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5">
        <a href="new_patient.php" class="quick-action blue">
            <span class="icon blue"><i class="fas fa-user-plus"></i></span>
            <span class="label">Register Patient</span>
        </a>
        
        <a href="new_appointment.php" class="quick-action green">
            <span class="icon green"><i class="fas fa-calendar-plus"></i></span>
            <span class="label">New Appointment</span>
        </a>
        
        <a href="patients.php" class="quick-action purple">
            <span class="icon purple"><i class="fas fa-users"></i></span>
            <span class="label">View Patients</span>
        </a>
        
        <a href="assign_doctor.php" class="quick-action orange">
            <span class="icon orange"><i class="fas fa-user-md"></i></span>
            <span class="label">Assign Doctor</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Reception Dashboard
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
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
<!-- GLOBAL STATS AUTO-UPDATE -->
<!-- ================================================================ -->
<script src="/dispensary_system/frontend/assets/js/global_stats.js"></script>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
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

    // ================================================================
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<span class="spinner"></span> Loading...';
        btn.disabled = true;
        
        setTimeout(function() {
            window.location.reload();
        }, 1000);
        
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
            showToast('✅ Refreshed', 'Dashboard data updated manually', 'success');
        }, 2000);
    }

    // ================================================================
    // TIME AGO FUNCTION
    // ================================================================
    function time_ago(timestamp) {
        if (!timestamp) return 'N/A';
        var now = new Date();
        var past = new Date(timestamp);
        var diff = Math.floor((now - past) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return past.toLocaleDateString();
    }

    // ================================================================
    // MONITOR GLOBAL STATS UPDATES
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Check if GlobalStats is loaded
        var checkGlobalStats = setInterval(function() {
            if (window.GlobalStats) {
                console.log('%c📊 Global Stats System Connected', 'font-size:14px; font-weight:bold; color:#34D399;');
                console.log('%c🔄 Auto-update every ' + window.GlobalStats.config.updateInterval / 1000 + ' seconds', 'font-size:12px; color:#64748B;');
                clearInterval(checkGlobalStats);
            }
        }, 500);
    });

    console.log('%c🏥 Braick - Reception Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👋 Welcome, <?= htmlspecialchars($user_full_name) ?>!', 'font-size:13px; color:#64748B;');
    console.log('%c🔄 Auto-update every 3 seconds via global_stats.js', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Click any stat card to navigate to relevant page', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Online Doctors card goes to online_doctors.php', 'font-size:13px; color:#059669;');
</script>

</body>
</html>