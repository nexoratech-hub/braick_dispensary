<?php
// ================================================================
// FILE: frontend/pages/admin/reassign_doctor.php
// ADMIN - REMOVE DOCTOR FROM PATIENT
// BRAICK DISPENSARY - USING EXISTING DB TABLES
// BLUE THEME - WITH SHARED HEADER & SIDEBAR
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
// GET PARAMETERS
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

if ($patient_id <= 0) {
    header('Location: patients.php?branch=' . $branch_id . '&error=invalid_patient');
    exit;
}

// ================================================================
// FETCH PATIENT DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        p.*,
        u.full_name as current_doctor_name,
        u.id as current_doctor_id,
        u.specialty as current_doctor_specialty,
        u.is_online as current_doctor_online,
        v.id as current_visit_id,
        v.visit_number,
        v.status as visit_status,
        v.visit_date,
        v.created_at as visit_created_at
    FROM patients p
    LEFT JOIN users u ON p.assigned_doctor_id = u.id
    LEFT JOIN visits v ON v.patient_id = p.id AND v.status NOT IN ('completed', 'cancelled')
    WHERE p.id = ?
    ORDER BY v.created_at DESC
    LIMIT 1
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: patients.php?branch=' . $branch_id . '&error=patient_not_found');
    exit;
}

