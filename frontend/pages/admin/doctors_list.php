<?php
// ================================================================
// FILE: frontend/pages/admin/doctors_list.php
// DOCTORS LIST - VIEW ALL DOCTORS
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
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
// CHECK IF USER HAS ADMIN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    // Redirect based on role
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
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
    if (isset($user_username) && !empty($user_username)) {
        require_once __DIR__ . '/../../../backend/config/database.php';
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, full_name, role, branch_id, profile_pic FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$user_username]);
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
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

// ================================================================
// GET DATABASE CONNECTION
// ================================================================
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// VARIABLES
// ================================================================
$message = '';
$message_type = '';
$per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$selected_branch_id = $_GET['branch'] ?? 'all';

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// HANDLE TOGGLE DOCTOR STATUS (Online/Offline)
// ================================================================
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $doctor_id = (int)$_GET['toggle'];
    
    // Get current status
    $stmt = $db->prepare("SELECT full_name, is_online FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doctor) {
        $new_status = $doctor['is_online'] == 1 ? 0 : 1;
        $action_text = $new_status == 1 ? 'online' : 'offline';
        
        // Update doctor status
        $stmt = $db->prepare("UPDATE users SET is_online = ?, last_online = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $doctor_id]);
        
        // Also update doctor_status table if exists
        try {
            $stmt = $db->prepare("
                INSERT INTO doctor_status (doctor_id, is_online, updated_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE is_online = ?, updated_at = NOW()
            ");
            $stmt->execute([$doctor_id, $new_status, $new_status]);
        } catch (Exception $e) {
            // Table might not exist, ignore
        }
        
        // Log activity
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                VALUES (?, ?, 'doctor_status_changed', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $user_branch_id,
                "Dr. {$doctor['full_name']} changed status to: $action_text"
            ]);
        } catch (Exception $e) {}
        
        $message = "Dr. {$doctor['full_name']} is now " . ($new_status == 1 ? '🟢 ONLINE' : '🔴 OFFLINE');
        $message_type = 'success';
        
        // Redirect to remove toggle parameter
        header("Location: doctors_list.php?page=$page" . ($search ? "&search=" . urlencode($search) : "") . ($status_filter ? "&status=" . urlencode($status_filter) : "") . "&branch=" . $selected_branch_id);
        exit();
    }
}

// ================================================================
// BUILD QUERY WITH FILTERS
// ================================================================
$where_clause = " WHERE role = 'doctor'";
$params = [];

