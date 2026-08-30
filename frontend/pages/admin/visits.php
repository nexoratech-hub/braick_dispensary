<?php
// ================================================================
// FILE: frontend/pages/admin/visits.php
// ADMIN - VIEW ALL VISITS (MATCHES view_patients.php DESIGN)
// BRAICK DISPENSARY - BLUE THEME - WITH LOGIN SESSION
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
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($_SESSION['branch_id'] ?? 1);
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ================================================================
// FUNCTION TO CHECK IF BRANCH EXISTS
// ================================================================
function branchExists($db, $branch_id) {
    try {
        $stmt = $db->prepare("SELECT id FROM branches WHERE id = ?");
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
// BUILD VISITS QUERY
// ================================================================
$sql = "
    SELECT 
        v.*,
        p.full_name as patient_name,
        p.patient_id as patient_number,
        p.phone as patient_phone,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        r.full_name as receptionist_name,
        b.name as branch_name,
        (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id) as prescription_count,
        (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id) as lab_test_count,
        (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status = 'paid') as paid_bill_count,
        (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status = 'pending') as pending_bill_count,
        (SELECT COUNT(*) FROM bills WHERE visit_id = v.id AND status = 'partial') as partial_bill_count
    FROM visits v
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN users r ON v.receptionist_id = r.id
    LEFT JOIN branches b ON v.branch_id = b.id
    WHERE v.branch_id = ?
";

$params = [$branch_id];

if ($status_filter !== 'all') {
    $sql .= " AND v.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR v.visit_number LIKE ? OR p.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($date_from)) {
    $sql .= " AND DATE(v.visit_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND DATE(v.visit_date) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY v.visit_date DESC LIMIT 100";

$visits = [];
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $visits = [];
}

// ================================================================
// CALCULATE STATISTICS
// ================================================================
$total_visits = count($visits);
$pending_visits = 0;
$assigned_visits = 0;
$with_doctor_visits = 0;
$lab_test_visits = 0;
$completed_visits = 0;
$cancelled_visits = 0;
$total_revenue = 0;

foreach ($visits as $visit) {
    $status = $visit['status'] ?? 'pending';
    switch ($status) {
        case 'pending': $pending_visits++; break;
        case 'assigned': $assigned_visits++; break;
        case 'with_doctor': $with_doctor_visits++; break;
        case 'lab_test': $lab_test_visits++; break;
        case 'completed': $completed_visits++; break;
        case 'cancelled': $cancelled_visits++; break;
    }
    $total_revenue += ($visit['visit_total'] ?? 0) - ($visit['total_discount'] ?? 0);
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
// GET BRANCH NAME
// ================================================================
$branch_name = 'Unknown';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
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
    $status = $status ?? 'pending';
    
    $classes = [
        'pending' => 'warning',
        'assigned' => 'info',
        'with_doctor' => 'primary',
        'lab_test' => 'purple',
        'lab_completed' => 'info',
        'prescribed' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger',
        'active' => 'success',
        'inactive' => 'danger',
        'dispensed' => 'success',
        'confirmed' => 'info',
        'paid' => 'success',
        'partial' => 'warning',
        'scheduled' => 'info',
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
    $status = $status ?? 'pending';
    
    $icons = [
        'pending' => 'fa-clock',
        'assigned' => 'fa-user-check',
        'with_doctor' => 'fa-stethoscope',
        'lab_test' => 'fa-flask',
        'lab_completed' => 'fa-check-double',
        'prescribed' => 'fa-prescription',
        'completed' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle',
        'active' => 'fa-check-circle',
        'inactive' => 'fa-times-circle',
        'dispensed' => 'fa-check-circle',
        'confirmed' => 'fa-check-double',
        'paid' => 'fa-check-circle',
        'partial' => 'fa-clock',
        'scheduled' => 'fa-calendar-check',
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
    <title>Visits - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BOLDER BLUE THEME (MATCHES view_patients.php)
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
            --table-stripe: #F8FAFC;
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
            --table-stripe: #1E293B;
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
           STATS CARDS - 7 CARDS (MATCHES view_patients.php)
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px 18px;
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--primary-gradient-strong);
            border-radius: 0 3px 3px 0;
            opacity: 0.8;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(11, 94, 215, 0.15);
        }
        
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.1;
        }
        
        .stat-card .stat-number.blue { color: var(--primary); }
        .stat-card .stat-number.green { color: #059669; }
        .stat-card .stat-number.orange { color: #F59E0B; }
        .stat-card .stat-number.purple { color: #7C3AED; }
        .stat-card .stat-number.teal { color: #0D9488; }
        .stat-card .stat-number.red { color: #DC2626; }
        .stat-card .stat-number.pink { color: #EC4899; }
        .stat-card .stat-number.yellow { color: #D97706; }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
        }
        
        .stat-card .stat-icon-small {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        
        .stat-card .stat-icon-small.blue { background: var(--primary-bg); color: var(--primary); }
        .stat-card .stat-icon-small.green { background: #ECFDF5; color: #059669; }
        .stat-card .stat-icon-small.orange { background: #FFFBEB; color: #F59E0B; }
        .stat-card .stat-icon-small.purple { background: #F5F3FF; color: #7C3AED; }
        .stat-card .stat-icon-small.teal { background: #ECFDF5; color: #0D9488; }
        .stat-card .stat-icon-small.red { background: #FEF2F2; color: #DC2626; }
        .stat-card .stat-icon-small.pink { background: #FDF2F8; color: #EC4899; }
        .stat-card .stat-icon-small.yellow { background: #FFFBEB; color: #D97706; }
        
        [data-theme="dark"] .stat-card .stat-icon-small.blue { background: #1E3A5F; color: #3B82F6; }
        [data-theme="dark"] .stat-card .stat-icon-small.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .stat-card .stat-icon-small.orange { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .stat-card .stat-icon-small.purple { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .stat-card .stat-icon-small.teal { background: #0F3D3D; color: #5EEAD4; }
        [data-theme="dark"] .stat-card .stat-icon-small.red { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .stat-card .stat-icon-small.pink { background: #3A1A2A; color: #F472B6; }
        [data-theme="dark"] .stat-card .stat-icon-small.yellow { background: #3D2E0A; color: #FBBF24; }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
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
        .badge-primary { background: #0B5ED7; }
        .badge-teal { background: #0D9488; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        /* ================================================================
           FILTER BAR
           ================================================================ */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            align-items: center;
            background: var(--bg-card);
            padding: 16px 20px;
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        
        .filter-bar:hover {
            border-color: var(--primary-light);
        }
        
        .filter-bar select, .filter-bar input {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 8px 14px;
            font-size: 0.8rem;
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
            min-width: 150px;
        }
        
        .filter-bar select:focus, .filter-bar input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
        }
        
        .filter-bar .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-gradient-strong);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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
        
        /* ================================================================
           DATA TABLE
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
            border-collapse: collapse;
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
            position: sticky;
            top: 0;
            z-index: 5;
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
        
        .data-table tbody tr:nth-child(even) td {
            background: var(--table-stripe);
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* ================================================================
           ACTION LINKS
           ================================================================ */
        .action-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.7rem;
            padding: 2px 10px;
            border-radius: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
            font-weight: 500;
        }
        
        .action-link.view {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .action-link.view:hover {
            background: var(--primary);
            color: white;
        }
        
        .action-link.assign {
            background: #ECFDF5;
            color: #059669;
        }
        
        .action-link.assign:hover {
            background: #059669;
            color: white;
        }
        
        .action-link.bill {
            background: #F5F3FF;
            color: #7C3AED;
        }
        
        .action-link.bill:hover {
            background: #7C3AED;
            color: white;
        }
        
        [data-theme="dark"] .action-link.view {
            background: #1E3A5F;
            color: #3B82F6;
        }
        
        [data-theme="dark"] .action-link.view:hover {
            background: #3B82F6;
            color: white;
        }
        
        [data-theme="dark"] .action-link.assign {
            background: #1A3A2A;
            color: #34D399;
        }
        
        [data-theme="dark"] .action-link.assign:hover {
            background: #34D399;
            color: white;
        }
        
        [data-theme="dark"] .action-link.bill {
            background: #2D1B4E;
            color: #A78BFA;
        }
        
        [data-theme="dark"] .action-link.bill:hover {
            background: #A78BFA;
            color: white;
        }
        
        .action-divider {
            color: var(--border-color);
            font-size: 0.6rem;
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
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(4, 1fr); }
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar select, .filter-bar input { width: 100%; min-width: unset; }
            .data-table { font-size: 0.7rem; }
            .data-table td, .data-table th { padding: 8px 10px; }
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
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .filter-bar, .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .page-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .header-badge, .role-badge-display {
                color: white !important;
            }
            .table-container { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .stat-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
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
            <input type="text" id="searchInput" placeholder="Search visits..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>>
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
                <i class="fas fa-hospital-user"></i>
                Patient Visits
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($branch_name) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-calendar-day"></i> <?= date('M d, Y') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-users"></i> <?= number_format($total_visits) ?> Visits
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue, 0) ?> Revenue
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS - 7 CARDS (MATCHES view_patients.php) -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card">
            <div class="stat-icon-small blue"><i class="fas fa-users"></i></div>
            <div>
                <p class="stat-label">Total Visits</p>
                <p class="stat-number blue"><?= number_format($total_visits) ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon-small orange"><i class="fas fa-clock"></i></div>
            <div>
                <p class="stat-label">Pending</p>
                <p class="stat-number orange"><?= number_format($pending_visits) ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon-small purple"><i class="fas fa-user-check"></i></div>
            <div>
                <p class="stat-label">Assigned</p>
                <p class="stat-number purple"><?= number_format($assigned_visits) ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon-small teal"><i class="fas fa-stethoscope"></i></div>
            <div>
                <p class="stat-label">With Doctor</p>
                <p class="stat-number teal"><?= number_format($with_doctor_visits) ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon-small purple"><i class="fas fa-flask"></i></div>
            <div>
                <p class="stat-label">Lab Test</p>
                <p class="stat-number purple"><?= number_format($lab_test_visits) ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon-small green"><i class="fas fa-check-circle"></i></div>
            <div>
                <p class="stat-label">Completed</p>
                <p class="stat-number green"><?= number_format($completed_visits) ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon-small red"><i class="fas fa-times-circle"></i></div>
            <div>
                <p class="stat-label">Cancelled</p>
                <p class="stat-number red"><?= number_format($cancelled_visits) ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER BAR -->
    <!-- ================================================================ -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.05s;">
        <form method="GET" class="flex flex-wrap gap-3 items-center w-full">
            <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
            
            <select name="status" onchange="this.form.submit()" class="flex-1 min-w-[150px]">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>📋 All Status</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                <option value="assigned" <?= $status_filter === 'assigned' ? 'selected' : '' ?>>👨‍⚕️ Assigned</option>
                <option value="with_doctor" <?= $status_filter === 'with_doctor' ? 'selected' : '' ?>>🩺 With Doctor</option>
                <option value="lab_test" <?= $status_filter === 'lab_test' ? 'selected' : '' ?>>🧪 Lab Test</option>
                <option value="lab_completed" <?= $status_filter === 'lab_completed' ? 'selected' : '' ?>>✅ Lab Completed</option>
                <option value="prescribed" <?= $status_filter === 'prescribed' ? 'selected' : '' ?>>💊 Prescribed</option>
                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>✅ Completed</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
            </select>
            
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="flex-1 min-w-[150px]" placeholder="Date From">
            <span class="text-gray-400 text-sm">→</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="flex-1 min-w-[150px]" placeholder="Date To">
            
            <input type="text" name="search" placeholder="🔍 Search patient, ID or visit..." value="<?= htmlspecialchars($search) ?>" class="flex-1 min-w-[200px]">
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="visits.php?branch_id=<?= $branch_id ?>" class="btn btn-outline">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- VISITS TABLE -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i>
                Visit Records
                <span class="text-sm font-normal text-white/70">(<?= count($visits) ?> records)</span>
            </h3>
            <span class="text-xs text-white/70">
                <i class="far fa-clock"></i> Showing latest 100 records
            </span>
        </div>
        <div class="overflow-x-auto">
            <?php if (count($visits) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="min-width:120px;"><i class="fas fa-hashtag"></i> Visit #</th>
                            <th style="min-width:150px;"><i class="fas fa-user"></i> Patient</th>
                            <th style="min-width:100px;"><i class="fas fa-id-card"></i> Patient ID</th>
                            <th style="min-width:130px;"><i class="fas fa-user-md"></i> Doctor</th>
                            <th style="min-width:140px;"><i class="fas fa-calendar-alt"></i> Date</th>
                            <th style="min-width:90px;"><i class="fas fa-tag"></i> Type</th>
                            <th style="min-width:100px;"><i class="fas fa-circle"></i> Status</th>
                            <th style="min-width:100px;"><i class="fas fa-money-bill-wave"></i> Total</th>
                            <th style="min-width:150px;"><i class="fas fa-cogs"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visits as $visit): 
                            $status = $visit['status'] ?? 'pending';
                            $net_total = ($visit['visit_total'] ?? 0) - ($visit['total_discount'] ?? 0);
                            $has_pending_bills = ($visit['pending_bill_count'] ?? 0) > 0;
                            $has_paid_bills = ($visit['paid_bill_count'] ?? 0) > 0;
                            $has_partial_bills = ($visit['partial_bill_count'] ?? 0) > 0;
                            $bill_status = '';
                            if ($has_paid_bills && !$has_pending_bills && !$has_partial_bills) {
                                $bill_status = 'badge-success';
                            } elseif ($has_partial_bills) {
                                $bill_status = 'badge-warning';
                            } elseif ($has_pending_bills) {
                                $bill_status = 'badge-secondary';
                            }
                        ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold">
                                    <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                                    <?php if ($bill_status): ?>
                                        <br><span class="badge <?= $bill_status ?>" style="font-size:0.5rem;">💰 <?= ucfirst(str_replace('badge-', '', $bill_status)) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="font-medium">
                                    <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>
                                    <?php if (!empty($visit['patient_phone'])): ?>
                                        <span class="text-xs text-gray-400 block">📱 <?= htmlspecialchars($visit['patient_phone']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="font-mono text-xs">
                                    <?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?>
                                </td>
                                <td>
                                    <?php if (!empty($visit['doctor_name'])): ?>
                                        <span class="badge badge-info" style="font-size:0.55rem;padding:2px 10px;">
                                            <i class="fas fa-user-md"></i> <?= htmlspecialchars($visit['doctor_name']) ?>
                                        </span>
                                        <?php if (!empty($visit['doctor_specialty'])): ?>
                                            <span class="text-xs text-gray-400 block"><?= htmlspecialchars($visit['doctor_specialty']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-xs">
                                    <div><strong><?= date('M d, Y', strtotime($visit['visit_date'] ?? 'now')) ?></strong></div>
                                    <div class="text-gray-400"><?= date('h:i A', strtotime($visit['visit_date'] ?? 'now')) ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($visit['visit_type'] ?? 'new') ?>" style="font-size:0.55rem;padding:2px 10px;">
                                        <?= ucfirst($visit['visit_type'] ?? 'New') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($status) ?>" style="font-size:0.55rem;padding:2px 10px;">
                                        <i class="fas <?= getStatusIcon($status) ?>"></i>
                                        <?= ucfirst(str_replace('_', ' ', $status)) ?>
                                    </span>
                                    <?php if ($status === 'completed'): ?>
                                        <?php if (!empty($visit['completed_at'])): ?>
                                            <span class="text-xs text-gray-400 block">✅ <?= date('M d, Y', strtotime($visit['completed_at'])) ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="font-semibold <?= $net_total > 0 ? 'text-green-600' : 'text-gray-400' ?>">
                                    <?php if ($net_total > 0): ?>
                                        TSh <?= number_format($net_total, 0) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <a href="view_visit.php?id=<?= $visit['id'] ?>&branch_id=<?= $branch_id ?>" class="action-link view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($status === 'pending' || $status === 'assigned'): ?>
                                            <span class="action-divider">|</span>
                                            <a href="assign_doctor.php?visit_id=<?= $visit['id'] ?>&branch_id=<?= $branch_id ?>" class="action-link assign">
                                                <i class="fas fa-user-md"></i> Assign
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($status === 'completed'): ?>
                                            <span class="action-divider">|</span>
                                            <a href="view_bill.php?visit_id=<?= $visit['id'] ?>&branch_id=<?= $branch_id ?>" class="action-link bill">
                                                <i class="fas fa-receipt"></i> Bill
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-hospital-user"></i>
                    <p>No visits found matching your criteria</p>
                    <p class="text-sm text-gray-400">Try adjusting your filters or search terms</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visits - <?= htmlspecialchars($branch_name) ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
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
        var url = new URL(window.location.href);
        if (query.length > 0) {
            url.searchParams.set('search', query);
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
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
        url.searchParams.set('branch_id', branchId);
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

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.textContent = message || '';
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 4000);
    }

    console.log('%c🏥 Braick Dispensary - Visits (BEAUTIFUL CSS)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏥 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📊 Total Visits: <?= number_format($total_visits) ?>', 'font-size:13px; color:#059669;');
    console.log('%c⏳ Pending: <?= $pending_visits ?> | 👨‍⚕️ Assigned: <?= $assigned_visits ?> | 🩺 With Doctor: <?= $with_doctor_visits ?>', 'font-size:13px; color:#F59E0B;');
    console.log('%c✅ Completed: <?= $completed_visits ?> | ❌ Cancelled: <?= $cancelled_visits ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>