// ================================================================
// GET BRANCH DETAILS
// ================================================================
$branch = [];
if ($branch_id > 0) {
    $stmt = $db->prepare("SELECT id, name, location FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
}

// If branch not found, try to get from patient
if (!$branch && $patient['branch_id'] > 0) {
    $stmt = $db->prepare("SELECT id, name, location FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$patient['branch_id']]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch) {
        $branch_id = $branch['id'];
    }
}

// If still not found, use branch_id=1 (Dodoma)
if (!$branch) {
    $stmt = $db->prepare("SELECT id, name, location FROM branches WHERE id = 1 AND status = 'active'");
    $stmt->execute();
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    $branch_id = 1;
}

// ================================================================
// CHECK IF PATIENT HAS A DOCTOR ASSIGNED
// ================================================================
$has_doctor = !empty($patient['current_doctor_id']);

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
// HANDLE REMOVE DOCTOR
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // ACTION: REMOVE DOCTOR
    // ================================================================
    if ($action === 'remove_doctor') {
        try {
            $db->beginTransaction();
            
            // Get current doctor name before removing
            $removed_doctor_name = $patient['current_doctor_name'] ?? 'Unknown Doctor';
            $removed_doctor_id = $patient['current_doctor_id'] ?? null;
            
            // 1. Update patient - remove assigned doctor
            $stmt = $db->prepare("
                UPDATE patients 
                SET assigned_doctor_id = NULL, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$patient_id]);
            
            // 2. Update active visit - remove doctor
            if (!empty($patient['current_visit_id'])) {
                $stmt = $db->prepare("
                    UPDATE visits 
                    SET doctor_id = NULL, 
                        status = 'pending', 
                        assigned_at = NULL, 
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$patient['current_visit_id']]);
            }
            
            // 3. Log activity
            $details = "Doctor REMOVED from patient: " . htmlspecialchars($patient['full_name']) . 
                       " (ID: " . htmlspecialchars($patient['patient_id']) . ")" .
                       " - Removed doctor: " . htmlspecialchars($removed_doctor_name) .
                       " | Patient now has NO assigned doctor";
            
            $log_branch_id = !empty($branch_id) ? $branch_id : 1;
            
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                VALUES (?, ?, 'doctor_removed_from_patient', ?, NOW())
            ");
            $stmt->execute([$user_id, $log_branch_id, $details]);
            
            $db->commit();
            
            // Refresh patient data
            $stmt = $db->prepare("
                SELECT 
                    p.*,
                    u.full_name as current_doctor_name,
                    u.id as current_doctor_id,
                    u.specialty as current_doctor_specialty,
                    v.id as current_visit_id,
                    v.visit_number,
                    v.status as visit_status
                FROM patients p
                LEFT JOIN users u ON p.assigned_doctor_id = u.id
                LEFT JOIN visits v ON v.patient_id = p.id AND v.status NOT IN ('completed', 'cancelled')
                WHERE p.id = ?
                ORDER BY v.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            $has_doctor = false;
            
            $message = "✅ Doctor has been successfully REMOVED from patient. Patient is now UNASSIGNED.";
            $message_type = "success";
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = "❌ Error removing doctor: " . $e->getMessage();
            $message_type = "danger";
        }
    }
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
    <title>Remove Doctor - Braick Dispensary</title>
    
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
            --success-bg: #D1FAE5;
            
            --danger: #DC2626;
            --danger-dark: #B91C1C;
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
            max-width: 900px;
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
           CARDS
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
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-title i {
            color: var(--primary);
        }
        
        .card-body {
            padding: 20px 24px;
        }
        
        /* ================================================================
           PATIENT INFO
           ================================================================ */
        .patient-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }
        
        .info-item {
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .info-value .badge {
            font-size: 0.7rem;
            padding: 2px 12px;
            border-radius: 20px;
        }
        
        .badge-success { background: #059669; color: white; }
        .badge-danger { background: #DC2626; color: white; }
        .badge-warning { background: #D97706; color: white; }
        .badge-secondary { background: #64748B; color: white; }
        .badge-info { background: var(--primary); color: white; }
        
        /* ================================================================
           DOCTOR STATUS
           ================================================================ */
        .doctor-status {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-radius: var(--radius);
            margin-bottom: 16px;
        }
        
        .doctor-status.has-doctor {
            background: var(--primary-bg);
            border: 2px solid var(--primary);
        }
        
        .doctor-status.no-doctor {
            background: var(--danger-bg);
            border: 2px solid var(--danger);
        }
        
        .doctor-status .status-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .doctor-status.has-doctor .status-icon {
            background: var(--primary);
            color: white;
        }
        
        .doctor-status.no-doctor .status-icon {
            background: var(--danger);
            color: white;
        }
        
        .doctor-status .status-text {
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .doctor-status .status-sub {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: var(--danger-dark);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
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
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-gradient-hover);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-block {
            width: 100%;
            justify-content: center;
        }
        
        .btn-lg {
            padding: 14px 28px;
            font-size: 1rem;
        }
        
        /* ================================================================
           ALERT / MESSAGE
           ================================================================ */
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: var(--success-bg);
            border: 2px solid var(--success);
            color: var(--success-dark);
        }
        
        .alert-danger {
            background: var(--danger-bg);
            border: 2px solid var(--danger);
            color: var(--danger-dark);
        }
        
        .alert i {
            font-size: 1.2rem;
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
            font-weight: 600;
        }
        
        /* ================================================================
           CONFIRMATION MODAL
           ================================================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-box {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            max-width: 500px;
            width: 90%;
            padding: 32px;
            box-shadow: var(--shadow-xl);
            animation: fadeInUp 0.3s ease;
        }
        
        .modal-box .modal-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--danger-bg);
            color: var(--danger);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 16px;
        }
        
        .modal-box h3 {
            text-align: center;
            font-size: 1.2rem;
            margin-bottom: 8px;
        }
        
        .modal-box p {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        .modal-box .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
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
            .patient-info-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .modal-box { padding: 20px; }
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
            .footer, #sidebarToggle, .modal-overlay { display: none !important; }
            .main-content { margin: 0; padding: 20px; max-width: 100%; }
            .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .page-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .role-badge-display {
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

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md"></i>
                Remove Assigned Doctor
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                Patient: <strong><?= htmlspecialchars($patient['full_name']) ?></strong>
                <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id']) ?>
                </span>
                <?php if (!empty($branch['name'])): ?>
                    <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch['name']) ?>
                    </span>
                <?php endif; ?>
                <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="patients.php?branch=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION CARD -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user"></i>
                Patient Information
            </h3>
        </div>
        <div class="card-body">
            <div class="patient-info-grid">
                <div class="info-item">
                    <div class="info-label">Patient ID</div>
                    <div class="info-value"><?= htmlspecialchars($patient['patient_id']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><?= htmlspecialchars($patient['full_name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Gender</div>
                    <div class="info-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Phone</div>
                    <div class="info-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Branch</div>
                    <div class="info-value"><?= htmlspecialchars($branch['name'] ?? 'N/A') ?></div>
                </div>
                <?php if (!empty($patient['current_visit_id'])): ?>
                    <div class="info-item">
                        <div class="info-label">Active Visit</div>
                        <div class="info-value">
                            <?= htmlspecialchars($patient['visit_number'] ?? 'N/A') ?>
                            <span class="badge badge-info" style="font-size:0.6rem;padding:2px 10px;">
                                <?= ucfirst($patient['visit_status'] ?? 'Pending') ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- CURRENT DOCTOR STATUS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-stethoscope"></i>
                Current Assigned Doctor
            </h3>
        </div>
        <div class="card-body">
            <?php if ($has_doctor): ?>
                <div class="doctor-status has-doctor">
                    <div class="status-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div>
                        <div class="status-text"><?= htmlspecialchars($patient['current_doctor_name']) ?></div>
                        <div class="status-sub">
                            <?= htmlspecialchars($patient['current_doctor_specialty'] ?? 'General Medicine') ?>
                            <?php if ($patient['current_doctor_online'] ?? false): ?>
                                <span class="badge badge-success" style="font-size:0.55rem;padding:2px 8px;">
                                    <i class="fas fa-circle"></i> Online
                                </span>
                            <?php else: ?>
                                <span class="badge badge-secondary" style="font-size:0.55rem;padding:2px 8px;">
                                    <i class="fas fa-circle"></i> Offline
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($patient['current_doctor_id'])): ?>
                                <span class="badge badge-info" style="font-size:0.55rem;padding:2px 8px;">
                                    ID: <?= $patient['current_doctor_id'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Remove Doctor Button -->
                <button type="button" class="btn btn-danger btn-block btn-lg" onclick="openConfirmModal()">
                    <i class="fas fa-user-minus"></i>
                    REMOVE DOCTOR FROM PATIENT
                </button>
                <p class="text-sm text-gray-500 mt-2 text-center">
                    <i class="fas fa-info-circle"></i> 
                    Patient will have <strong>NO assigned doctor</strong> after removal.
                </p>
                
            <?php else: ?>
                <div class="doctor-status no-doctor">
                    <div class="status-icon">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div>
                        <div class="status-text" style="color: var(--danger);">No Doctor Assigned</div>
                        <div class="status-sub">This patient currently has no assigned doctor</div>
                    </div>
                </div>
                <div class="text-center py-2">
                    <a href="assign_doctor.php?patient_id=<?= $patient_id ?>&branch_id=<?= $branch_id ?>" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Assign Doctor
                    </a>
                    <a href="patients.php?branch=<?= $branch_id ?>" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGES -->
    <!-- ================================================================ -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> animate-fade-in-up" style="animation-delay:0.1s;">
            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Remove Assigned Doctor
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- CONFIRMATION MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3>Confirm Remove Doctor</h3>
        <p>
            Are you sure you want to remove 
            <strong><?= htmlspecialchars($patient['current_doctor_name'] ?? 'the doctor') ?></strong> 
            from <strong><?= htmlspecialchars($patient['full_name']) ?></strong>?
            <br><br>
            <span style="color: var(--danger); font-weight: 600;">
                ⚠️ This action will leave the patient with NO assigned doctor.
            </span>
        </p>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline" onclick="closeConfirmModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="remove_doctor">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-user-minus"></i> Yes, Remove Doctor
                </button>
            </form>
        </div>
    </div>
</div>

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
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=<?= $branch_id ?>';
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // CONFIRMATION MODAL
    // ================================================================
    function openConfirmModal() {
        document.getElementById('confirmModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Close modal on overlay click
    document.getElementById('confirmModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeConfirmModal();
        }
    });
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeConfirmModal();
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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c👨‍⚕️ Remove Doctor from Patient - Braick Dispensary', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name']) ?> (ID: <?= $patient_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🩺 Current Doctor: <?= $has_doctor ? htmlspecialchars($patient['current_doctor_name']) : 'None' ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c⚠️ Action: Remove doctor - patient will be unassigned', 'font-size:13px; color:#DC2626;');
    console.log('%c📊 Tables: patients, users, visits, branches, activity_logs', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Login session: ACTIVE', 'font-size:13px; color:#34D399;');
    console.log('%c🔒 Role: <?= $_SESSION['role'] ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>