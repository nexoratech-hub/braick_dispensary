<?php
// ================================================================
// FILE: frontend/pages/reception/dashboard.php
// RECEPTION DASHBOARD - ROSE MWANGI
// FULL VERSION WITH ALL FEATURES
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Rose Mwangi (Reception)
// ================================================================
$_SESSION['user_id'] = 6;
$_SESSION['full_name'] = 'Rose Mwangi';
$_SESSION['role'] = 'reception';
$_SESSION['branch_id'] = 1;
$_SESSION['branch_name'] = 'Dodoma';
$_SESSION['username'] = 'reception.rose';
$_SESSION['is_admin'] = false;

// ================================================================
// PATH SAHIHI - Database Config
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';

// ================================================================
// USER VARIABLES
// ================================================================
$user_full_name = $_SESSION['full_name'] ?? 'Rose Mwangi';
$user_role = $_SESSION['role'] ?? 'reception';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// BRANCH FILTER - Force to user's branch
// ================================================================
$selected_branch_id = $user_branch_id;
$branch_name = $user_branch_name;

// ================================================================
// FETCH DATA FROM DATABASE
// ================================================================
try {
    $db = getDB();
    
    // Branch filter
    $branch_filter = " AND branch_id = ?";
    $params = [$selected_branch_id];
    
    // ================================================================
    // STATISTICS
    // ================================================================
    
    // Total Patients
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM patients WHERE 1=1 " . $branch_filter);
    $stmt->execute($params);
    $total_patients = $stmt->fetch()['total'] ?? 0;
    
    // Today's Patients
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM patients WHERE DATE(created_at) = CURDATE() " . $branch_filter);
    $stmt->execute($params);
    $today_patients = $stmt->fetch()['total'] ?? 0;
    
    // Total Appointments
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE 1=1 " . $branch_filter);
    $stmt->execute($params);
    $total_appointments = $stmt->fetch()['total'] ?? 0;
    
    // Today's Appointments
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_date) = CURDATE() " . $branch_filter);
    $stmt->execute($params);
    $today_appointments = $stmt->fetch()['total'] ?? 0;
    
    // Pending Appointments
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE status IN ('scheduled', 'pending') " . $branch_filter);
    $stmt->execute($params);
    $pending_appointments = $stmt->fetch()['total'] ?? 0;
    
    // Total Doctors
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ?");
    $stmt->execute([$selected_branch_id]);
    $total_doctors = $stmt->fetch()['total'] ?? 0;
    
    // Online Doctors
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND is_online = 1 AND status = 'active' AND branch_id = ?");
    $stmt->execute([$selected_branch_id]);
    $online_doctors = $stmt->fetch()['total'] ?? 0;
    
    // Today's Revenue
    $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as total FROM pharmacy_sales WHERE DATE(sale_date) = CURDATE() AND payment_status = 'paid' " . $branch_filter);
    $stmt->execute($params);
    $today_revenue = $stmt->fetch()['total'] ?? 0;
    
    // ================================================================
    // TODAY'S APPOINTMENTS LIST
    // ================================================================
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name, p.patient_id, u.full_name as doctor_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.doctor_id = u.id
        WHERE DATE(a.appointment_date) = CURDATE() " . $branch_filter . "
        ORDER BY a.appointment_date
        LIMIT 10
    ");
    $stmt->execute($params);
    $today_appointments_list = $stmt->fetchAll();
    
    // ================================================================
    // RECENT PATIENTS
    // ================================================================
    $stmt = $db->prepare("
        SELECT * FROM patients 
        WHERE 1=1 " . $branch_filter . "
        ORDER BY created_at DESC 
        LIMIT 8
    ");
    $stmt->execute($params);
    $recent_patients = $stmt->fetchAll();
    
    // ================================================================
    // ONLINE DOCTORS LIST
    // ================================================================
    $stmt = $db->prepare("
        SELECT id, full_name, specialty 
        FROM users 
        WHERE role = 'doctor' AND is_online = 1 AND status = 'active' AND branch_id = ?
        ORDER BY full_name
    ");
    $stmt->execute([$selected_branch_id]);
    $online_doctors_list = $stmt->fetchAll();
    
    // ================================================================
    // WEEKLY CHART DATA
    // ================================================================
    $chart_labels = [];
    $chart_values = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = date('D', strtotime($date));
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_date) = ? " . $branch_filter);
        $stmt->execute([$date, $selected_branch_id]);
        $chart_values[] = (int)($stmt->fetch()['total'] ?? 0);
    }
    
    // ================================================================
    // RECENT ACTIVITIES
    // ================================================================
    try {
        $stmt = $db->query("
            SELECT action, details, created_at 
            FROM activity_logs 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $recent_activities = $stmt->fetchAll();
    } catch (Exception $e) {
        $recent_activities = [];
    }
    
    // ================================================================
    // BRANCHES FOR SELECTOR (only if admin)
    // ================================================================
    $branches = [];
    if ($_SESSION['is_admin']) {
        $branches = getBranches();
    } else {
        $branch = getBranch($selected_branch_id);
        if ($branch) {
            $branches[] = $branch;
        }
    }
    
} catch (Exception $e) {
    // Fallback data
    $total_patients = 0;
    $today_patients = 0;
    $total_appointments = 0;
    $today_appointments = 0;
    $pending_appointments = 0;
    $total_doctors = 0;
    $online_doctors = 0;
    $today_revenue = 0;
    $today_appointments_list = [];
    $recent_patients = [];
    $online_doctors_list = [];
    $recent_activities = [];
    $branches = [];
    $chart_labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $chart_values = [0, 0, 0, 0, 0, 0, 0];
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_notifications = $stmt->fetch()['total'] ?? 0;
    } catch (Exception $e) {
        $unread_notifications = 0;
    }
}

