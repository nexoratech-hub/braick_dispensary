<?php
// ================================================================
// FILE: frontend/pages/admin/view_visit.php
// ADMIN - VIEW VISIT DETAILS WITH VITAL SIGNS CARDS
// BRAICK DISPENSARY - BLUE THEME - MATCHES view_patient.php
// WITH LOGIN SESSION
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
// CHECK IF USER HAS ADMIN ACCESS
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
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// IF SESSION IS INCOMPLETE, TRY TO RECOVER FROM DATABASE
// ================================================================
if ($user_id <= 0) {
    if (isset($username) && !empty($username)) {
        require_once __DIR__ . '/../../../backend/config/database.php';
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, full_name, role, branch_id, profile_pic FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['profile_pic'] = $user['profile_pic'];
                $user_id = $user['id'];
                $user_full_name = $user['full_name'];
                $user_role = $user['role'];
                $user_branch_id = $user['branch_id'];
                $profile_pic = $user['profile_pic'];
            }
        } catch (Exception $e) {
            // Fallback to session values
        }
    }
}

// If still no user_id, redirect to login
if ($user_id <= 0) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

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
// GET PARAMETERS
// ================================================================
$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? $_GET['branch_id'] ?? 'all';

if ($visit_id <= 0) {
    header('Location: visits.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH VISIT DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            v.*,
            p.id as patient_id,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            p.gender as patient_gender,
            p.date_of_birth,
            p.blood_group,
            p.allergies,
            p.address,
            p.email as patient_email,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            u.is_online as doctor_online,
            r.full_name as receptionist_name,
            b.name as branch_name,
            (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id) as lab_tests_count,
            (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id) as prescriptions_count,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id IN (SELECT id FROM patient_bills WHERE visit_id = v.id)) as bill_items_count
        FROM visits v
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.id = ?
    ");
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visit) {
        header('Location: visits.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching visit: " . $e->getMessage());
    header('Location: visits.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// CALCULATE AGE
// ================================================================
$age = null;
if (!empty($visit['date_of_birth'])) {
    $birthDate = new DateTime($visit['date_of_birth']);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
}

// ================================================================
// FETCH VITAL SIGNS FOR THIS VISIT
// ================================================================
$vital_signs = [];
try {
    $stmt = $db->prepare("
        SELECT 
            vs.*,
            u.full_name as recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.visit_id = ?
        ORDER BY vs.recorded_at DESC
        LIMIT 1
    ");
    $stmt->execute([$visit_id]);
    $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $vital_signs = [];
}

// ================================================================
// FETCH ALL VITAL SIGNS HISTORY
// ================================================================
$vital_signs_history = [];
try {
    $stmt = $db->prepare("
        SELECT 
            vs.*,
            u.full_name as recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.visit_id = ?
        ORDER BY vs.recorded_at DESC
    ");
    $stmt->execute([$visit_id]);
    $vital_signs_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $vital_signs_history = [];
}

// ================================================================
// FETCH LAB TESTS FOR THIS VISIT
// ================================================================
$lab_tests = [];
try {
    $stmt = $db->prepare("
        SELECT 
            lt.*,
            u.full_name as doctor_name,
            (SELECT full_name FROM users WHERE id = lt.lab_technician_id) as technician_name
        FROM lab_tests lt
        LEFT JOIN users u ON lt.doctor_id = u.id
        WHERE lt.visit_id = ?
        ORDER BY lt.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lab_tests = [];
}

// ================================================================
// FETCH PRESCRIPTIONS FOR THIS VISIT
// ================================================================
$prescriptions = [];
try {
    $stmt = $db->prepare("
        SELECT 
            pr.*,
            u.full_name as doctor_name,
            (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = pr.id) as items_count
        FROM prescriptions pr
        LEFT JOIN users u ON pr.doctor_id = u.id
        WHERE pr.visit_id = ?
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prescriptions = [];
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
        'cancelled' => 'danger',
        'paid' => 'success',
        'partial' => 'warning',
        'with_doctor' => 'primary',
        'lab_test' => 'purple',
        'prescribed' => 'teal'
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
        'cancelled' => 'fa-times-circle',
        'with_doctor' => 'fa-stethoscope',
        'lab_test' => 'fa-flask',
        'prescribed' => 'fa-prescription'
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
    <title>View Visit - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BOLDER BLUE THEME (MATCHES view_patient)
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #073B8A);
            
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
            --primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
            --primary-gradient-strong: linear-gradient(135deg, #1D4ED8, #1E40AF);
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
           PAGE HEADER - BOLDER BLUE THEME
           ================================================================ */
        .page-header {
            background: var(--primary-gradient-strong);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
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
           DETAIL CARD
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
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
           VITAL SIGNS CARDS - 6 CARDS (MATCHES view_patient)
           ================================================================ */
        .vital-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 20px 16px;
            text-align: center;
            border: 2px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .vital-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            border-radius: 14px 14px 0 0;
        }
        
        .vital-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }
        
        .vital-card .vital-icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        
        .vital-card .vital-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }
        
        .vital-card .vital-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.04em;
            margin-top: 4px;
        }
        
        .vital-card .vital-unit {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 400;
            margin-left: 2px;
        }
        
        /* Card Colors - 6 Colors */
        .vital-card.blue::before { background: linear-gradient(90deg, #0B5ED7, #1A73E8); }
        .vital-card.blue .vital-icon { color: #0B5ED7; }
        .vital-card.blue .vital-value { color: #0B5ED7; }
        
        .vital-card.red::before { background: linear-gradient(90deg, #EF4444, #F87171); }
        .vital-card.red .vital-icon { color: #EF4444; }
        .vital-card.red .vital-value { color: #EF4444; }
        
        .vital-card.pink::before { background: linear-gradient(90deg, #EC4899, #F472B6); }
        .vital-card.pink .vital-icon { color: #EC4899; }
        .vital-card.pink .vital-value { color: #EC4899; }
        
        .vital-card.purple::before { background: linear-gradient(90deg, #7B2FBE, #9B4DCA); }
        .vital-card.purple .vital-icon { color: #7B2FBE; }
        .vital-card.purple .vital-value { color: #7B2FBE; }
        
        .vital-card.green::before { background: linear-gradient(90deg, #059669, #0AA84F); }
        .vital-card.green .vital-icon { color: #059669; }
        .vital-card.green .vital-value { color: #059669; }
        
        .vital-card.indigo::before { background: linear-gradient(90deg, #4F46E5, #818CF8); }
        .vital-card.indigo .vital-icon { color: #4F46E5; }
        .vital-card.indigo .vital-value { color: #4F46E5; }
        
        /* Dark mode vital cards */
        [data-theme="dark"] .vital-card {
            background: #1E293B;
            border-color: #334155;
        }
        
        [data-theme="dark"] .vital-card:hover {
            border-color: #0B5ED7;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        }
        
        [data-theme="dark"] .vital-card .vital-value {
            color: #F1F5F9;
        }
        
        [data-theme="dark"] .vital-card.blue .vital-value { color: #6EA8FE; }
        [data-theme="dark"] .vital-card.red .vital-value { color: #F87171; }
        [data-theme="dark"] .vital-card.pink .vital-value { color: #F472B6; }
        [data-theme="dark"] .vital-card.purple .vital-value { color: #A78BFA; }
        [data-theme="dark"] .vital-card.green .vital-value { color: #34D399; }
        [data-theme="dark"] .vital-card.indigo .vital-value { color: #A5B4FC; }
        
        /* ================================================================
           TABLE CONTAINER
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .table-container .card-header {
            padding: 14px 20px;
            background: var(--primary-gradient-strong);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .table-container .card-header .card-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-container .card-header .card-title i {
            color: rgba(255,255,255,0.8);
        }
        
        .table-container .card-header .card-action {
            color: rgba(255,255,255,0.7);
            font-size: 0.65rem;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .table-container .card-header .card-action:hover {
            color: white;
        }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.82rem;
        }
        
        .data-table thead th {
            background: var(--bg-body);
            color: var(--text-secondary);
            font-weight: 700;
            padding: 12px 14px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
            text-align: left;
        }
        
        [data-theme="dark"] .data-table thead th {
            background: #0F172A;
        }
        
        .data-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
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
        .badge-primary { background: #0B5ED7; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
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
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 0.85rem;
            margin: 0;
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
            font-weight: 700;
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
            .vital-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .detail-card { padding: 12px 14px; }
            .vital-grid { grid-template-columns: repeat(2, 1fr); }
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
            .detail-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .table-container { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
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

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clinic-medical"></i>
                Visit Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-id-card"></i>
                <strong><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i>
                    <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-<?= $visit['status'] === 'completed' ? 'check-circle' : 'clock' ?>"></i>
                    <?= ucfirst($visit['status'] ?? 'Pending') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-calendar"></i>
                    <?= date('M d, Y', strtotime($visit['visit_date'] ?? 'now')) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="edit_visit.php?id=<?= $visit_id ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="visits.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISIT INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="detail-label"><i class="fas fa-id-card mr-1"></i> Visit Number</p>
                <p class="detail-value"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user mr-1"></i> Patient</p>
                <p class="detail-value">
                    <a href="view_patient.php?id=<?= $visit['patient_id'] ?>&branch=<?= $selected_branch_id ?>" class="text-blue-600 hover:underline">
                        <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>
                    </a>
                    <span class="text-xs text-gray-400 block"><?= htmlspecialchars($visit['patient_code'] ?? '') ?></span>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calendar-plus mr-1"></i> Visit Date</p>
                <p class="detail-value"><?= date('M d, Y h:i A', strtotime($visit['visit_date'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-md mr-1"></i> Doctor</p>
                <p class="detail-value">
                    <?php if (!empty($visit['doctor_name'])): ?>
                        Dr. <?= htmlspecialchars($visit['doctor_name']) ?>
                        <?php if ($visit['doctor_online'] == 1): ?>
                            <span class="text-green-500 text-xs">🟢 Online</span>
                        <?php else: ?>
                            <span class="text-gray-400 text-xs">⚪ Offline</span>
                        <?php endif; ?>
                        <?php if (!empty($visit['doctor_specialty'])): ?>
                            <span class="text-xs text-gray-400">(<?= htmlspecialchars($visit['doctor_specialty']) ?>)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-gray-400 text-sm">Not assigned</span>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-user-plus mr-1"></i> Receptionist</p>
                <p class="detail-value"><?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-store mr-1"></i> Branch</p>
                <p class="detail-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-tag mr-1"></i> Visit Type</p>
                <p class="detail-value">
                    <span class="badge badge-info">
                        <?= ucfirst($visit['visit_type'] ?? 'New') ?>
                    </span>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-clock mr-1"></i> Status</p>
                <p class="detail-value">
                    <span class="badge badge-<?= getStatusBadge($visit['status'] ?? 'pending') ?>">
                        <i class="fas <?= getStatusIcon($visit['status'] ?? 'pending') ?>"></i>
                        <?= ucfirst($visit['status'] ?? 'Pending') ?>
                    </span>
                </p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-calendar-check mr-1"></i> Completed At</p>
                <p class="detail-value">
                    <?php if (!empty($visit['completed_at'])): ?>
                        <?= date('M d, Y h:i A', strtotime($visit['completed_at'])) ?>
                    <?php else: ?>
                        <span class="text-gray-400 text-sm">Not completed</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VITAL SIGNS - 6 CARDS (MATCHES view_patient) -->
    <!-- ================================================================ -->
    <?php if (!empty($vital_signs)): ?>
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-bold text-primary">
                <i class="fas fa-heartbeat" style="color: #EC4899;"></i> Latest Vital Signs
            </h3>
            <span class="text-xs text-gray-400">Recorded: <?= date('M d, Y h:i A', strtotime($vital_signs['recorded_at'] ?? 'now')) ?></span>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
            
            <!-- 1. Temperature -->
            <div class="vital-card blue">
                <div class="vital-icon"><i class="fas fa-thermometer-half"></i></div>
                <div class="vital-value">
                    <?php 
                        $temp = $vital_signs['temperature'] ?? null;
                        echo $temp !== null ? $temp : '-';
                    ?>
                    <span class="vital-unit">°C</span>
                </div>
                <div class="vital-label">Temperature</div>
            </div>
            
            <!-- 2. Blood Pressure - FIXED: Shows only systolic if diastolic is NULL -->
            <div class="vital-card red">
                <div class="vital-icon"><i class="fas fa-heart"></i></div>
                <div class="vital-value">
                    <?php 
                        $systolic = $vital_signs['blood_pressure_systolic'] ?? null;
                        $diastolic = $vital_signs['blood_pressure_diastolic'] ?? null;
                        
                        if ($systolic !== null && $diastolic !== null) {
                            echo $systolic . '/' . $diastolic;
                        } elseif ($systolic !== null) {
                            echo $systolic;
                        } else {
                            echo '-';
                        }
                    ?>
                    <span class="vital-unit">mmHg</span>
                </div>
                <div class="vital-label">Blood Pressure</div>
            </div>
            
            <!-- 3. Pulse Rate -->
            <div class="vital-card pink">
                <div class="vital-icon"><i class="fas fa-heartbeat"></i></div>
                <div class="vital-value">
                    <?php 
                        $pulse = $vital_signs['pulse_rate'] ?? null;
                        echo $pulse !== null ? $pulse : '-';
                    ?>
                    <span class="vital-unit">bpm</span>
                </div>
                <div class="vital-label">Pulse Rate</div>
            </div>
            
            <!-- 4. Weight -->
            <div class="vital-card purple">
                <div class="vital-icon"><i class="fas fa-weight"></i></div>
                <div class="vital-value">
                    <?php 
                        $weight = $vital_signs['weight'] ?? null;
                        echo $weight !== null ? $weight : '-';
                    ?>
                    <span class="vital-unit">kg</span>
                </div>
                <div class="vital-label">Weight</div>
            </div>
            
            <!-- 5. Height -->
            <div class="vital-card green">
                <div class="vital-icon"><i class="fas fa-ruler-vertical"></i></div>
                <div class="vital-value">
                    <?php 
                        $height = $vital_signs['height'] ?? null;
                        echo $height !== null ? $height : '-';
                    ?>
                    <span class="vital-unit">cm</span>
                </div>
                <div class="vital-label">Height</div>
            </div>
            
            <!-- 6. BMI -->
            <div class="vital-card indigo">
                <div class="vital-icon"><i class="fas fa-calculator"></i></div>
                <div class="vital-value">
                    <?php 
                        $bmi = $vital_signs['bmi'] ?? null;
                        echo $bmi !== null ? $bmi : '-';
                    ?>
                </div>
                <div class="vital-label">BMI</div>
            </div>
            
        </div>
        
        <?php if (!empty($vital_signs['notes'])): ?>
        <div class="mt-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <p class="text-xs text-gray-500">📝 Notes</p>
            <p class="text-sm"><?= htmlspecialchars($vital_signs['notes']) ?></p>
        </div>
        <?php endif; ?>
        
        <p class="text-xs text-gray-400 mt-2">
            <i class="fas fa-user"></i> Recorded by: <?= htmlspecialchars($vital_signs['recorded_by_name'] ?? 'N/A') ?>
        </p>
    </div>
    <?php else: ?>
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="flex justify-between items-center">
            <h3 class="text-sm font-bold text-primary">
                <i class="fas fa-heartbeat" style="color: #EC4899;"></i> Vital Signs
            </h3>
            <span class="text-xs text-gray-400">No vital signs recorded</span>
        </div>
        <div class="empty-state">
            <i class="fas fa-heartbeat" style="color: #EC4899;"></i>
            <p>No vital signs recorded for this visit</p>
            <a href="add_vital_signs.php?visit_id=<?= $visit_id ?>&branch=<?= $selected_branch_id ?>" class="text-blue-600 text-sm hover:underline">Add Vital Signs</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- VITAL SIGNS HISTORY (if multiple records) -->
    <!-- ================================================================ -->
    <?php if (count($vital_signs_history) > 1): ?>
    <div class="table-container animate-fade-in-up" style="animation-delay:0.08s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history"></i>
                Vital Signs History (<?= count($vital_signs_history) ?>)
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Temperature</th>
                        <th>BP</th>
                        <th>Pulse</th>
                        <th>Weight</th>
                        <th>Height</th>
                        <th>BMI</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vital_signs_history as $vs): ?>
                    <tr>
                        <td class="text-xs"><?= date('M d, Y h:i A', strtotime($vs['recorded_at'] ?? 'now')) ?></td>
                        <td><?= !empty($vs['temperature']) ? $vs['temperature'] . '°C' : '-' ?></td>
                        <td>
                            <?php 
                                $systolic = $vs['blood_pressure_systolic'] ?? null;
                                $diastolic = $vs['blood_pressure_diastolic'] ?? null;
                                if ($systolic !== null && $diastolic !== null) {
                                    echo $systolic . '/' . $diastolic;
                                } elseif ($systolic !== null) {
                                    echo $systolic;
                                } else {
                                    echo '-';
                                }
                            ?>
                        </td>
                        <td><?= !empty($vs['pulse_rate']) ? $vs['pulse_rate'] . ' bpm' : '-' ?></td>
                        <td><?= !empty($vs['weight']) ? $vs['weight'] . ' kg' : '-' ?></td>
                        <td><?= !empty($vs['height']) ? $vs['height'] . ' cm' : '-' ?></td>
                        <td><?= !empty($vs['bmi']) ? $vs['bmi'] : '-' ?></td>
                        <td><?= htmlspecialchars($vs['recorded_by_name'] ?? 'N/A') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- CLINICAL INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.1s;">
        <h3 class="text-sm font-bold text-primary mb-4">
            <i class="fas fa-notes-medical"></i> Clinical Information
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="detail-label"><i class="fas fa-exclamation-triangle mr-1"></i> Symptoms</p>
                <p class="detail-value"><?= htmlspecialchars($visit['symptoms'] ?? 'None') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-comment-medical mr-1"></i> Complaint</p>
                <p class="detail-value"><?= htmlspecialchars($visit['complaint'] ?? 'None') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-stethoscope mr-1"></i> Diagnosis</p>
                <p class="detail-value"><?= htmlspecialchars($visit['diagnosis'] ?? 'Not yet') ?></p>
            </div>
            <div>
                <p class="detail-label"><i class="fas fa-prescription mr-1"></i> Treatment</p>
                <p class="detail-value"><?= htmlspecialchars($visit['treatment'] ?? 'Not yet') ?></p>
            </div>
            <div class="md:col-span-2">
                <p class="detail-label"><i class="fas fa-sticky-note mr-1"></i> Notes</p>
                <p class="detail-value"><?= htmlspecialchars($visit['notes'] ?? 'None') ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- LAB TESTS -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-flask"></i>
                Lab Tests (<?= count($lab_tests) ?>)
            </h3>
            <a href="lab_tests.php?visit_id=<?= $visit_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($lab_tests) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Doctor</th>
                            <th>Technician</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): ?>
                            <tr>
                                <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($test['technician_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($test['status'] ?? 'pending') ?>">
                                        <?= ucfirst($test['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($test['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_lab_result.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" class="text-blue-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-flask"></i>
                <p>No lab tests found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-prescription"></i>
                Prescriptions (<?= count($prescriptions) ?>)
            </h3>
            <a href="prescriptions.php?visit_id=<?= $visit_id ?>" class="card-action">View All →</a>
        </div>
        <?php if (count($prescriptions) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Prescription #</th>
                            <th>Doctor</th>
                            <th>Diagnosis</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $prescription): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($prescription['diagnosis'] ?? 'N/A') ?></td>
                                <td><?= number_format($prescription['items_count'] ?? 0) ?></td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($prescription['status'] ?? 'pending') ?>">
                                        <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($prescription['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_prescription.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" class="text-blue-600 text-xs hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <p>No prescriptions found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 animate-fade-in-up" style="animation-delay:0.25s;">
        <a href="add_vital_signs.php?visit_id=<?= $visit_id ?>&branch=<?= $selected_branch_id ?>" 
           class="bg-card border-2 border-border rounded-lg p-4 text-center hover:border-primary transition-all hover:shadow-md text-decoration-none">
            <i class="fas fa-heartbeat text-2xl text-red-600 block mb-2"></i>
            <span class="text-sm font-medium text-text-primary">Add Vital Signs</span>
        </a>
        <a href="add_lab_test.php?visit_id=<?= $visit_id ?>&branch=<?= $selected_branch_id ?>" 
           class="bg-card border-2 border-border rounded-lg p-4 text-center hover:border-purple-500 transition-all hover:shadow-md text-decoration-none">
            <i class="fas fa-flask text-2xl text-purple-600 block mb-2"></i>
            <span class="text-sm font-medium text-text-primary">Request Lab Test</span>
        </a>
        <a href="add_prescription.php?visit_id=<?= $visit_id ?>&branch=<?= $selected_branch_id ?>" 
           class="bg-card border-2 border-border rounded-lg p-4 text-center hover:border-green-500 transition-all hover:shadow-md text-decoration-none">
            <i class="fas fa-prescription text-2xl text-green-600 block mb-2"></i>
            <span class="text-sm font-medium text-text-primary">Add Prescription</span>
        </a>
        <a href="edit_visit.php?id=<?= $visit_id ?>&branch=<?= $selected_branch_id ?>" 
           class="bg-card border-2 border-border rounded-lg p-4 text-center hover:border-orange-500 transition-all hover:shadow-md text-decoration-none">
            <i class="fas fa-edit text-2xl text-orange-500 block mb-2"></i>
            <span class="text-sm font-medium text-text-primary">Edit Visit</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visit Details - <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
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

    console.log('%c🏥 Braick Dispensary - View Visit (WITH LOGIN SESSION)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#059669;');
    console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c❤️ Vital Signs: 6 cards (Temp, BP, Pulse, Weight, Height, BMI)', 'font-size:13px; color:#EC4899;');
    console.log('%c🔬 Lab Tests: <?= count($lab_tests) ?>', 'font-size:13px; color:#F59E0B;');
    console.log('%c💊 Prescriptions: <?= count($prescriptions) ?>', 'font-size:13px; color:#7B2FBE;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>