// Search filter
if (!empty($search)) {
    $where_clause .= " AND (full_name LIKE ? OR specialty LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Status filter (online/offline)
if (!empty($status_filter)) {
    if ($status_filter === 'online') {
        $where_clause .= " AND is_online = 1";
    } elseif ($status_filter === 'offline') {
        $where_clause .= " AND (is_online = 0 OR is_online IS NULL)";
    }
}

// Branch filter
if ($selected_branch_id !== 'all') {
    $where_clause .= " AND branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// ================================================================
// GET DOCTORS WITH PAGINATION
// ================================================================

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_doctors / $per_page);

// Get doctors for current page
$sql = "
    SELECT u.*, b.name as branch_name,
           (SELECT COUNT(*) FROM visits WHERE doctor_id = u.id) as total_visits,
           (SELECT COUNT(*) FROM prescriptions WHERE doctor_id = u.id) as total_prescriptions,
           (SELECT COUNT(*) FROM patients WHERE assigned_doctor_id = u.id) as total_patients
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    $where_clause
    ORDER BY u.full_name ASC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================

// Total doctors
$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor'");
$total_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Online doctors
$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND is_online = 1");
$online_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Offline doctors
$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND (is_online = 0 OR is_online IS NULL)");
$offline_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Doctors with patients assigned
$stmt = $db->query("
    SELECT COUNT(DISTINCT assigned_doctor_id) as total 
    FROM patients 
    WHERE assigned_doctor_id IS NOT NULL
");
$with_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

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
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';

// ================================================================
// PAGE TITLE
// ================================================================
$page_title = 'Doctors';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors List - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
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
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
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
            --table-hover: #E8F0FE;
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
            --table-hover: #1E3A5F;
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
           STAT CARDS
           ================================================================ */
        .stat-card-mini {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .stat-card-mini:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-color: var(--primary);
        }
        
        .stat-card-mini .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-card-mini .stat-number.green { color: var(--success); }
        .stat-card-mini .stat-number.orange { color: var(--warning); }
        .stat-card-mini .stat-number.red { color: var(--danger); }
        .stat-card-mini .stat-number.purple { color: var(--purple); }
        
        .stat-card-mini .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .stat-card-mini .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 4px;
        }
        
        /* ================================================================
           FILTER SECTION
           ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            margin-bottom: 18px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
        }
        
        .filter-section .filter-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-right: 4px;
        }
        
        .filter-btn {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .filter-btn.active:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        [data-theme="dark"] .filter-btn:hover {
            background: #1E3A5F;
            border-color: var(--primary);
            color: #6EA8FE;
        }
        
        .filter-btn i { margin-right: 4px; }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            border: 2px solid var(--border-color);
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
        
        .title-blue { color: var(--primary); }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
        }
        
        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table tbody tr:nth-child(even) {
            background: var(--primary-bg);
        }
        
        .data-table tbody tr:nth-child(odd) {
            background: var(--bg-card);
        }
        
        .data-table tbody tr:hover {
            background: var(--table-hover);
        }
        
        [data-theme="dark"] .data-table tbody tr:hover {
            background: #1E3A5F;
        }
        
        .data-table td {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: white;
            border: none;
        }
        
        .badge-info { background: var(--primary); }
        .badge-blue { background: var(--primary); }
        .badge-green { background: var(--success); }
        .badge-danger { background: var(--danger); }
        .badge-purple { background: var(--purple); }
        
        /* ================================================================
           STATUS BADGE
           ================================================================ */
        .status-badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-badge.online {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .status-badge.offline {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        [data-theme="dark"] .status-badge.online {
            background: #1A3A2A;
            color: #34D399;
        }
        
        [data-theme="dark"] .status-badge.offline {
            background: #3A1A1A;
            color: #F87171;
        }
        
        /* ================================================================
           DOCTOR AVATAR
           ================================================================ */
        .doctor-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            color: white;
            flex-shrink: 0;
        }
        
        .doctor-avatar.blue { background: linear-gradient(135deg, #0B5ED7, #1A73E8); }
        .doctor-avatar.green { background: linear-gradient(135deg, #059669, #0AA84F); }
        .doctor-avatar.purple { background: linear-gradient(135deg, #7B2FBE, #9B4DCA); }
        .doctor-avatar.orange { background: linear-gradient(135deg, #F59E0B, #FBBF24); }
        .doctor-avatar.red { background: linear-gradient(135deg, #EF4444, #F87171); }
        .doctor-avatar.pink { background: linear-gradient(135deg, #EC4899, #F472B6); }
        .doctor-avatar.teal { background: linear-gradient(135deg, #0D9488, #14B8A6); }
        
        .doctor-avatar-wrapper {
            position: relative;
            display: inline-block;
        }
        
        .doctor-avatar-wrapper .online-indicator {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid var(--bg-card);
        }
        
        .doctor-avatar-wrapper .online-indicator.online { background: var(--success); }
        .doctor-avatar-wrapper .online-indicator.offline { background: var(--danger); }
        
        /* ================================================================
           ACTION BUTTONS - WITH BACKGROUND COLORS
           ================================================================ */
        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            cursor: pointer;
            letter-spacing: 0.02em;
        }
        
        .btn-action i { font-size: 0.7rem; }
        
        /* ================================================================
           VIEW Button - Blue
           ================================================================ */
        .btn-view {
            background: #E8F0FE;
            color: #0B5ED7;
            border: 2px solid rgba(11, 94, 215, 0.2);
        }
        
        .btn-view:hover {
            background: #0B5ED7;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        }
        
        [data-theme="dark"] .btn-view {
            background: #1E3A5F;
            color: #6EA8FE;
            border-color: rgba(59, 130, 246, 0.2);
        }
        [data-theme="dark"] .btn-view:hover {
            background: #0B5ED7;
            color: white;
        }
        
        /* ================================================================
           DASHBOARD Button - Purple
           ================================================================ */
        .btn-dashboard {
            background: #EDE9FE;
            color: #7C3AED;
            border: 2px solid rgba(124, 58, 237, 0.2);
        }
        
        .btn-dashboard:hover {
            background: #7C3AED;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(124, 58, 237, 0.3);
        }
        
        [data-theme="dark"] .btn-dashboard {
            background: #2D1B4E;
            color: #A78BFA;
            border-color: rgba(167, 139, 250, 0.2);
        }
        [data-theme="dark"] .btn-dashboard:hover {
            background: #7C3AED;
            color: white;
        }
        
        /* ================================================================
           EDIT Button - Orange (REMOVED - Kept for reference)
           ================================================================ */
        /* .btn-edit {
            background: #FEF3C7;
            color: #D97706;
            border: 2px solid rgba(217, 119, 6, 0.2);
        }
        
        .btn-edit:hover {
            background: #D97706;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(217, 119, 6, 0.3);
        } */
        
        /* ================================================================
           TOGGLE ONLINE Button - Green
           ================================================================ */
        .btn-toggle-online {
            background: #D1FAE5;
            color: #059669;
            border: 2px solid rgba(5, 150, 105, 0.2);
        }
        
        .btn-toggle-online:hover {
            background: #059669;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }
        
        [data-theme="dark"] .btn-toggle-online {
            background: #1A3A2A;
            color: #34D399;
            border-color: rgba(52, 211, 153, 0.2);
        }
        [data-theme="dark"] .btn-toggle-online:hover {
            background: #059669;
            color: white;
        }
        
        /* ================================================================
           TOGGLE OFFLINE Button - Red
           ================================================================ */
        .btn-toggle-offline {
            background: #FEE2E2;
            color: #DC2626;
            border: 2px solid rgba(220, 38, 38, 0.2);
        }
        
        .btn-toggle-offline:hover {
            background: #DC2626;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.3);
        }
        
        [data-theme="dark"] .btn-toggle-offline {
            background: #3A1A1A;
            color: #F87171;
            border-color: rgba(248, 113, 113, 0.2);
        }
        [data-theme="dark"] .btn-toggle-offline:hover {
            background: #DC2626;
            color: white;
        }
        
        /* ================================================================
           ADD Button - Green (REMOVED - Kept for reference)
           ================================================================ */
        /* .btn-add {
            background: #059669;
            color: white;
            border: 2px solid #059669;
        }
        
        .btn-add:hover {
            background: #047857;
            border-color: #047857;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        } */
        
        /* ================================================================
           PAGINATION
           ================================================================ */
        .pagination {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .pagination .page-link {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.3s;
            background: var(--bg-card);
        }
        
        .pagination .page-link:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
            .stat-card-mini .stat-number { font-size: 1.4rem; }
            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-section .filter-label {
                margin-bottom: 4px;
            }
            .data-table tbody td {
                font-size: 0.7rem;
                padding: 6px 10px !important;
            }
            .doctor-avatar {
                width: 28px;
                height: 28px;
                font-size: 0.7rem;
            }
            .action-buttons { flex-direction: column; }
            .action-buttons .btn-action { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .stat-card-mini { padding: 10px 12px; }
            .stat-card-mini .stat-number { font-size: 1.2rem; }
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
            <form method="GET" action="" class="flex-1 flex">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Search doctors..." 
                       class="flex-1 px-3 py-2 bg-transparent border-none outline-none text-sm" 
                       style="color: var(--text-primary);">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
            </form>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches_list as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
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

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md"></i>
                Doctors
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                Manage all doctors in the system
                <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-user-md"></i> <?= $total_all ?> Total
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-circle"></i> <?= $online_count ?> Online
                </span>
                <span class="header-badge" style="background:rgba(248,113,113,0.2);border-color:rgba(248,113,113,0.3);color:#F87171;">
                    <i class="fas fa-circle"></i> <?= $offline_count ?> Offline
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <!-- Add Doctor Button REMOVED -->
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5 animate-fade-in-up">
        
        <div class="stat-card-mini">
            <div class="stat-icon">👨‍⚕️</div>
            <p class="stat-number"><?= $total_all ?></p>
            <p class="stat-label">Total Doctors</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">🟢</div>
            <p class="stat-number green"><?= $online_count ?></p>
            <p class="stat-label">Online</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">🔴</div>
            <p class="stat-number red"><?= $offline_count ?></p>
            <p class="stat-label">Offline</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">👤</div>
            <p class="stat-number purple"><?= $with_patients ?></p>
            <p class="stat-label">With Patients</p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up" style="animation-delay:0.05s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Status:</span>
        
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => '', 'page' => 1])) ?>" 
           class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">
            <i class="fas fa-globe"></i> All
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'online', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'online' ? 'active' : '' ?>">
            <i class="fas fa-circle" style="color: var(--success);"></i> Online
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'offline', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'offline' ? 'active' : '' ?>">
            <i class="fas fa-circle" style="color: var(--danger);"></i> Offline
        </a>
        
        <?php if (!empty($search) || !empty($status_filter)): ?>
            <a href="doctors_list.php?branch=<?= $selected_branch_id ?>" class="filter-btn" style="border-color: var(--danger); color: var(--danger);">
                <i class="fas fa-times"></i> Clear Filters
            </a>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- DOCTORS LIST -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i>
                Doctors List
                <span class="text-sm font-normal text-gray-400">(<?= $total_doctors ?> doctors)</span>
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table w-full">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="min-width: 180px;">Doctor</th>
                        <th style="min-width: 120px;">Specialty</th>
                        <th style="min-width: 120px;">Branch</th>
                        <th style="min-width: 80px;">Patients</th>
                        <th style="min-width: 80px;">Visits</th>
                        <th style="min-width: 100px;">Status</th>
                        <th style="min-width: 250px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($doctors) > 0): ?>
                        <?php $i = $offset + 1; foreach ($doctors as $doctor): ?>
                            <?php 
                                // Get avatar color based on name
                                $colors = ['blue', 'green', 'purple', 'orange', 'red', 'pink', 'teal'];
                                $color_index = abs(crc32($doctor['full_name'])) % count($colors);
                                $avatar_color = $colors[$color_index];
                                $initials = implode('', array_map(function($name) {
                                    return strtoupper($name[0]);
                                }, explode(' ', trim($doctor['full_name']))));
                                
                                $is_online = $doctor['is_online'] == 1;
                            ?>
                            <tr>
                                <td class="font-bold text-blue-600 dark:text-blue-400"><?= $i++ ?></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="doctor-avatar-wrapper">
                                            <div class="doctor-avatar <?= $avatar_color ?>">
                                                <?= substr($initials, 0, 2) ?>
                                                <span class="online-indicator <?= $is_online ? 'online' : 'offline' ?>"></span>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-sm"><?= htmlspecialchars($doctor['full_name']) ?></p>
                                            <p class="text-xs text-gray-400"><?= htmlspecialchars($doctor['email']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= htmlspecialchars($doctor['specialty'] ?? 'General') ?></span>
                                </td>
                                <td><?= htmlspecialchars($doctor['branch_name'] ?? 'N/A') ?></td>
                                <td class="text-center">
                                    <span class="badge badge-blue"><?= $doctor['total_patients'] ?? 0 ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-green"><?= $doctor['total_visits'] ?? 0 ?></span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $is_online ? 'online' : 'offline' ?>">
                                        <i class="fas fa-circle text-[8px]"></i>
                                        <?= $is_online ? 'Online' : 'Offline' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- View Doctor Details Button - BLUE -->
                                        <a href="doctor_details.php?id=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action btn-view" title="View Doctor Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <!-- View Doctor Dashboard Button - PURPLE -->
                                        <a href="view_doctor.php?id=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action btn-dashboard" title="View Doctor Dashboard">
                                            <i class="fas fa-chart-bar"></i> Dashboard
                                        </a>
                                        
                                        <!-- Toggle Online/Offline Button -->
                                        <?php if ($is_online): ?>
                                            <a href="?toggle=<?= $doctor['id'] ?>&page=<?= $page ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" 
                                               class="btn-action btn-toggle-offline" 
                                               onclick="return confirm('Are you sure you want to set Dr. <?= htmlspecialchars($doctor['full_name']) ?> to OFFLINE?')" 
                                               title="Set Offline">
                                                <i class="fas fa-power-off"></i> Offline
                                            </a>
                                        <?php else: ?>
                                            <a href="?toggle=<?= $doctor['id'] ?>&page=<?= $page ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" 
                                               class="btn-action btn-toggle-online" 
                                               onclick="return confirm('Are you sure you want to set Dr. <?= htmlspecialchars($doctor['full_name']) ?> to ONLINE?')" 
                                               title="Set Online">
                                                <i class="fas fa-power-off"></i> Online
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Edit Button REMOVED -->
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400">
                                <i class="fas fa-user-md text-4xl block mb-3" style="color: var(--primary);"></i>
                                <p class="text-lg font-medium text-gray-700 dark:text-gray-300">
                                    <?= !empty($search) || !empty($status_filter) ? 'No doctors found matching your filters' : 'No doctors found' ?>
                                </p>
                                <p class="text-sm">
                                    <?= !empty($search) || !empty($status_filter) ? 'Try changing your search or filter criteria' : 'Contact administrator to add a doctor' ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ================================================================ -->
        <!-- PAGINATION -->
        <!-- ================================================================ -->
        <?php if ($total_pages > 1): ?>
            <div class="flex flex-wrap justify-between items-center gap-3 mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Showing <?= $offset + 1 ?> - <?= min($offset + $per_page, $total_doctors) ?> of <?= $total_doctors ?> doctors
                </div>
                
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    <?php endif; ?>
                    
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="?page=<?= $p ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" 
                           class="page-link <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            Doctors Management
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
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
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('page');
        window.location.href = url.toString();
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

    console.log('%c🏥 Braick Dispensary - Doctors Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#059669;');
    console.log('%c👨‍⚕️ Total Doctors: <?= $total_all ?>', 'font-size:13px; color:#059669;');
    console.log('%c🟢 Online: <?= $online_count ?> | 🔴 Offline: <?= $offline_count ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Showing: <?= count($doctors) ?> doctors', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔒 Login protection: Active', 'font-size:13px; color:#34D399;');
    console.log('%c🔵 View Button: Blue', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🟣 Dashboard Button: Purple', 'font-size:13px; color:#7C3AED;');
    console.log('%c🟢 Online Button: Green', 'font-size:13px; color:#059669;');
    console.log('%c🔴 Offline Button: Red', 'font-size:13px; color:#DC2626;');
    console.log('%c🚫 Edit & Add Doctor buttons REMOVED', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>