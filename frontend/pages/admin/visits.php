<?php
// ================================================================
// FILE: frontend/pages/admin/visits.php
// ADMIN - VIEW ALL VISITS
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
        (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id) as lab_test_count
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
    $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR v.visit_number LIKE ?)";
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
$completed_visits = 0;
$cancelled_visits = 0;
$total_revenue = 0;

foreach ($visits as $visit) {
    $status = $visit['status'] ?? 'pending';
    switch ($status) {
        case 'pending': $pending_visits++; break;
        case 'assigned': $assigned_visits++; break;
        case 'with_doctor': $with_doctor_visits++; break;
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

function getStatusColor($status) {
    $status = $status ?? 'pending';
    
    $colors = [
        'pending' => 'text-yellow-600',
        'assigned' => 'text-blue-600',
        'with_doctor' => 'text-blue-700',
        'lab_test' => 'text-purple-600',
        'lab_completed' => 'text-blue-600',
        'prescribed' => 'text-blue-600',
        'completed' => 'text-green-600',
        'cancelled' => 'text-red-600',
        'active' => 'text-green-600',
        'inactive' => 'text-red-600',
        'unknown' => 'text-gray-500'
    ];
    return $colors[$status] ?? 'text-gray-500';
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
    <title>Visits - Braick Dispensary</title>
    
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
           STATS CARDS - BLUE THEME
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px 18px;
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
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
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card .stat-content {
            flex: 1;
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
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.05);
        }
        
        .stat-icon.blue { background: var(--primary-bg); color: var(--primary); }
        .stat-icon.green { background: #ECFDF5; color: #059669; }
        .stat-icon.orange { background: #FFFBEB; color: #F59E0B; }
        .stat-icon.purple { background: #F5F3FF; color: #7C3AED; }
        .stat-icon.red { background: #FEF2F2; color: #DC2626; }
        .stat-icon.teal { background: #ECFDF5; color: #0D9488; }
        
        [data-theme="dark"] .stat-icon.blue { background: #1E3A5F; color: #3B82F6; }
        [data-theme="dark"] .stat-icon.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .stat-icon.orange { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .stat-icon.purple { background: #2D1B4E; color: #A78BFA; }
        [data-theme="dark"] .stat-icon.red { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .stat-icon.teal { background: #1A3A2A; color: #2DD4BF; }
        
        .stat-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
        }
        
        .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            line-height: 1.2;
        }
        
        .stat-value.blue-text { color: var(--primary); }
        .stat-value.green-text { color: #059669; }
        .stat-value.orange-text { color: #F59E0B; }
        .stat-value.purple-text { color: #7C3AED; }
        .stat-value.red-text { color: #DC2626; }
        .stat-value.teal-text { color: #0D9488; }
        
        .stat-sub {
            font-size: 0.55rem;
            color: var(--text-secondary);
            margin-top: 1px;
        }
        
        .stat-arrow {
            opacity: 0;
            transition: all 0.3s ease;
            color: var(--primary);
            font-size: 0.7rem;
            flex-shrink: 0;
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
        .badge-primary { background: #0B5ED7; }
        
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
            background: var(--primary-gradient-hover);
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
            overflow-x: auto;
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
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin-bottom: 8px;
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
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
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
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar select, .filter-bar input { width: 100%; min-width: unset; }
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
            <a href="reception_dashboard.php?branch_id=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS - BLUE THEME -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Total Visits</p>
                <p class="stat-value blue-text"><?= number_format($total_visits) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Pending</p>
                <p class="stat-value orange-text"><?= number_format($pending_visits) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Assigned</p>
                <p class="stat-value purple-text"><?= number_format($assigned_visits) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon teal">
                <i class="fas fa-stethoscope"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">With Doctor</p>
                <p class="stat-value teal-text"><?= number_format($with_doctor_visits) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Completed</p>
                <p class="stat-value green-text"><?= number_format($completed_visits) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon red">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <p class="stat-label">Cancelled</p>
                <p class="stat-value red-text"><?= number_format($cancelled_visits) ?></p>
            </div>
            <i class="fas fa-chevron-right stat-arrow"></i>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER BAR -->
    <!-- ================================================================ -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.05s;">
        <form method="GET" class="flex flex-wrap gap-3 items-center w-full">
            <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
            
            <select name="status" onchange="this.form.submit()" class="flex-1 min-w-[150px]">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="assigned" <?= $status_filter === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                <option value="with_doctor" <?= $status_filter === 'with_doctor' ? 'selected' : '' ?>>With Doctor</option>
                <option value="lab_test" <?= $status_filter === 'lab_test' ? 'selected' : '' ?>>Lab Test</option>
                <option value="lab_completed" <?= $status_filter === 'lab_completed' ? 'selected' : '' ?>>Lab Completed</option>
                <option value="prescribed" <?= $status_filter === 'prescribed' ? 'selected' : '' ?>>Prescribed</option>
                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="flex-1 min-w-[150px]" placeholder="Date From">
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="flex-1 min-w-[150px]" placeholder="Date To">
            
            <input type="text" name="search" placeholder="Search patient or visit..." value="<?= htmlspecialchars($search) ?>" class="flex-1 min-w-[200px]">
            
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
    <div class="card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list text-blue-600"></i>
                Visit Records
                <span class="text-xs text-gray-500 ml-2">(<?= count($visits) ?> records)</span>
            </h3>
            <div class="flex gap-2">
                <span class="text-xs text-gray-400">
                    <i class="far fa-clock"></i> Showing latest 100 records
                </span>
            </div>
        </div>
        <div class="table-container">
            <?php if (count($visits) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Visit #</th>
                            <th>Patient</th>
                            <th>Patient ID</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visits as $visit): 
                            $status = $visit['status'] ?? 'pending';
                            $net_total = ($visit['visit_total'] ?? 0) - ($visit['total_discount'] ?? 0);
                        ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold">
                                    <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                                </td>
                                <td class="font-medium">
                                    <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>
                                </td>
                                <td class="font-mono text-xs">
                                    <?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?>
                                </td>
                                <td>
                                    <?php if (!empty($visit['doctor_name'])): ?>
                                        <span class="badge badge-info" style="font-size:0.55rem;padding:2px 10px;">
                                            <i class="fas fa-user-md"></i> <?= htmlspecialchars($visit['doctor_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-xs">
                                    <?= date('M d, Y h:i A', strtotime($visit['visit_date'] ?? 'now')) ?>
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
                                </td>
                                <td class="font-semibold <?= $net_total > 0 ? 'text-green-600' : 'text-gray-400' ?>">
                                    <?php if ($net_total > 0): ?>
                                        TSh <?= number_format($net_total, 0) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex gap-1 flex-wrap">
                                        <a href="view_visit.php?id=<?= $visit['id'] ?>&branch_id=<?= $branch_id ?>" class="text-blue-600 text-xs hover:underline">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($status === 'pending' || $status === 'assigned'): ?>
                                            <span class="text-gray-300">|</span>
                                            <a href="assign_doctor_visit.php?visit_id=<?= $visit['id'] ?>&branch_id=<?= $branch_id ?>" class="text-green-600 text-xs hover:underline">
                                                <i class="fas fa-user-md"></i> Assign
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($status === 'completed'): ?>
                                            <span class="text-gray-300">|</span>
                                            <a href="view_bill.php?visit_id=<?= $visit['id'] ?>&branch_id=<?= $branch_id ?>" class="text-purple-600 text-xs hover:underline">
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
                    <h3>No Visits Found</h3>
                    <p class="text-gray-400">
                        <?= !empty($search) || $status_filter !== 'all' || !empty($date_from) || !empty($date_to) 
                            ? 'No visits match your search criteria.' 
                            : 'No patient visits have been recorded in this branch yet.' ?>
                    </p>
                    <?php if (!empty($search) || $status_filter !== 'all' || !empty($date_from) || !empty($date_to)): ?>
                        <a href="visits.php?branch_id=<?= $branch_id ?>" class="btn btn-primary mt-4">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
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

    console.log('%c🏥 Braick Dispensary - Visits (BLUE THEME)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Visits: <?= number_format($total_visits) ?>', 'font-size:13px; color:#059669;');
    console.log('%c⏳ Pending: <?= $pending_visits ?> | Assigned: <?= $assigned_visits ?> | With Doctor: <?= $with_doctor_visits ?>', 'font-size:13px; color:#F59E0B;');
    console.log('%c✅ Completed: <?= $completed_visits ?> | ❌ Cancelled: <?= $cancelled_visits ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#0D9488;');
</script>

</body>
</html>