// ================================================================
// LOGO PATH
// ================================================================
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
    <title>Braick Dispensary - Reception Dashboard</title>
    
    <!-- Favicon -->
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        /* ================================================================
           BRAICK DISPENSARY - COMPLETE STYLES
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
            font-family: 'Inter', 'Segoe UI', sans-serif;
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
        
        .top-nav .branch-selector {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            background: var(--bg-card);
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            outline: none;
            min-width: 160px;
            color: var(--text-primary);
            transition: all 0.3s;
        }
        
        .top-nav .branch-selector:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .top-nav .branch-selector:disabled {
            opacity: 0.7;
            cursor: not-allowed;
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
        
        .notif-dot.has-notif {
            background: var(--danger);
        }
        
        .notif-dot.no-notif {
            background: var(--gray-400);
            animation: none;
        }
        
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
        
        .dark-toggle-btn i {
            font-size: 0.9rem;
        }
        
        .role-badge {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
        }
        
        [data-theme="dark"] .role-badge {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .branch-badge {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge {
            background: #1A3A2A;
            color: #34D399;
        }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
        /* ================================================================
           STAT CARDS
           ================================================================ */
        .stat-card {
            border-radius: 16px;
            padding: 18px 20px;
            border: none;
            transition: all 0.3s;
            color: white;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card.blue { background: var(--primary); }
        .stat-card.blue-dark { background: var(--primary-dark); }
        .stat-card.green { background: var(--success); }
        .stat-card.green-dark { background: var(--success-dark); }
        .stat-card.purple { background: #7C3AED; }
        .stat-card.orange { background: #D97706; }
        .stat-card.red { background: var(--danger); }
        
        .stat-card .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            background: rgba(255,255,255,0.15);
            color: white;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
        }
        
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.8);
            font-weight: 500;
        }
        
        .stat-card .stat-trend {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: rgba(255,255,255,0.15);
            color: white;
        }
        
        /* ================================================================
           CARDS
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 18px 20px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
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
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.78rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-blue {
            background: var(--primary);
            color: white;
        }
        .btn-blue:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-green {
            background: var(--success);
            color: white;
        }
        .btn-green:hover {
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
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: white;
            border: none;
        }
        
        .badge-blue { background: var(--primary); }
        .badge-green { background: var(--success); }
        .badge-gray { background: var(--gray-500); }
        .badge-yellow { background: var(--warning); }
        .badge-red { background: var(--danger); }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            border-bottom: 3px solid var(--primary);
            padding-bottom: 12px;
        }
        
        .page-header .page-title {
            color: var(--primary-dark);
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        [data-theme="dark"] .page-header .page-title {
            color: var(--primary-light);
        }
        
        .page-header .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .page-header .branch-tag {
            background: var(--success);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        /* ================================================================
           APPOINTMENT ITEMS
           ================================================================ */
        .appointment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
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
            font-size: 0.78rem;
            color: var(--text-primary);
            min-width: 65px;
        }
        
        .appointment-patient .name {
            font-weight: 500;
            font-size: 0.85rem;
            color: var(--text-primary);
        }
        
        .appointment-patient .doctor {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .appointment-status {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 12px;
        }
        
        .appointment-status.confirmed { background: #D1FAE5; color: #059669; }
        .appointment-status.pending { background: #FEF3C7; color: #D97706; }
        .appointment-status.scheduled { background: #E8F0FE; color: #0B5ED7; }
        .appointment-status.completed { background: #D1FAE5; color: #059669; }
        .appointment-status.cancelled { background: #FEE2E2; color: #DC2626; }
        .appointment-status.in-progress { background: #FEF3C7; color: #D97706; }
        
        [data-theme="dark"] .appointment-status.confirmed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .appointment-status.pending { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .appointment-status.scheduled { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .appointment-status.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .appointment-status.cancelled { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .appointment-status.in-progress { background: #3D2E0A; color: #FBBF24; }
        
        .online-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #059669;
            animation: pulse-dot 1.5s infinite;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        
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
            font-size: 0.7rem;
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
           QUICK ACTION CARDS
           ================================================================ */
        .quick-action {
            padding: 16px;
            border-radius: 14px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
        }
        
        .quick-action:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
            box-shadow: 0 8px 25px rgba(11, 94, 215, 0.12);
        }
        
        .quick-action .icon {
            font-size: 1.8rem;
            display: block;
            margin-bottom: 6px;
        }
        
        .quick-action .label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .quick-action .icon.blue { color: var(--primary); }
        .quick-action .icon.green { color: var(--success); }
        .quick-action .icon.purple { color: #7C3AED; }
        .quick-action .icon.orange { color: #D97706; }
        .quick-action .icon.red { color: var(--danger); }
        
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
           CHART
           ================================================================ */
        .chart-container {
            height: 180px;
        }
        
        .chart-container canvas {
            height: 100% !important;
            width: 100% !important;
        }
        
        /* ================================================================
           BADGES DISPLAY
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
        
        .welcome-text {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        [data-theme="dark"] .welcome-text {
            background: linear-gradient(135deg, #6EA8FE, #3B82F6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 12px;
            z-index: 999;
            max-width: 360px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        
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
            transition: all 0.3s ease;
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
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
        
        @keyframes spin {
            to { transform: rotate(360deg); }
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
            .top-nav .branch-selector { min-width: 120px; font-size: 0.7rem; }
            .top-nav .datetime { display: none; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .stat-card .stat-number { font-size: 1.4rem; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
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
            <input type="text" id="searchInput" placeholder="Search patients by name, ID or phone...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <!-- Branch display - fixed -->
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn" title="Notifications">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?>" alt="Profile" class="avatar"
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
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-home mr-2" style="color: var(--primary);"></i> Reception Dashboard
                <span class="role-badge-display ml-2">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                Welcome back, <strong class="welcome-text"><?= htmlspecialchars($user_full_name) ?></strong>!
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-clock mr-1"></i> <?= date('h:i A') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="new_patient.php?branch=<?= $selected_branch_id ?>" class="btn btn-blue btn-sm">
                <i class="fas fa-user-plus"></i> Register Patient
            </a>
            <a href="new_appointment.php?branch=<?= $selected_branch_id ?>" class="btn btn-green btn-sm">
                <i class="fas fa-plus-circle"></i> New Appointment
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
        
        <div class="stat-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Patients</p>
                    <p class="stat-number"><?= number_format($total_patients) ?></p>
                    <span class="stat-trend"><i class="fas fa-arrow-up"></i> All time</span>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        
        <div class="stat-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Today's Patients</p>
                    <p class="stat-number"><?= number_format($today_patients) ?></p>
                    <span class="stat-trend"><i class="fas fa-user-plus"></i> New today</span>
                </div>
                <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
            </div>
        </div>
        
        <div class="stat-card purple animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Appointments</p>
                    <p class="stat-number"><?= number_format($total_appointments) ?></p>
                    <span class="stat-trend"><i class="fas fa-calendar-alt"></i> All time</span>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            </div>
        </div>
        
        <div class="stat-card orange animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Today's Appointments</p>
                    <p class="stat-number"><?= number_format($today_appointments) ?></p>
                    <span class="stat-trend"><i class="fas fa-clock"></i> <?= $pending_appointments ?> pending</span>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            </div>
        </div>
        
        <div class="stat-card blue-dark animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Online Doctors</p>
                    <p class="stat-number"><?= number_format($online_doctors) ?></p>
                    <span class="stat-trend"><i class="fas fa-user-md"></i> Available</span>
                </div>
                <div class="stat-icon"><i class="fas fa-user-md"></i></div>
            </div>
        </div>
        
        <div class="stat-card green-dark animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Today's Revenue</p>
                    <p class="stat-number">TSh <?= number_format($today_revenue) ?></p>
                    <span class="stat-trend"><i class="fas fa-money-bill-wave"></i> Today</span>
                </div>
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- CHART & ONLINE DOCTORS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <div class="lg:col-span-2 card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line title-blue mr-2"></i> Weekly Appointments Overview
                    <span class="text-sm font-normal text-gray-400">(Last 7 days)</span>
                </h3>
                <span class="text-xs text-gray-400">Total: <?= array_sum($chart_values) ?> appointments</span>
            </div>
            <div class="chart-container">
                <canvas id="appointmentsChart"></canvas>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-md title-green mr-2"></i> Online Doctors
                    <span class="text-sm font-normal text-gray-400">(<?= count($online_doctors_list) ?> online)</span>
                </h3>
            </div>
            
            <div class="scroll-container" style="max-height: 180px;">
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
            
            <div class="mt-3 pt-3 border-t text-center">
                <a href="../admin/doctors_list.php?branch=<?= $selected_branch_id ?>" class="text-primary text-sm hover:underline">
                    <i class="fas fa-arrow-right mr-1"></i> View all doctors
                </a>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TODAY'S APPOINTMENTS & RECENT PATIENTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-check title-blue mr-2"></i> Today's Appointments
                    <span class="text-sm font-normal text-gray-400">(<?= count($today_appointments_list) ?>)</span>
                </h3>
                <a href="appointments.php?branch=<?= $selected_branch_id ?>" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            
            <div class="scroll-container" style="max-height: 220px;">
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
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-injured title-green mr-2"></i> Recent Patients
                </h3>
                <a href="patients.php?branch=<?= $selected_branch_id ?>" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            
            <div class="scroll-container" style="max-height: 220px;">
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
                                <a href="view_patient.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" class="text-primary text-xs hover:underline">View</a>
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
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS & RECENT ACTIVITIES -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        <div class="lg:col-span-2">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-bolt title-blue mr-2"></i> Quick Actions
                    </h3>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <a href="new_patient.php?branch=<?= $selected_branch_id ?>" class="quick-action blue">
                        <span class="icon blue"><i class="fas fa-user-plus"></i></span>
                        <span class="label">Register Patient</span>
                    </a>
                    
                    <a href="new_appointment.php?branch=<?= $selected_branch_id ?>" class="quick-action green">
                        <span class="icon green"><i class="fas fa-calendar-plus"></i></span>
                        <span class="label">New Appointment</span>
                    </a>
                    
                    <a href="patients.php?branch=<?= $selected_branch_id ?>" class="quick-action purple">
                        <span class="icon purple"><i class="fas fa-users"></i></span>
                        <span class="label">View Patients</span>
                    </a>
                    
                    <a href="assign_doctor.php?branch=<?= $selected_branch_id ?>" class="quick-action orange">
                        <span class="icon orange"><i class="fas fa-user-md"></i></span>
                        <span class="label">Assign Doctor</span>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock title-blue mr-2"></i> Recent Activities
                </h3>
                <a href="activities.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            
            <div class="scroll-container" style="max-height: 180px;">
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
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Reception Dashboard
            <span class="text-gray-300 mx-2">|</span>
            Version 2.0
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
<!-- JAVASCRIPT - NO AUTO REFRESH -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE - MANUAL ONLY
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
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    // ================================================================
    // CHART - RENDER ONCE ONLY
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('appointmentsChart')?.getContext('2d');
        if (ctx && typeof Chart !== 'undefined') {
            var labels = <?= json_encode($chart_labels) ?>;
            var values = <?= json_encode($chart_values) ?>;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Appointments',
                        data: values,
                        backgroundColor: '#0B5ED7',
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.raw + ' appointments';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            ticks: { 
                                stepSize: 1,
                                color: '#64748B'
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#64748B' }
                        }
                    }
                }
            });
        }
    });

    // ================================================================
    // DATE & TIME - LIVE CLOCK ONLY
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
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH - USER CLICK ONLY
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
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
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
            searchInput?.select();
        }
        if (e.altKey && e.key === 'n') {
            e.preventDefault();
            window.location.href = 'new_patient.php?branch=<?= $selected_branch_id ?>';
        }
        if (e.altKey && e.key === 'a') {
            e.preventDefault();
            window.location.href = 'new_appointment.php?branch=<?= $selected_branch_id ?>';
        }
        if (e.key === 'Escape' && document.activeElement === searchInput) {
            searchInput.value = '';
            searchInput.blur();
        }
    });

    // ================================================================
    // CONSOLE INFO
    // ================================================================
    console.log('%c🏥 Braick Dispensary - Reception Dashboard', 'font-size:20px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👋 Welcome, <?= htmlspecialchars($user_full_name) ?>!', 'font-size:14px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👑 Role: RECEPTION', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Total Patients: <?= number_format($total_patients) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📅 Today Appointments: <?= number_format($today_appointments) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Today Revenue: TSh <?= number_format($today_revenue) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🚫 Auto Refresh: DISABLED', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>