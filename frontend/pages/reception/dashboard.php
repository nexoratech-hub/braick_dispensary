<?php
// ================================================================
// FILE: frontend/pages/reception/dashboard.php
// RECEPTION DASHBOARD - WITH TODAY PATIENTS & TODAY VISITS
// WITH AJAX AUTO-UPDATE (3 SECONDS) - FIXED
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
    $today = date('Y-m-d');
    
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
    
    // Total Visits
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE 1=1 " . $branch_filter);
    $stmt->execute($params);
    $total_visits = $stmt->fetch()['total'] ?? 0;
    
    // Today's Patients - PENDING
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT patient_id) as count 
        FROM visits 
        WHERE branch_id = ? AND DATE(created_at) = ? AND status IN ('pending', 'assigned')
    ");
    $stmt->execute([$selected_branch_id, $today]);
    $today_patients_pending = $stmt->fetch()['count'] ?? 0;
    
    // Today's Patients - COMPLETED
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT patient_id) as count 
        FROM visits 
        WHERE branch_id = ? AND DATE(created_at) = ? AND status = 'completed'
    ");
    $stmt->execute([$selected_branch_id, $today]);
    $today_patients_completed = $stmt->fetch()['count'] ?? 0;
    $today_patients_total = $today_patients_pending + $today_patients_completed;
    
    // Today's Visits - PENDING
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE branch_id = ? AND DATE(created_at) = ? AND status IN ('pending', 'assigned')
    ");
    $stmt->execute([$selected_branch_id, $today]);
    $today_visits_pending = $stmt->fetch()['count'] ?? 0;
    
    // Today's Visits - COMPLETED
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE branch_id = ? AND DATE(created_at) = ? AND status = 'completed'
    ");
    $stmt->execute([$selected_branch_id, $today]);
    $today_visits_completed = $stmt->fetch()['count'] ?? 0;
    $today_visits_total = $today_visits_pending + $today_visits_completed;
    
    // Total Appointments
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE 1=1 " . $branch_filter);
    $stmt->execute($params);
    $total_appointments = $stmt->fetch()['total'] ?? 0;
    
    // Today's Appointments - PENDING
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE branch_id = ? AND DATE(appointment_date) = ? 
        AND status IN ('scheduled', 'pending', 'confirmed')
    ");
    $stmt->execute([$selected_branch_id, $today]);
    $today_appointments_pending = $stmt->fetch()['count'] ?? 0;
    
    // Today's Appointments - COMPLETED
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE branch_id = ? AND DATE(appointment_date) = ? AND status = 'completed'
    ");
    $stmt->execute([$selected_branch_id, $today]);
    $today_appointments_completed = $stmt->fetch()['count'] ?? 0;
    $today_appointments_total = $today_appointments_pending + $today_appointments_completed;
    
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
    // UNREAD NOTIFICATIONS
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
    
} catch (Exception $e) {
    // Fallback data - FIXED: Added $total_visits
    $total_patients = 0;
    $total_visits = 0;  // ← HII ILIKOSA AWALI!
    $today_patients_pending = 0;
    $today_patients_completed = 0;
    $today_patients_total = 0;
    $today_visits_pending = 0;
    $today_visits_completed = 0;
    $today_visits_total = 0;
    $total_appointments = 0;
    $today_appointments_pending = 0;
    $today_appointments_completed = 0;
    $today_appointments_total = 0;
    $pending_appointments = 0;
    $total_doctors = 0;
    $online_doctors = 0;
    $today_revenue = 0;
    $today_appointments_list = [];
    $recent_patients = [];
    $online_doctors_list = [];
    $recent_activities = [];
    $unread_notifications = 0;
    $chart_labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $chart_values = [0, 0, 0, 0, 0, 0, 0];
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

<!-- Rest of the HTML stays the same -->
<!-- Make sure line 1361 uses $total_visits properly -->

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
           COMPLETE STYLES
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
           STAT CARDS - CLICKABLE
           ================================================================ */
        .stat-card-clickable {
            border-radius: 16px;
            padding: 18px 20px;
            border: none;
            transition: all 0.3s;
            color: white;
            text-decoration: none;
            display: block;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card-clickable:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
        }
        
        .stat-card-clickable.blue { background: var(--primary); }
        .stat-card-clickable.blue-dark { background: var(--primary-dark); }
        .stat-card-clickable.green { background: var(--success); }
        .stat-card-clickable.green-dark { background: var(--success-dark); }
        .stat-card-clickable.purple { background: #7C3AED; }
        .stat-card-clickable.orange { background: #D97706; }
        .stat-card-clickable.red { background: var(--danger); }
        .stat-card-clickable.teal { background: #0D9488; }
        
        .stat-card-clickable .stat-icon {
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
        
        .stat-card-clickable .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
        }
        
        .stat-card-clickable .stat-label {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.8);
            font-weight: 500;
        }
        
        .stat-card-clickable .stat-details {
            display: flex;
            gap: 12px;
            margin-top: 4px;
            flex-wrap: wrap;
        }
        
        .stat-card-clickable .stat-detail {
            font-size: 0.6rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
            color: rgba(255,255,255,0.7);
        }
        
        .stat-card-clickable .stat-detail.pending { color: #FCD34D; }
        .stat-card-clickable .stat-detail.completed { color: #6EE7B7; }
        
        .stat-card-clickable .stat-progress {
            height: 3px;
            background: rgba(255,255,255,0.2);
            border-radius: 0 0 16px 16px;
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            overflow: hidden;
        }
        
        .stat-card-clickable .stat-progress .stat-progress-bar {
            height: 100%;
            background: rgba(255,255,255,0.5);
            transition: width 0.5s ease;
        }
        
        .stat-card-clickable .stat-badge {
            position: absolute;
            top: 10px;
            right: 14px;
            font-size: 0.6rem;
            font-weight: 700;
            background: rgba(255,255,255,0.2);
            padding: 2px 10px;
            border-radius: 20px;
            color: white;
            animation: pulse-badge 2s infinite;
        }
        
        .stat-card-clickable .stat-badge.danger {
            background: #EF4444;
        }
        
        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
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
        .card-title .title-orange { color: #D97706; }
        
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
        
        .update-badge {
            font-size: 0.65rem;
            color: var(--text-secondary);
            background: var(--bg-body);
            padding: 2px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .update-badge .fa-spin {
            animation: fa-spin 2s infinite linear;
        }
        
        @keyframes fa-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
            .stat-card-clickable .stat-number { font-size: 1.4rem; }
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
                <span class="update-badge ml-2" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                Welcome back, <strong class="welcome-text"><?= htmlspecialchars($user_full_name) ?></strong>!
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200" id="liveTime">
                    <i class="fas fa-clock mr-1"></i> <?= date('h:i:s A') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="new_patient.php" class="btn btn-blue btn-sm">
                <i class="fas fa-user-plus"></i> Register Patient
            </a>
            <a href="new_appointment.php" class="btn btn-green btn-sm">
                <i class="fas fa-plus-circle"></i> New Appointment
            </a>
            <button onclick="manualRefresh()" class="btn btn-outline btn-sm" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 8 STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-5">
        
        <!-- CARD 1: Today's Patients -->
        <a href="visits.php?filter=today" class="stat-card-clickable blue" id="cardTodayPatients">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Today's Patients</p>
                    <p class="stat-number" id="todayPatientsTotal"><?= $today_patients_total ?></p>
                    <div class="stat-details">
                        <span class="stat-detail pending" id="todayPatientsPending">
                            <i class="fas fa-clock"></i> <?= $today_patients_pending ?> Pending
                        </span>
                        <span class="stat-detail completed" id="todayPatientsCompleted">
                            <i class="fas fa-check-circle"></i> <?= $today_patients_completed ?> Complete
                        </span>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-user-injured"></i></div>
            </div>
            <div class="stat-progress">
                <div class="stat-progress-bar" id="todayPatientsProgress" style="width: <?= $today_patients_total > 0 ? min(100, ($today_patients_completed / max($today_patients_total, 1)) * 100) : 0 ?>%;"></div>
            </div>
        </a>
        
        <!-- CARD 2: Today's Visits -->
        <a href="visits.php?filter=today" class="stat-card-clickable green" id="cardTodayVisits">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Today's Visits</p>
                    <p class="stat-number" id="todayVisitsTotal"><?= $today_visits_total ?></p>
                    <div class="stat-details">
                        <span class="stat-detail pending" id="todayVisitsPending">
                            <i class="fas fa-clock"></i> <?= $today_visits_pending ?> Pending
                        </span>
                        <span class="stat-detail completed" id="todayVisitsCompleted">
                            <i class="fas fa-check-circle"></i> <?= $today_visits_completed ?> Complete
                        </span>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-clinic-medical"></i></div>
            </div>
            <div class="stat-progress">
                <div class="stat-progress-bar" id="todayVisitsProgress" style="width: <?= $today_visits_total > 0 ? min(100, ($today_visits_completed / max($today_visits_total, 1)) * 100) : 0 ?>%;"></div>
            </div>
        </a>
        
        <!-- CARD 3: Today's Appointments -->
        <a href="appointments.php?filter=today" class="stat-card-clickable purple" id="cardTodayAppointments">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Today's Appointments</p>
                    <p class="stat-number" id="todayAppointmentsTotal"><?= $today_appointments_total ?></p>
                    <div class="stat-details">
                        <span class="stat-detail pending" id="todayAppointmentsPending">
                            <i class="fas fa-clock"></i> <?= $today_appointments_pending ?> Pending
                        </span>
                        <span class="stat-detail completed" id="todayAppointmentsCompleted">
                            <i class="fas fa-check-circle"></i> <?= $today_appointments_completed ?> Complete
                        </span>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            </div>
            <div class="stat-progress">
                <div class="stat-progress-bar" id="todayAppointmentsProgress" style="width: <?= $today_appointments_total > 0 ? min(100, ($today_appointments_completed / max($today_appointments_total, 1)) * 100) : 0 ?>%;"></div>
            </div>
        </a>
        
        <!-- CARD 4: Total Appointments -->
        <a href="appointments.php" class="stat-card-clickable teal" id="cardTotalAppointments">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Appointments</p>
                    <p class="stat-number" id="totalAppointments"><?= number_format($total_appointments) ?></p>
                    <div class="stat-details">
                        <span class="stat-detail">
                            <i class="fas fa-arrow-up"></i> All time
                        </span>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            </div>
            <div class="stat-progress">
                <div class="stat-progress-bar" style="width: <?= $total_appointments > 0 ? min(100, ($total_appointments / 200) * 100) : 0 ?>%;"></div>
            </div>
        </a>
        
        <!-- CARD 5: Total Patients -->
        <a href="patients.php" class="stat-card-clickable blue-dark" id="cardTotalPatients">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Patients</p>
                    <p class="stat-number" id="totalPatients"><?= number_format($total_patients) ?></p>
                    <div class="stat-details">
                        <span class="stat-detail">
                            <i class="fas fa-arrow-up"></i> All time
                        </span>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="stat-progress">
                <div class="stat-progress-bar" style="width: <?= $total_patients > 0 ? min(100, ($total_patients / 200) * 100) : 0 ?>%;"></div>
            </div>
        </a>
        
        <!-- CARD 6: Total Visits -->
        <a href="visits.php" class="stat-card-clickable green-dark" id="cardTotalVisits">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Visits</p>
                    <p class="stat-number" id="totalVisits"><?= number_format($total_visits) ?></p>
                    <div class="stat-details">
                        <span class="stat-detail">
                            <i class="fas fa-arrow-up"></i> All time
                        </span>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-notes-medical"></i></div>
            </div>
            <div class="stat-progress">
                <div class="stat-progress-bar" style="width: <?= $total_visits > 0 ? min(100, ($total_visits / 500) * 100) : 0 ?>%;"></div>
            </div>
        </a>
        
        <!-- CARD 7: Pending Appointments -->
        <a href="appointments.php?status=pending" class="stat-card-clickable orange" id="cardPendingAppointments">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Pending Appointments</p>
                    <p class="stat-number" id="pendingAppointments"><?= $pending_appointments ?></p>
                    <div class="stat-details">
                        <span class="stat-detail">
                            <i class="fas fa-clock"></i> Awaiting confirmation
                        </span>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
            <?php if ($pending_appointments > 0): ?>
                <span class="stat-badge danger"><?= $pending_appointments ?></span>
            <?php endif; ?>
            <div class="stat-progress">
                <div class="stat-progress-bar" style="width: <?= $pending_appointments > 0 ? min(100, ($pending_appointments / 20) * 100) : 0 ?>%;"></div>
            </div>
        </a>
        
        <!-- CARD 8: Online Doctors - CLICKABLE -->
        <a href="online_doctors.php" class="stat-card-clickable purple" id="cardOnlineDoctors">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Online Doctors</p>
                    <p class="stat-number" id="onlineDoctors"><?= $online_doctors ?></p>
                    <div class="stat-details">
                        <span class="stat-detail">
                            <i class="fas fa-user-md"></i> Available now
                        </span>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-user-md"></i></div>
            </div>
            <?php if ($online_doctors > 0): ?>
                <span class="stat-badge" style="background: #059669;"><?= $online_doctors ?></span>
            <?php endif; ?>
            <div class="stat-progress">
                <div class="stat-progress-bar" style="width: <?= $online_doctors > 0 ? min(100, ($online_doctors / 10) * 100) : 0 ?>%;"></div>
            </div>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- CHART & ONLINE DOCTORS LIST -->
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
                    <span class="text-sm font-normal text-gray-400" id="onlineDoctorsCount">(<?= count($online_doctors_list) ?> online)</span>
                </h3>
                <a href="online_doctors.php" class="text-primary text-sm hover:underline">
                    <i class="fas fa-arrow-right mr-1"></i> View all
                </a>
            </div>
            
            <div class="scroll-container" style="max-height: 180px;" id="onlineDoctorsList">
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
                <a href="online_doctors.php" class="text-primary text-sm hover:underline">
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
                    <span class="text-sm font-normal text-gray-400" id="appointmentsCount">(<?= count($today_appointments_list) ?>)</span>
                </h3>
                <a href="appointments.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            
            <div class="scroll-container" style="max-height: 220px;" id="appointmentsList">
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
                <a href="patients.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            
            <div class="scroll-container" style="max-height: 220px;" id="recentPatientsList">
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
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock title-blue mr-2"></i> Recent Activities
                </h3>
                <a href="activities.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            
            <div class="scroll-container" style="max-height: 180px;" id="recentActivities">
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
<!-- JAVASCRIPT - WITH AJAX AUTO-UPDATE (3 SECONDS) -->
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
    // CHART - INITIAL RENDER
    // ================================================================
    var chartInstance = null;
    var chartLabels = <?= json_encode($chart_labels) ?>;
    var chartValues = <?= json_encode($chart_values) ?>;
    
    function renderChart(labels, values) {
        var ctx = document.getElementById('appointmentsChart')?.getContext('2d');
        if (!ctx) return;
        
        if (chartInstance) {
            chartInstance.destroy();
        }
        
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var gridColor = isDark ? '#334155' : '#E2E8F0';
        var textColor = isDark ? '#94A3B8' : '#64748B';
        
        chartInstance = new Chart(ctx, {
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
                        grid: { color: gridColor },
                        ticks: { 
                            stepSize: 1,
                            color: textColor
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor }
                    }
                }
            }
        });
    }

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
    // AJAX AUTO-UPDATE - SMART (3 SECONDS)
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var lastHash = null;
    var updateCount = 0;

    function fetchAndUpdateStats() {
        if (isUpdating) return;
        isUpdating = true;
        
        updateCount++;
        
        // Update badge every 3 updates
        if (updateCount % 3 === 0) {
            document.getElementById('updateBadge').innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Updating...';
        }
        
        fetch('get_reception_stats.php')
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    // Check if data has changed
                    if (lastHash !== data.hash) {
                        lastHash = data.hash;
                        updateDashboard(data.data);
                        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + data.data.timestamp;
                        
                        // Show notification on change
                        if (updateCount > 1) {
                            showToast('🔄 Updated', 'Dashboard auto-updated at ' + data.data.timestamp, 'info');
                        }
                    }
                    
                    // Update badge
                    var now = new Date();
                    document.getElementById('updateBadge').innerHTML = 
                        '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + 
                        now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Update error:', error);
                document.getElementById('updateBadge').innerHTML = '<i class="fas fa-exclamation-circle" style="color:#EF4444;"></i> Error';
                isUpdating = false;
            });
    }

    function updateDashboard(data) {
        // ================================================================
        // CARD 1: Today's Patients
        // ================================================================
        document.getElementById('todayPatientsTotal').textContent = data.today_patients.total;
        document.getElementById('todayPatientsPending').innerHTML = '<i class="fas fa-clock"></i> ' + data.today_patients.pending + ' Pending';
        document.getElementById('todayPatientsCompleted').innerHTML = '<i class="fas fa-check-circle"></i> ' + data.today_patients.completed + ' Complete';
        var pct1 = data.today_patients.total > 0 ? Math.min(100, (data.today_patients.completed / Math.max(data.today_patients.total, 1)) * 100) : 0;
        document.getElementById('todayPatientsProgress').style.width = pct1 + '%';

        // ================================================================
        // CARD 2: Today's Visits
        // ================================================================
        document.getElementById('todayVisitsTotal').textContent = data.today_visits.total;
        document.getElementById('todayVisitsPending').innerHTML = '<i class="fas fa-clock"></i> ' + data.today_visits.pending + ' Pending';
        document.getElementById('todayVisitsCompleted').innerHTML = '<i class="fas fa-check-circle"></i> ' + data.today_visits.completed + ' Complete';
        var pct2 = data.today_visits.total > 0 ? Math.min(100, (data.today_visits.completed / Math.max(data.today_visits.total, 1)) * 100) : 0;
        document.getElementById('todayVisitsProgress').style.width = pct2 + '%';

        // ================================================================
        // CARD 3: Today's Appointments
        // ================================================================
        document.getElementById('todayAppointmentsTotal').textContent = data.today_appointments.total;
        document.getElementById('todayAppointmentsPending').innerHTML = '<i class="fas fa-clock"></i> ' + data.today_appointments.pending + ' Pending';
        document.getElementById('todayAppointmentsCompleted').innerHTML = '<i class="fas fa-check-circle"></i> ' + data.today_appointments.completed + ' Complete';
        var pct3 = data.today_appointments.total > 0 ? Math.min(100, (data.today_appointments.completed / Math.max(data.today_appointments.total, 1)) * 100) : 0;
        document.getElementById('todayAppointmentsProgress').style.width = pct3 + '%';

        // ================================================================
        // CARD 4: Total Appointments
        // ================================================================
        document.getElementById('totalAppointments').textContent = Number(data.total_appointments).toLocaleString();

        // ================================================================
        // CARD 5: Total Patients
        // ================================================================
        document.getElementById('totalPatients').textContent = Number(data.total_patients).toLocaleString();

        // ================================================================
        // CARD 6: Total Visits
        // ================================================================
        document.getElementById('totalVisits').textContent = Number(data.total_visits).toLocaleString();

        // ================================================================
        // CARD 7: Pending Appointments
        // ================================================================
        document.getElementById('pendingAppointments').textContent = data.pending_appointments;
        var badge = document.querySelector('#cardPendingAppointments .stat-badge');
        if (data.pending_appointments > 0) {
            if (!badge) {
                var card = document.getElementById('cardPendingAppointments');
                var span = document.createElement('span');
                span.className = 'stat-badge danger';
                span.textContent = data.pending_appointments;
                card.appendChild(span);
            } else {
                badge.textContent = data.pending_appointments;
                badge.style.display = 'inline-block';
            }
        } else {
            if (badge) badge.style.display = 'none';
        }

        // ================================================================
        // CARD 8: Online Doctors
        // ================================================================
        document.getElementById('onlineDoctors').textContent = data.online_doctors;

        // ================================================================
        // ONLINE DOCTORS LIST
        // ================================================================
        var onlineList = document.getElementById('onlineDoctorsList');
        document.getElementById('onlineDoctorsCount').textContent = '(' + data.online_doctors + ' online)';
        
        if (data.online_doctors_list && data.online_doctors_list.length > 0) {
            var listHtml = '';
            data.online_doctors_list.forEach(function(doc) {
                var initial = doc.full_name.charAt(0).toUpperCase();
                listHtml += `
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-primary-bg transition mb-1">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white text-sm font-bold">
                                ${escapeHtml(initial)}
                            </div>
                            <div>
                                <p class="font-medium text-sm text-gray-800">${escapeHtml(doc.full_name)}</p>
                                <p class="text-xs text-gray-500">${escapeHtml(doc.specialty || 'General Practitioner')}</p>
                            </div>
                        </div>
                        <span class="online-dot" title="Online"></span>
                    </div>
                `;
            });
            onlineList.innerHTML = listHtml;
        } else {
            onlineList.innerHTML = `
                <div class="text-center py-4 text-gray-400">
                    <i class="fas fa-user-md text-2xl block mb-2"></i>
                    <p class="text-sm">No doctors online</p>
                </div>
            `;
        }

        // ================================================================
        // APPOINTMENTS LIST
        // ================================================================
        var appointmentsList = document.getElementById('appointmentsList');
        document.getElementById('appointmentsCount').textContent = '(' + data.today_appointments.list.length + ')';
        
        if (data.today_appointments.list && data.today_appointments.list.length > 0) {
            var listHtml = '';
            data.today_appointments.list.forEach(function(appt) {
                var time = formatTime(appt.appointment_date);
                var statusClass = appt.status || 'scheduled';
                listHtml += `
                    <div class="appointment-item">
                        <span class="appointment-time">${time}</span>
                        <div class="appointment-patient flex-1 ml-3">
                            <span class="name">${escapeHtml(appt.patient_name)}</span>
                            <span class="doctor block">Dr. ${escapeHtml(appt.doctor_name)}</span>
                        </div>
                        <span class="appointment-status ${statusClass}">
                            ${capitalize(statusClass)}
                        </span>
                    </div>
                `;
            });
            appointmentsList.innerHTML = listHtml;
        } else {
            appointmentsList.innerHTML = `
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-calendar-check text-2xl block mb-2"></i>
                    <p class="text-sm">No appointments scheduled for today</p>
                </div>
            `;
        }

        // ================================================================
        // RECENT PATIENTS
        // ================================================================
        var recentPatients = document.getElementById('recentPatientsList');
        if (data.recent_patients && data.recent_patients.length > 0) {
            var listHtml = '';
            data.recent_patients.forEach(function(patient) {
                var initial = patient.full_name.charAt(0).toUpperCase();
                var color = '#' + patient.full_name.split('').reduce(function(a, b) {
                    a = ((a << 5) - a) + b.charCodeAt(0);
                    return a & a;
                }, 0).toString(16).padStart(6, '0');
                listHtml += `
                    <div class="flex items-center justify-between p-2 border-b border-gray-100 hover:bg-gray-50 rounded-lg transition">
                        <div class="flex items-center gap-3">
                            <div class="patient-avatar-sm" style="background: ${color};">
                                ${escapeHtml(initial)}
                            </div>
                            <div>
                                <p class="font-medium text-sm text-gray-800">${escapeHtml(patient.full_name)}</p>
                                <p class="text-xs text-gray-500">
                                    ${escapeHtml(patient.patient_id || 'N/A')} • ${escapeHtml(patient.phone || 'No phone')}
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-400">${patient.created_at ? timeAgo(patient.created_at) : 'N/A'}</p>
                            <a href="view_patient.php?id=${patient.id}" class="text-primary text-xs hover:underline">View</a>
                        </div>
                    </div>
                `;
            });
            recentPatients.innerHTML = listHtml;
        } else {
            recentPatients.innerHTML = `
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-users text-2xl block mb-2"></i>
                    <p class="text-sm">No patients registered yet</p>
                </div>
            `;
        }

        // ================================================================
        // RECENT ACTIVITIES
        // ================================================================
        var activities = document.getElementById('recentActivities');
        if (data.recent_activities && data.recent_activities.length > 0) {
            var listHtml = '';
            data.recent_activities.forEach(function(activity) {
                listHtml += `
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-circle text-[6px]"></i>
                        </div>
                        <div class="activity-content">
                            <p class="action">${escapeHtml(activity.action || 'Action')}</p>
                            <p class="details">${escapeHtml(activity.details || '')}</p>
                            <p class="time">${activity.created_at ? timeAgo(activity.created_at) : 'Just now'}</p>
                        </div>
                    </div>
                `;
            });
            activities.innerHTML = listHtml;
        } else {
            activities.innerHTML = `
                <div class="text-center py-4 text-gray-400">
                    <i class="fas fa-clock text-2xl block mb-2"></i>
                    <p class="text-sm">No recent activities</p>
                </div>
            `;
        }
    }

    // ================================================================
    // HELPER FUNCTIONS
    // ================================================================
    function formatTime(datetime) {
        var d = new Date(datetime);
        return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function capitalize(text) {
        return text.charAt(0).toUpperCase() + text.slice(1);
    }
    
    function timeAgo(timestamp) {
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
    // START AUTO-UPDATE
    // ================================================================
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        updateInterval = setInterval(fetchAndUpdateStats, 3000);
        document.getElementById('updateBadge').innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Live mode active';
        fetchAndUpdateStats();
    }

    // ================================================================
    // STOP AUTO-UPDATE
    // ================================================================
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            document.getElementById('updateBadge').innerHTML = '<i class="fas fa-pause"></i> Paused';
        }
    }

    // ================================================================
    // VISIBILITY CHANGE - PAUSE WHEN HIDDEN
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    // ================================================================
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<span class="spinner"></span> Loading...';
        btn.disabled = true;
        
        lastHash = null;
        fetchAndUpdateStats();
        
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
            showToast('✅ Refreshed', 'Dashboard data updated manually', 'success');
        }, 1500);
    }

    // ================================================================
    // DATE & TIME - LIVE CLOCK
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
        document.getElementById('liveTime').innerHTML = '<i class="fas fa-clock mr-1"></i> ' + timeStr;
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
            window.location.href = 'new_patient.php';
        }
        if (e.altKey && e.key === 'a') {
            e.preventDefault();
            window.location.href = 'new_appointment.php';
        }
        if (e.key === 'Escape' && document.activeElement === searchInput) {
            searchInput.value = '';
            searchInput.blur();
        }
    });

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        renderChart(chartLabels, chartValues);
        
        setTimeout(function() {
            startAutoUpdate();
        }, 2000);
    });

    // ================================================================
    // CONSOLE
    // ================================================================
    console.log('%c🏥 Braick Dispensary - Reception Dashboard (FIXED)', 'font-size:20px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👋 Welcome, <?= htmlspecialchars($user_full_name) ?>!', 'font-size:14px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 8 Cards: Today Patients, Today Visits, Today Appointments, Total Appointments, Total Patients, Total Visits, Pending Appointments, Online Doctors', 'font-size:12px; color:#0B5ED7;');
    console.log('%c✅ Today Patients: <?= $today_patients_total ?> (Pending: <?= $today_patients_pending ?>, Complete: <?= $today_patients_completed ?>)', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Today Visits: <?= $today_visits_total ?> (Pending: <?= $today_visits_pending ?>, Complete: <?= $today_visits_completed ?>)', 'font-size:12px; color:#34D399;');
    console.log('%c🔄 Auto-update: Every 3 seconds (Smart - only when data changes)', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Click Online Doctors card to see all online doctors', 'font-size:12px; color:#0B5ED7;');
</script>

</body>
</html>