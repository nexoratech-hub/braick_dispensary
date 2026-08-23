<?php
// ================================================================
// FILE: frontend/pages/reception/view_appointment.php
// RECEPTION - VIEW APPOINTMENT DETAILS
// USING dispensary_db (new database structure)
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
$user_id = $_SESSION['user_id'] ?? 0;
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

$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($appointment_id <= 0) {
    header('Location: appointments.php');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // ================================================================
    // ✅ GET APPOINTMENT DETAILS WITH PATIENT AND DOCTOR INFO
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            a.*,
            a.visit_type,
            a.visit_id,
            a.assigned_at,
            a.confirmed_at,
            a.completed_at,
            a.cancelled_at,
            a.created_by,
            p.id as patient_id,
            p.full_name as patient_name, 
            p.patient_id as patient_code, 
            p.phone, 
            p.email, 
            p.address,
            p.date_of_birth,
            p.gender,
            p.blood_group,
            p.allergies,
            p.created_at as patient_created_at,
            p.marital_status,
            p.emergency_contact,
            u.id as doctor_id,
            u.full_name as doctor_name, 
            u.specialty, 
            u.phone as doctor_phone,
            u.is_online as doctor_online,
            b.name as branch_name,
            creator.full_name as created_by_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.doctor_id = u.id
        LEFT JOIN branches b ON a.branch_id = b.id
        LEFT JOIN users creator ON a.created_by = creator.id
        WHERE a.id = ? AND a.branch_id = ?
    ");
    $stmt->execute([$appointment_id, $branch_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        header('Location: appointments.php?error=not_found');
        exit;
    }
    
    // ================================================================
    // ✅ GET LATEST VITAL SIGNS FOR PATIENT
    // ================================================================
    $vital_signs = null;
    $stmt = $db->prepare("
        SELECT * FROM vital_signs 
        WHERE patient_id = ? 
        ORDER BY recorded_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$appointment['patient_id']]);
    $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ================================================================
    // ✅ GET VISIT COUNT FOR PATIENT
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_visits 
        FROM visits 
        WHERE patient_id = ?
    ");
    $stmt->execute([$appointment['patient_id']]);
    $visit_count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ================================================================
    // ✅ GET PATIENT DAYS
    // ================================================================
    $stmt = $db->prepare("
        SELECT DATEDIFF(NOW(), created_at) as patient_days 
        FROM patients 
        WHERE id = ?
    ");
    $stmt->execute([$appointment['patient_id']]);
    $patient_days_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_days = $patient_days_data['patient_days'] ?? 0;
    
    // ================================================================
    // ✅ GET APPOINTMENT HISTORY FOR THIS PATIENT
    // ================================================================
    $stmt = $db->prepare("
        SELECT id, appointment_date, status, purpose, visit_type
        FROM appointments 
        WHERE patient_id = ? AND id != ?
        ORDER BY appointment_date DESC 
        LIMIT 5
    ");
    $stmt->execute([$appointment['patient_id'], $appointment_id]);
    $appointment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // ✅ GET BILLS FOR THIS VISIT (if visit_id exists)
    // ================================================================
    $bills = [];
    if (!empty($appointment['visit_id'])) {
        $stmt = $db->prepare("
            SELECT id, bill_number, total_amount, paid_amount, balance, status, created_at
            FROM bills 
            WHERE visit_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$appointment['visit_id']]);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    header('Location: appointments.php?error=db_error');
    exit;
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $unread_notifications = $result['total'] ?? 0;
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
// HELPER FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'scheduled' => 'scheduled',
        'confirmed' => 'confirmed',
        'in-progress' => 'in-progress',
        'completed' => 'completed',
        'cancelled' => 'cancelled'
    ];
    return $map[$status] ?? 'scheduled';
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('F d, Y h:i A', strtotime($datetime));
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
    <title>Appointment Details - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
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
        
        /* ================================================================
           DETAIL CARD - BLUE THEME
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 2px solid var(--primary-light);
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.08);
        }
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.15);
        }
        [data-theme="dark"] .detail-card {
            border-color: var(--primary);
        }
        [data-theme="dark"] .detail-card:hover {
            border-color: var(--primary-light);
        }
        
        .detail-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .detail-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* ================================================================
           STATUS BADGE
           ================================================================ */
        .status-badge-display {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 16px;
            border-radius: 20px;
        }
        .status-badge-display.scheduled { background: #E8F0FE; color: #0B5ED7; }
        .status-badge-display.confirmed { background: #D1FAE5; color: #059669; }
        .status-badge-display.in-progress { background: #FEF3C7; color: #D97706; }
        .status-badge-display.completed { background: #D1FAE5; color: #059669; }
        .status-badge-display.cancelled { background: #FEE2E2; color: #DC2626; }
        
        [data-theme="dark"] .status-badge-display.scheduled { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge-display.confirmed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge-display.in-progress { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge-display.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge-display.cancelled { background: #3A1A1A; color: #F87171; }
        
        /* ================================================================
           DAYS BADGE - BLUE BACKGROUND
           ================================================================ */
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
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.35);
        }
        .btn-success {
            background: var(--success);
            color: white;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(5, 150, 105, 0.35);
        }
        .btn-danger {
            background: var(--danger);
            color: white;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(220, 38, 38, 0.35);
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
        .btn-sm {
            padding: 6px 14px;
            font-size: 0.75rem;
            border-radius: 8px;
        }
        .btn-purple {
            background: var(--purple);
            color: white;
        }
        .btn-purple:hover {
            background: var(--purple-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
        }
        
        /* ================================================================
           QUICK ACTION CARDS
           ================================================================ */
        .quick-action-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            box-shadow: var(--shadow-sm);
        }
        .quick-action-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        .quick-action-card .icon {
            font-size: 2rem;
            display: block;
            margin-bottom: 8px;
        }
        .quick-action-card .label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        /* ================================================================
           APPOINTMENT HISTORY
           ================================================================ */
        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }
        .history-item:hover {
            background: var(--primary-bg);
            border-radius: 6px;
        }
        .history-item:last-child {
            border-bottom: none;
        }
        .history-item .history-date {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        .history-item .history-status {
            font-size: 0.65rem;
            font-weight: 500;
            padding: 2px 10px;
            border-radius: 12px;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
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
            .detail-card { padding: 16px; }
            .grid-cols-1 { grid-template-columns: 1fr !important; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .page-header .header-badge { font-size: 0.6rem; padding: 2px 10px; }
            .page-header .page-subtitle { font-size: 0.8rem; }
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
            <input type="text" id="searchInput" placeholder="Search...">
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
                <i class="fas fa-calendar-check"></i>
                Appointment Details
                <span class="role-badge-display">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                ID: <strong>#<?= $appointment['id'] ?></strong>
                <span class="ml-2">|</span>
                <span class="ml-2">📅 <?= date('F d, Y', strtotime($appointment['appointment_date'])) ?></span>
                <span class="ml-2">|</span>
                <span class="ml-2">🕐 <?= date('h:i A', strtotime($appointment['appointment_date'])) ?></span>
                <span class="ml-2">|</span>
                <span class="header-badge">
                    <span class="status-badge-display <?= getStatusBadgeClass($appointment['status']) ?>">
                        <?= ucfirst($appointment['status']) ?>
                    </span>
                </span>
                <span class="ml-2 header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($appointment['patient_name']) ?>
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <?php if ($appointment['status'] !== 'confirmed' && $appointment['status'] !== 'completed' && $appointment['status'] !== 'cancelled'): ?>
                <a href="appointment_status.php?id=<?= $appointment['id'] ?>&status=confirmed&redirect=view_appointment.php?id=<?= $appointment['id'] ?>" class="btn btn-success btn-sm no-print">
                    <i class="fas fa-check"></i> Confirm
                </a>
            <?php endif; ?>
            <?php if ($appointment['status'] !== 'cancelled' && $appointment['status'] !== 'completed'): ?>
                <a href="appointment_status.php?id=<?= $appointment['id'] ?>&status=cancelled&redirect=view_appointment.php?id=<?= $appointment['id'] ?>" class="btn btn-danger btn-sm no-print" onclick="return confirm('Are you sure you want to cancel this appointment?')">
                    <i class="fas fa-times"></i> Cancel
                </a>
            <?php endif; ?>
            <a href="appointments.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- APPOINTMENT DETAILS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        <!-- Appointment Info -->
        <div class="detail-card lg:col-span-2 animate-fade-in-up" style="animation-delay:0.05s;">
            <h3 class="text-lg font-semibold mb-4" style="color:var(--text-primary);">
                <i class="fas fa-info-circle" style="color:var(--primary);"></i> Appointment Information
            </h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="detail-label">Appointment ID</p>
                    <p class="detail-value">#<?= $appointment['id'] ?></p>
                </div>
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <span class="status-badge-display <?= getStatusBadgeClass($appointment['status']) ?>">
                            <?= ucfirst($appointment['status']) ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Date</p>
                    <p class="detail-value"><?= date('F d, Y', strtotime($appointment['appointment_date'])) ?></p>
                </div>
                <div>
                    <p class="detail-label">Time</p>
                    <p class="detail-value"><?= date('h:i A', strtotime($appointment['appointment_date'])) ?></p>
                </div>
                <div>
                    <p class="detail-label">Purpose</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['purpose'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Visit Type</p>
                    <p class="detail-value"><?= ucfirst($appointment['visit_type'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Branch</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['branch_name'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Created By</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['created_by_name'] ?? 'N/A') ?></p>
                </div>
                <div class="col-span-2">
                    <p class="detail-label">Created At</p>
                    <p class="detail-value"><?= date('F d, Y h:i A', strtotime($appointment['created_at'])) ?></p>
                </div>
                <?php if (!empty($appointment['confirmed_at'])): ?>
                <div class="col-span-2">
                    <p class="detail-label">Confirmed At</p>
                    <p class="detail-value"><?= date('F d, Y h:i A', strtotime($appointment['confirmed_at'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($appointment['completed_at'])): ?>
                <div class="col-span-2">
                    <p class="detail-label">Completed At</p>
                    <p class="detail-value"><?= date('F d, Y h:i A', strtotime($appointment['completed_at'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($appointment['cancelled_at'])): ?>
                <div class="col-span-2">
                    <p class="detail-label">Cancelled At</p>
                    <p class="detail-value"><?= date('F d, Y h:i A', strtotime($appointment['cancelled_at'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Patient Info -->
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.1s;">
            <h3 class="text-lg font-semibold mb-4" style="color:var(--text-primary);">
                <i class="fas fa-user" style="color:var(--primary);"></i> Patient
                <span class="days-badge-blue <?= $patient_days == 0 ? 'new' : '' ?>">
                    📅 <?= $patient_days > 0 ? $patient_days . ' days' : 'New' ?>
                </span>
            </h3>
            <div class="space-y-3">
                <div>
                    <p class="detail-label">Name</p>
                    <p class="detail-value">
                        <a href="view_patient.php?id=<?= $appointment['patient_id'] ?>" class="text-primary hover:underline">
                            <?= htmlspecialchars($appointment['patient_name']) ?>
                        </a>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Patient ID</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['patient_code'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Phone</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['phone'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Email</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['email'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Gender</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['gender'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Date of Birth</p>
                    <p class="detail-value"><?= !empty($appointment['date_of_birth']) ? date('F d, Y', strtotime($appointment['date_of_birth'])) : 'N/A' ?></p>
                </div>
                <div>
                    <p class="detail-label">Marital Status</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['marital_status'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Blood Group</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['blood_group'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Allergies</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['allergies'] ?? 'None') ?></p>
                </div>
                <div>
                    <p class="detail-label">Address</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['address'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Total Visits</p>
                    <p class="detail-value"><?= $visit_count['total_visits'] ?? 0 ?></p>
                </div>
            </div>
        </div>
        
        <!-- Doctor Info -->
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.15s;">
            <h3 class="text-lg font-semibold mb-4" style="color:var(--text-primary);">
                <i class="fas fa-user-md" style="color:var(--primary);"></i> Doctor
                <?php if ($appointment['doctor_online'] == 1): ?>
                    <span class="text-xs text-green-500 font-normal">🟢 Online</span>
                <?php else: ?>
                    <span class="text-xs text-gray-400 font-normal">⚪ Offline</span>
                <?php endif; ?>
            </h3>
            <div class="space-y-3">
                <div>
                    <p class="detail-label">Name</p>
                    <p class="detail-value">Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Specialty</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['specialty'] ?? 'General Practitioner') ?></p>
                </div>
                <div>
                    <p class="detail-label">Phone</p>
                    <p class="detail-value"><?= htmlspecialchars($appointment['doctor_phone'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <?php if ($appointment['doctor_online'] == 1): ?>
                            <span class="text-green-500 font-semibold">🟢 Online</span>
                        <?php else: ?>
                            <span class="text-gray-400 font-semibold">⚪ Offline</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- BILLS INFORMATION (if visit exists) -->
    <!-- ================================================================ -->
    <?php if (!empty($bills)): ?>
    <div class="detail-card mt-5 animate-fade-in-up" style="animation-delay:0.2s;">
        <h3 class="text-lg font-semibold mb-4" style="color:var(--text-primary);">
            <i class="fas fa-file-invoice" style="color:var(--warning);"></i> Bills
            <span class="text-xs text-gray-400 font-normal ml-2">(<?= count($bills) ?> bill(s))</span>
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="text-left py-2 font-semibold text-gray-600">Bill #</th>
                        <th class="text-left py-2 font-semibold text-gray-600">Amount</th>
                        <th class="text-left py-2 font-semibold text-gray-600">Paid</th>
                        <th class="text-left py-2 font-semibold text-gray-600">Balance</th>
                        <th class="text-left py-2 font-semibold text-gray-600">Status</th>
                        <th class="text-left py-2 font-semibold text-gray-600">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bills as $bill): ?>
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="py-2 font-medium"><?= htmlspecialchars($bill['bill_number']) ?></td>
                        <td class="py-2">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                        <td class="py-2 text-green-600">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                        <td class="py-2 <?= ($bill['balance'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' ?>">
                            TSh <?= number_format($bill['balance'] ?? 0, 0) ?>
                        </td>
                        <td class="py-2">
                            <span class="status-badge-display <?= $bill['status'] === 'paid' ? 'confirmed' : ($bill['status'] === 'pending' ? 'scheduled' : 'cancelled') ?>">
                                <?= ucfirst($bill['status']) ?>
                            </span>
                        </td>
                        <td class="py-2 text-xs text-gray-500"><?= date('d M Y', strtotime($bill['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- VITAL SIGNS (If available) -->
    <!-- ================================================================ -->
    <?php if ($vital_signs): ?>
    <div class="detail-card mt-5 animate-fade-in-up" style="animation-delay:0.25s;">
        <h3 class="text-lg font-semibold mb-4" style="color:var(--text-primary);">
            <i class="fas fa-heartbeat text-red-500 mr-2"></i> Latest Vital Signs
            <span class="text-xs text-gray-400 font-normal ml-2">
                Recorded: <?= date('F d, Y h:i A', strtotime($vital_signs['recorded_at'])) ?>
            </span>
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
            <?php if ($vital_signs['temperature']): ?>
            <div class="text-center p-3 bg-blue-50 rounded-lg border border-blue-200 dark:bg-blue-900/20 dark:border-blue-800">
                <p class="text-xs text-gray-500">🌡️ Temperature</p>
                <p class="text-lg font-bold text-blue-600"><?= $vital_signs['temperature'] ?>°C</p>
            </div>
            <?php endif; ?>
            <?php if ($vital_signs['blood_pressure_systolic'] || $vital_signs['blood_pressure_diastolic']): ?>
            <div class="text-center p-3 bg-green-50 rounded-lg border border-green-200 dark:bg-green-900/20 dark:border-green-800">
                <p class="text-xs text-gray-500">❤️ Blood Pressure</p>
                <p class="text-lg font-bold text-green-600">
                    <?php 
                        $sys = $vital_signs['blood_pressure_systolic'] ?? '';
                        $dia = $vital_signs['blood_pressure_diastolic'] ?? '';
                        if ($sys && $dia) {
                            echo $sys . '/' . $dia;
                        } elseif ($sys) {
                            echo $sys;
                        } else {
                            echo 'N/A';
                        }
                    ?>
                    mmHg
                </p>
            </div>
            <?php endif; ?>
            <?php if ($vital_signs['pulse_rate']): ?>
            <div class="text-center p-3 bg-purple-50 rounded-lg border border-purple-200 dark:bg-purple-900/20 dark:border-purple-800">
                <p class="text-xs text-gray-500">💓 Pulse Rate</p>
                <p class="text-lg font-bold text-purple-600"><?= $vital_signs['pulse_rate'] ?> bpm</p>
            </div>
            <?php endif; ?>
            <?php if ($vital_signs['weight']): ?>
            <div class="text-center p-3 bg-orange-50 rounded-lg border border-orange-200 dark:bg-orange-900/20 dark:border-orange-800">
                <p class="text-xs text-gray-500">⚖️ Weight</p>
                <p class="text-lg font-bold text-orange-600"><?= $vital_signs['weight'] ?> kg</p>
            </div>
            <?php endif; ?>
            <?php if ($vital_signs['height']): ?>
            <div class="text-center p-3 bg-teal-50 rounded-lg border border-teal-200 dark:bg-teal-900/20 dark:border-teal-800">
                <p class="text-xs text-gray-500">📏 Height</p>
                <p class="text-lg font-bold text-teal-600"><?= $vital_signs['height'] ?> cm</p>
            </div>
            <?php endif; ?>
            <?php if ($vital_signs['bmi']): ?>
            <div class="text-center p-3 bg-red-50 rounded-lg border border-red-200 dark:bg-red-900/20 dark:border-red-800">
                <p class="text-xs text-gray-500">📊 BMI</p>
                <p class="text-lg font-bold text-red-600"><?= $vital_signs['bmi'] ?> kg/m²</p>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($vital_signs['notes'])): ?>
            <div class="mt-3 text-sm text-gray-500">
                <i class="fas fa-sticky-note mr-1"></i> Notes: <?= htmlspecialchars($vital_signs['notes']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- APPOINTMENT HISTORY -->
    <!-- ================================================================ -->
    <?php if (!empty($appointment_history)): ?>
    <div class="detail-card mt-5 animate-fade-in-up" style="animation-delay:0.3s;">
        <h3 class="text-lg font-semibold mb-4" style="color:var(--text-primary);">
            <i class="fas fa-history text-purple-500 mr-2"></i> Appointment History
            <span class="text-xs text-gray-400 font-normal ml-2">(Last 5 appointments)</span>
        </h3>
        <div>
            <?php foreach ($appointment_history as $history): ?>
                <div class="history-item">
                    <div>
                        <span class="history-date"><?= date('F d, Y h:i A', strtotime($history['appointment_date'])) ?></span>
                        <?php if (!empty($history['purpose'])): ?>
                            <span class="text-xs text-gray-400 ml-2">- <?= htmlspecialchars($history['purpose']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($history['visit_type'])): ?>
                            <span class="text-xs text-gray-400 ml-1">(<?= ucfirst($history['visit_type']) ?>)</span>
                        <?php endif; ?>
                    </div>
                    <span class="history-status status-badge-display <?= getStatusBadgeClass($history['status']) ?>">
                        <?= ucfirst($history['status']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-5">
        <a href="view_patient.php?id=<?= $appointment['patient_id'] ?>" class="quick-action-card animate-fade-in-up" style="animation-delay:0.35s;">
            <span class="icon"><i class="fas fa-user" style="color:var(--primary);"></i></span>
            <span class="label">View Patient Profile</span>
        </a>
        <a href="new_appointment.php?patient_id=<?= $appointment['patient_id'] ?>" class="quick-action-card animate-fade-in-up" style="animation-delay:0.4s;">
            <span class="icon"><i class="fas fa-calendar-plus" style="color:var(--success);"></i></span>
            <span class="label">New Appointment</span>
        </a>
        <a href="assign_doctor.php?patient_id=<?= $appointment['patient_id'] ?>" class="quick-action-card animate-fade-in-up" style="animation-delay:0.45s;">
            <span class="icon"><i class="fas fa-user-md" style="color:var(--purple);"></i></span>
            <span class="label">Assign Doctor</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-modern">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Appointment Details
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
    // CLOCK - UPDATE EVERY SECOND
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
    // CHECK FOR STATUS CHANGE MESSAGES
    // ================================================================
    <?php if (isset($_GET['status_changed']) && $_GET['status_changed'] === 'confirmed'): ?>
        showToast('✅ Confirmed', 'Appointment confirmed successfully!', 'success');
    <?php endif; ?>
    
    <?php if (isset($_GET['status_changed']) && $_GET['status_changed'] === 'cancelled'): ?>
        showToast('❌ Cancelled', 'Appointment has been cancelled.', 'warning');
    <?php endif; ?>

    console.log('%c📅 Braick - View Appointment (NEW DATABASE)', 'font-size:18px; font-weight:bold; color:#2563EB;');
    console.log('%c📋 Appointment ID: <?= $appointment['id'] ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($appointment['patient_name']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📅 Patient Days: <?= $patient_days ?> days', 'font-size:13px; color:#2563EB;');
    console.log('%c👨‍⚕️ Doctor: <?= htmlspecialchars($appointment['doctor_name']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Total Visits: <?= $visit_count['total_visits'] ?? 0 ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🎨 Blue theme applied to all cards', 'font-size:13px; color:#2563EB;');
    console.log('%c📋 Appointment History shown', 'font-size:13px; color:#7C3AED;');
    console.log('%c💾 Using NEW DATABASE: dispensary_db', 'font-size:13px; color:#34D399;');
    console.log('%c🔒 Login protection: Active', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>