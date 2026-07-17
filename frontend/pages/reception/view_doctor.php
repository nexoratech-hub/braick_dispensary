<?php
// ================================================================
// FILE: frontend/pages/reception/view_doctor.php
// RECEPTION - VIEW DOCTOR DETAILS (BRANCH FILTERED)
// WITH AUTO-UPDATE (3 SECONDS) - NO REFRESH NEEDED
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// GET USER COLOR - Helper function
// ================================================================
function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777', '#4F46E5', '#E11D48'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}

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

$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

if ($doctor_id <= 0) {
    header('Location: online_doctors.php');
    exit;
}

try {
    $db = getDB();
    
    // ================================================================
    // GET DOCTOR DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT u.*, b.name as branch_name 
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.id = ? AND u.role = 'doctor' AND u.branch_id = ?
    ");
    $stmt->execute([$doctor_id, $user_branch_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor) {
        header('Location: online_doctors.php');
        exit;
    }
    
    // ================================================================
    // GET DOCTOR STATISTICS
    // ================================================================
    
    // 1. Total Patients (distinct)
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 2. Today's Visits
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$doctor_id, $today]);
    $today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 3. Pending Visits
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status IN ('pending', 'assigned')");
    $stmt->execute([$doctor_id]);
    $pending_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 4. Completed Visits
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status = 'completed'");
    $stmt->execute([$doctor_id]);
    $completed_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 5. Total Appointments
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 6. Today's Appointments
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ?");
    $stmt->execute([$doctor_id, $today]);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 7. Total Prescriptions
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 8. Today's Appointments List
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name, p.patient_id, p.phone 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = ? AND DATE(a.appointment_date) = ?
        ORDER BY a.appointment_date
        LIMIT 10
    ");
    $stmt->execute([$doctor_id, $today]);
    $today_appointments_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 9. Recent Patients
    $stmt = $db->prepare("
        SELECT DISTINCT p.*, v.created_at as last_visit 
        FROM patients p
        JOIN visits v ON p.id = v.patient_id
        WHERE v.doctor_id = ?
        ORDER BY v.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$doctor_id]);
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 10. Recent Visits
    $stmt = $db->prepare("
        SELECT v.*, p.full_name as patient_name, p.patient_id
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE v.doctor_id = ?
        ORDER BY v.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$doctor_id]);
    $recent_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $doctor = null;
    $total_patients = 0;
    $today_visits = 0;
    $pending_visits = 0;
    $completed_visits = 0;
    $total_appointments = 0;
    $today_appointments = 0;
    $total_prescriptions = 0;
    $today_appointments_list = [];
    $recent_patients = [];
    $recent_visits = [];
    $message = "Database error: " . $e->getMessage();
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
    <title>View Doctor - Braick Dispensary</title>
    
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
           DOCTOR PROFILE
           ================================================================ */
        .doctor-profile {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        .doctor-profile:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .doctor-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(0,0,0,0.15);
        }
        
        .doctor-name-large {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .doctor-specialty-badge {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 14px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        [data-theme="dark"] .doctor-specialty-badge {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .doctor-status-badge {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 3px 14px;
            border-radius: 20px;
        }
        
        .doctor-status-badge.online {
            background: #D1FAE5;
            color: #059669;
        }
        
        .doctor-status-badge.offline {
            background: #F1F5F9;
            color: #94A3B8;
        }
        
        [data-theme="dark"] .doctor-status-badge.online {
            background: #1A3A2A;
            color: #34D399;
        }
        
        [data-theme="dark"] .doctor-status-badge.offline {
            background: #1E293B;
            color: #64748B;
        }
        
        .doctor-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }
        
        .doctor-meta-grid .meta-item {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--bg-body);
            padding: 4px 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .doctor-meta-grid .meta-item i {
            color: var(--primary);
            font-size: 0.8rem;
        }
        
        /* ================================================================
           STATS BOX
           ================================================================ */
        .stats-grid-doctor {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 14px 16px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-box:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-box .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-box .stat-number.green { color: #059669; }
        .stat-box .stat-number.orange { color: #D97706; }
        .stat-box .stat-number.purple { color: #7C3AED; }
        .stat-box .stat-number.red { color: #DC2626; }
        
        .stat-box .stat-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-box .stat-update {
            font-size: 0.5rem;
            color: var(--text-secondary);
            opacity: 0.5;
            margin-top: 4px;
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
        
        .appointment-patient .id {
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
           PATIENT AVATAR
           ================================================================ */
        .patient-avatar-sm {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.6rem;
            flex-shrink: 0;
        }
        
        /* ================================================================
           VISIT ITEM
           ================================================================ */
        .visit-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .visit-item:hover {
            background: var(--bg-body);
        }
        
        .visit-item:last-child {
            border-bottom: none;
        }
        
        .visit-item .visit-date {
            font-size: 0.7rem;
            color: var(--text-secondary);
            min-width: 80px;
        }
        
        .visit-item .visit-patient .name {
            font-weight: 500;
            font-size: 0.8rem;
            color: var(--text-primary);
        }
        
        .visit-item .visit-status {
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 12px;
        }
        
        .visit-item .visit-status.pending { background: #FEF3C7; color: #D97706; }
        .visit-item .visit-status.assigned { background: #E8F0FE; color: #0B5ED7; }
        .visit-item .visit-status.completed { background: #D1FAE5; color: #059669; }
        .visit-item .visit-status.cancelled { background: #FEE2E2; color: #DC2626; }
        
        [data-theme="dark"] .visit-item .visit-status.pending { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .visit-item .visit-status.assigned { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .visit-item .visit-status.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .visit-item .visit-status.cancelled { background: #3A1A1A; color: #F87171; }
        
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
           SCROLL CONTAINER
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
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .doctor-profile { padding: 18px 20px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .doctor-name-large { font-size: 1.1rem; }
            .doctor-avatar-large { width: 60px; height: 60px; font-size: 1.5rem; }
            .doctor-meta-grid { grid-template-columns: 1fr 1fr; }
            .stats-grid-doctor { grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .doctor-profile { padding: 12px 14px; }
            .doctor-meta-grid { grid-template-columns: 1fr; }
            .stats-grid-doctor { grid-template-columns: 1fr 1fr; }
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
        
        /* Status update flash */
        .status-updated {
            animation: flashUpdate 0.6s ease;
        }
        
        @keyframes flashUpdate {
            0% { background-color: rgba(11, 94, 215, 0.05); }
            30% { background-color: rgba(11, 94, 215, 0.15); }
            70% { background-color: rgba(11, 94, 215, 0.08); }
            100% { background-color: transparent; }
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
            <input type="text" id="searchInput" placeholder="Search patients...">
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
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
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

    <?php if ($doctor): ?>
    
    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md"></i>
                Doctor Details
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">RECEPTION</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                View doctor information and statistics
                
                <span class="header-badge" id="onlineStatusBadge">
                    <span id="onlineStatusDisplay" class="<?= ($doctor['is_online'] ?? 0) ? 'online' : 'offline' ?>">
                        <?= ($doctor['is_online'] ?? 0) ? '🟢 Online' : '⚪ Offline' ?>
                    </span>
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i>
                    ID: <?= $doctor['id'] ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="online_doctors.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Doctors
            </a>
            <a href="assign_doctor.php?doctor_id=<?= $doctor['id'] ?>" class="btn-outline-light">
                <i class="fas fa-user-md"></i> Assign Patient
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DOCTOR PROFILE -->
    <!-- ================================================================ -->
    <div class="doctor-profile animate-fade-in-up" style="max-width:1000px;margin:0 auto 20px;">
        <div class="flex items-center gap-6 flex-wrap">
            <div class="doctor-avatar-large" style="background: <?= getUserColor($doctor['full_name']) ?>;">
                <?= strtoupper(substr($doctor['full_name'], 0, 2)) ?>
            </div>
            
            <div class="flex-1">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="doctor-name-large">Dr. <?= htmlspecialchars($doctor['full_name']) ?></span>
                    <span class="doctor-specialty-badge">
                        <i class="fas fa-stethoscope mr-1"></i>
                        <?= htmlspecialchars($doctor['specialty'] ?? 'General Practitioner') ?>
                    </span>
                    <span class="doctor-status-badge <?= ($doctor['is_online'] ?? 0) ? 'online' : 'offline' ?>" id="doctorStatusBadge">
                        <?= ($doctor['is_online'] ?? 0) ? '🟢 Online' : '⚪ Offline' ?>
                    </span>
                </div>
                
                <div class="doctor-meta-grid">
                    <span class="meta-item">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor['branch_name'] ?? 'Not Assigned') ?>
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($doctor['email'] ?? 'N/A') ?>
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-phone"></i> <?= htmlspecialchars($doctor['phone'] ?? 'N/A') ?>
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-calendar-alt"></i> Joined: <?= isset($doctor['created_at']) ? date('M d, Y', strtotime($doctor['created_at'])) : 'N/A' ?>
                    </span>
                    <?php if (!empty($doctor['last_online'])): ?>
                        <span class="meta-item">
                            <i class="fas fa-clock"></i> Last seen: <?= date('M d, Y h:i A', strtotime($doctor['last_online'])) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS - AUTO UPDATED -->
    <!-- ================================================================ -->
    <div class="stats-grid-doctor" style="max-width:1000px;margin:0 auto 20px;" id="statsGrid">
        <div class="stat-box">
            <p class="stat-number" id="totalPatients"><?= number_format($total_patients) ?></p>
            <p class="stat-label">Total Patients</p>
            <p class="stat-update" id="totalPatientsUpdate">Updated now</p>
        </div>
        <div class="stat-box">
            <p class="stat-number green" id="todayVisits"><?= number_format($today_visits) ?></p>
            <p class="stat-label">Today's Visits</p>
            <p class="stat-update" id="todayVisitsUpdate">Updated now</p>
        </div>
        <div class="stat-box">
            <p class="stat-number orange" id="pendingVisits"><?= number_format($pending_visits) ?></p>
            <p class="stat-label">Pending Visits</p>
            <p class="stat-update" id="pendingVisitsUpdate">Updated now</p>
        </div>
        <div class="stat-box">
            <p class="stat-number green" id="completedVisits"><?= number_format($completed_visits) ?></p>
            <p class="stat-label">Completed Visits</p>
            <p class="stat-update" id="completedVisitsUpdate">Updated now</p>
        </div>
        <div class="stat-box">
            <p class="stat-number purple" id="totalAppointments"><?= number_format($total_appointments) ?></p>
            <p class="stat-label">Total Appointments</p>
            <p class="stat-update" id="totalAppointmentsUpdate">Updated now</p>
        </div>
        <div class="stat-box">
            <p class="stat-number" id="todayAppointments"><?= number_format($today_appointments) ?></p>
            <p class="stat-label">Today's Appointments</p>
            <p class="stat-update" id="todayAppointmentsUpdate">Updated now</p>
        </div>
        <div class="stat-box">
            <p class="stat-number" style="color:#7C3AED;" id="totalPrescriptions"><?= number_format($total_prescriptions) ?></p>
            <p class="stat-label">Prescriptions</p>
            <p class="stat-update" id="totalPrescriptionsUpdate">Updated now</p>
        </div>
        <div class="stat-box">
            <p class="stat-number" style="color:#D97706;" id="workload"><?= number_format($pending_visits + $today_visits) ?></p>
            <p class="stat-label">Total Workload</p>
            <p class="stat-update" id="workloadUpdate">Updated now</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TODAY'S APPOINTMENTS & RECENT PATIENTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5" style="max-width:1000px;margin:0 auto;">
        
        <!-- Today's Appointments -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-check title-blue mr-2"></i> Today's Appointments
                    <span class="text-sm font-normal text-gray-400" id="appointmentsCount">(<?= count($today_appointments_list) ?>)</span>
                </h3>
            </div>
            <div class="scroll-container" id="todayAppointmentsList">
                <?php if (count($today_appointments_list) > 0): ?>
                    <?php foreach ($today_appointments_list as $appt): ?>
                        <div class="appointment-item">
                            <span class="appointment-time"><?= date('h:i A', strtotime($appt['appointment_date'])) ?></span>
                            <div class="appointment-patient flex-1 ml-3">
                                <span class="name"><?= htmlspecialchars($appt['patient_name']) ?></span>
                                <span class="id"><?= htmlspecialchars($appt['patient_id']) ?></span>
                            </div>
                            <span class="appointment-status <?= $appt['status'] ?>">
                                <?= ucfirst($appt['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-calendar-check text-xl block mb-2"></i>
                        <p class="text-sm">No appointments today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Patients -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-injured title-green mr-2"></i> Recent Patients
                    <span class="text-sm font-normal text-gray-400">(<?= count($recent_patients) ?>)</span>
                </h3>
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
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-400"><?= isset($patient['last_visit']) ? time_ago($patient['last_visit']) : 'N/A' ?></p>
                                <a href="view_patient.php?id=<?= $patient['id'] ?>" class="text-primary text-xs hover:underline">View</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-users text-xl block mb-2"></i>
                        <p class="text-sm">No patients yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- RECENT VISITS -->
    <!-- ================================================================ -->
    <div class="card mt-5" style="max-width:1000px;margin:20px auto 0;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-clinic-medical title-orange mr-2"></i> Recent Visits
                <span class="text-sm font-normal text-gray-400">(<?= count($recent_visits) ?>)</span>
            </h3>
        </div>
        <div class="scroll-container" id="recentVisitsList" style="max-height:200px;">
            <?php if (count($recent_visits) > 0): ?>
                <?php foreach ($recent_visits as $visit): ?>
                    <div class="visit-item">
                        <span class="visit-date"><?= date('M d, Y h:i A', strtotime($visit['created_at'])) ?></span>
                        <div class="visit-patient flex-1 ml-3">
                            <span class="name"><?= htmlspecialchars($visit['patient_name']) ?></span>
                            <span class="id block text-xs text-gray-400"><?= htmlspecialchars($visit['patient_id']) ?></span>
                        </div>
                        <span class="visit-status <?= $visit['status'] ?>">
                            <?= ucfirst($visit['status']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-4 text-gray-400">
                    <i class="fas fa-clinic-medical text-xl block mb-2"></i>
                    <p class="text-sm">No visits recorded</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-user-md text-4xl block mb-3"></i>
            <p class="text-lg">Doctor not found</p>
            <a href="online_doctors.php" class="text-primary hover:underline">Back to doctors list</a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Doctor
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
    // TIME AGO
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
            showToast('✅ Refreshed', 'Page data updated manually', 'success');
        }, 2000);
    }

    // ================================================================
    // AUTO-UPDATE DOCTOR STATUS & STATS (3 SECONDS)
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var lastDoctorData = '';

    function updateDoctorData() {
        if (isUpdating) return;
        isUpdating = true;
        
        var doctorId = <?= json_encode($doctor_id) ?>;
        var branchId = <?= json_encode($user_branch_id) ?>;
        
        var url = '/dispensary_system/frontend/api/get_online_doctors.php?branch_id=' + branchId + '&t=' + new Date().getTime();
        
        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var doctors = data.doctors || [];
                    var onlineCount = data.online_count || 0;
                    var totalDoctors = data.total_doctors || 0;
                    
                    // Find this doctor in the list
                    var currentDoctor = null;
                    for (var i = 0; i < doctors.length; i++) {
                        if (doctors[i].id == doctorId) {
                            currentDoctor = doctors[i];
                            break;
                        }
                    }
                    
                    if (currentDoctor) {
                        var isOnline = currentDoctor.is_online == 1;
                        
                        // ================================================================
                        // UPDATE DOCTOR STATUS - INSTANT
                        // ================================================================
                        var statusBadge = document.getElementById('doctorStatusBadge');
                        var statusDisplay = document.getElementById('onlineStatusDisplay');
                        
                        if (statusBadge) {
                            statusBadge.className = 'doctor-status-badge ' + (isOnline ? 'online' : 'offline');
                            statusBadge.textContent = isOnline ? '🟢 Online' : '⚪ Offline';
                        }
                        
                        if (statusDisplay) {
                            statusDisplay.className = isOnline ? 'online' : 'offline';
                            statusDisplay.textContent = isOnline ? '🟢 Online' : '⚪ Offline';
                        }
                        
                        // ================================================================
                        // UPDATE UPDATE BADGE
                        // ================================================================
                        var now = new Date();
                        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        document.getElementById('updateBadge').innerHTML = '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + timeStr;
                        
                        // Show toast if status changed
                        if (window.lastStatus !== undefined && window.lastStatus !== isOnline) {
                            showToast('🔄 Doctor Status Updated', 'Doctor is now ' + (isOnline ? 'Online 🟢' : 'Offline ⚪'), 'info');
                        }
                        window.lastStatus = isOnline;
                    }
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Error updating doctor data:', error);
                isUpdating = false;
            });
    }

    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        updateDoctorData();
        updateInterval = setInterval(updateDoctorData, 3000);
    }

    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
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
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 2000);
    });

    console.log('%c👨‍⚕️ Braick - View Doctor (Auto-Update Every 3s)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👨‍⚕️ Doctor: <?= htmlspecialchars($doctor['full_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Total Patients: <?= number_format($total_patients) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📅 Today Appointments: <?= number_format($today_appointments) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🔄 Auto-update every 3 seconds - No refresh needed', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Doctor status updates instantly', 'font-size:13px; color:#059669;');
</script>

</body>
</html>