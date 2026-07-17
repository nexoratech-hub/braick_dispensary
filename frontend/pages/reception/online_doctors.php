<?php
// ================================================================
// FILE: frontend/pages/reception/online_doctors.php
// RECEPTION - ONLINE DOCTORS LIST (BRANCH FILTERED)
// SHOWS BOTH ONLINE AND OFFLINE DOCTORS
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

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$search = $_GET['search'] ?? '';
$message = '';
$message_type = '';

try {
    $db = getDB();
    
    // ================================================================
    // GET ALL DOCTORS IN THIS BRANCH
    // ================================================================
    $query = "
        SELECT u.*, b.name as branch_name 
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.role = 'doctor' 
        AND u.status = 'active' 
        AND u.branch_id = ?
        ORDER BY u.is_online DESC, u.full_name
    ";
    $params = [$selected_branch_id];
    
    if (!empty($search)) {
        $query .= " AND (u.full_name LIKE ? OR u.specialty LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $doctors = $stmt->fetchAll();
    
    // ================================================================
    // COUNT ONLINE AND OFFLINE DOCTORS
    // ================================================================
    $online_count = 0;
    $offline_count = 0;
    foreach ($doctors as $doc) {
        if ($doc['is_online'] ?? 0) {
            $online_count++;
        } else {
            $offline_count++;
        }
    }
    $total_doctors = count($doctors);
    
} catch (Exception $e) {
    $doctors = [];
    $online_count = 0;
    $offline_count = 0;
    $total_doctors = 0;
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
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
    <title>Online Doctors - Braick Dispensary</title>
    
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
           DOCTOR CARD
           ================================================================ */
        .doctor-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px 22px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 12px;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        
        .doctor-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .doctor-card.online {
            border-left: 4px solid #059669;
        }
        
        .doctor-card.offline {
            border-left: 4px solid #94A3B8;
            opacity: 0.85;
        }
        
        .doctor-card .status-indicator {
            position: absolute;
            top: 12px;
            right: 16px;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 12px;
        }
        
        .doctor-card .status-indicator.online {
            background: #D1FAE5;
            color: #059669;
        }
        
        .doctor-card .status-indicator.offline {
            background: #F1F5F9;
            color: #94A3B8;
        }
        
        [data-theme="dark"] .doctor-card .status-indicator.online {
            background: #1A3A2A;
            color: #34D399;
        }
        
        [data-theme="dark"] .doctor-card .status-indicator.offline {
            background: #1E293B;
            color: #64748B;
        }
        
        .doctor-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .doctor-card .doctor-info {
            flex: 1;
        }
        
        .doctor-card .doctor-name {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .doctor-card .doctor-specialty {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .doctor-card .doctor-branch {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .doctor-card .doctor-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 4px;
        }
        
        .doctor-card .doctor-meta span {
            font-size: 0.65rem;
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--bg-body);
            padding: 2px 10px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        
        .online-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            animation: pulse-dot 1.5s infinite;
        }
        
        .online-dot.online {
            background: #059669;
        }
        
        .online-dot.offline {
            background: #94A3B8;
            animation: none;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm { 
            padding: 4px 10px; 
            font-size: 0.7rem; 
            border-radius: 6px; 
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
           STATS CARD
           ================================================================ */
        .stat-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-card .stat-number.green {
            color: var(--success);
        }
        
        .stat-card .stat-number.gray {
            color: #94A3B8;
        }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .stat-card .stat-icon {
            font-size: 1.4rem;
            margin-bottom: 4px;
        }
        
        .stat-card .stat-update {
            font-size: 0.55rem;
            color: var(--text-secondary);
            opacity: 0.6;
            margin-top: 4px;
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
            .doctor-card { flex-wrap: wrap; gap: 12px; }
            .doctor-card .doctor-avatar { width: 44px; height: 44px; font-size: 1rem; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .doctor-card { padding: 12px 14px; }
            .doctor-card .doctor-name { font-size: 0.85rem; }
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
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
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
        
        /* Doctor status update flash */
        .doctor-card.status-updated {
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
            <input type="text" id="searchInput" placeholder="Search doctors by name, specialty or email..." value="<?= htmlspecialchars($search) ?>">
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

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md"></i>
                Online Doctors
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">RECEPTION</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                View all doctors in <strong><?= htmlspecialchars($branch_name) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-circle" style="color:#34D399;font-size:0.6rem;"></i>
                    <span id="onlineCountDisplay"><?= $online_count ?></span> Online
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-circle" style="color:#94A3B8;font-size:0.6rem;"></i>
                    <span id="offlineCountDisplay"><?= $offline_count ?></span> Offline
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-user-md"></i>
                    <span id="totalCountDisplay"><?= $total_doctors ?></span> Total
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>" style="max-width:1000px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div class="alert-content"><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- Search Results Info -->
    <?php if (!empty($search)): ?>
        <div class="mb-4" style="max-width:1000px;margin:0 auto 16px;">
            <span class="inline-flex bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs border border-yellow-200">
                <i class="fas fa-search mr-1"></i> Results for: "<?= htmlspecialchars($search) ?>"
                <a href="online_doctors.php" class="ml-2 text-yellow-600 hover:text-yellow-800">
                    <i class="fas fa-times"></i>
                </a>
            </span>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-5" style="max-width:1000px;margin:0 auto 16px;">
        <div class="stat-card">
            <div class="stat-icon">🟢</div>
            <p class="stat-number green" id="onlineStat"><?= $online_count ?></p>
            <p class="stat-label">Online Doctors</p>
            <p class="stat-update" id="onlineStatUpdate">Updated now</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⚪</div>
            <p class="stat-number gray" id="offlineStat"><?= $offline_count ?></p>
            <p class="stat-label">Offline Doctors</p>
            <p class="stat-update" id="offlineStatUpdate">Updated now</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👨‍⚕️</div>
            <p class="stat-number" id="totalStat"><?= $total_doctors ?></p>
            <p class="stat-label">Total Doctors</p>
            <p class="stat-update" id="totalStatUpdate">Updated now</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <p class="stat-number" style="color:#D97706;" id="availabilityStat">
                <?= $total_doctors > 0 ? round(($online_count / $total_doctors) * 100) : 0 ?>%
            </p>
            <p class="stat-label">Availability</p>
            <p class="stat-update" id="availabilityStatUpdate">Updated now</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DOCTORS LIST - AUTO UPDATED -->
    <!-- ================================================================ -->
    <div class="card" style="max-width:1000px;margin:0 auto;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Doctors List
                <span class="text-sm font-normal text-gray-400" id="doctorsCount">(<?= $total_doctors ?> doctors)</span>
            </h3>
            <span class="text-xs text-gray-400" id="lastUpdateTime">Last updated: <?= date('h:i:s A') ?></span>
        </div>
        
        <div id="doctorsListContainer">
            <?php if (count($doctors) > 0): ?>
                <?php foreach ($doctors as $doctor): 
                    $is_online = $doctor['is_online'] ?? 0;
                    $color = getUserColor($doctor['full_name']);
                ?>
                    <div class="doctor-card <?= $is_online ? 'online' : 'offline' ?>" data-doctor-id="<?= $doctor['id'] ?>">
                        <span class="status-indicator <?= $is_online ? 'online' : 'offline' ?>">
                            <?= $is_online ? '🟢 Online' : '⚪ Offline' ?>
                        </span>
                        
                        <div class="doctor-avatar" style="background: <?= $color ?>;">
                            <?= strtoupper(substr($doctor['full_name'], 0, 2)) ?>
                        </div>
                        
                        <div class="doctor-info">
                            <div class="doctor-name">
                                Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                            </div>
                            <div class="doctor-specialty">
                                <i class="fas fa-stethoscope mr-1"></i>
                                <?= htmlspecialchars($doctor['specialty'] ?? 'General Practitioner') ?>
                            </div>
                            <div class="doctor-branch">
                                <i class="fas fa-store-alt mr-1"></i>
                                <?= htmlspecialchars($doctor['branch_name'] ?? 'Not Assigned') ?>
                            </div>
                            <div class="doctor-meta">
                                <span>
                                    <i class="fas fa-envelope mr-1"></i>
                                    <?= htmlspecialchars($doctor['email'] ?? 'N/A') ?>
                                </span>
                                <?php if (!empty($doctor['phone'])): ?>
                                    <span>
                                        <i class="fas fa-phone mr-1"></i>
                                        <?= htmlspecialchars($doctor['phone']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($doctor['last_online'])): ?>
                                    <span>
                                        <i class="fas fa-clock mr-1"></i>
                                        Last seen: <?= date('M d, Y h:i A', strtotime($doctor['last_online'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="flex flex-col gap-2" style="flex-shrink:0;">
                            <?php if ($is_online): ?>
                                <a href="assign_doctor.php?doctor_id=<?= $doctor['id'] ?>" class="btn btn-success btn-sm" title="Assign this doctor">
                                    <i class="fas fa-user-md"></i> Assign
                                </a>
                            <?php endif; ?>
                            <a href="view_doctor.php?id=<?= $doctor['id'] ?>" class="btn btn-primary btn-sm" title="View Doctor Profile">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-user-md text-4xl block mb-3"></i>
                    <?php if (!empty($search)): ?>
                        <p class="text-lg">No doctors found matching "<strong><?= htmlspecialchars($search) ?></strong>"</p>
                    <?php else: ?>
                        <p class="text-lg">No doctors available in <?= htmlspecialchars($branch_name) ?></p>
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
            Online Doctors
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
        document.getElementById('lastUpdateTime').textContent = 'Last updated: ' + timeStr;
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
            window.location.href = 'online_doctors.php?search=' + encodeURIComponent(query);
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
            showToast('✅ Refreshed', 'Page data updated manually', 'success');
        }, 2000);
    }

    // ================================================================
    // GET USER COLOR - JavaScript version
    // ================================================================
    function getUserColor(name) {
        var colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777', '#4F46E5', '#E11D48'];
        var index = 0;
        for (var i = 0; i < name.length; i++) {
            index = (index + name.charCodeAt(i)) % colors.length;
        }
        return colors[index];
    }

    // ================================================================
    // ESCAPE HTML
    // ================================================================
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ================================================================
    // AUTO-UPDATE DOCTORS LIST (3 SECONDS)
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var lastDoctorData = '';

    function updateDoctorsList() {
        if (isUpdating) return;
        isUpdating = true;
        
        var branchId = <?= json_encode($selected_branch_id) ?>;
        var searchQuery = <?= json_encode($search) ?>;
        
        var url = '/dispensary_system/frontend/api/get_online_doctors.php?branch_id=' + branchId + '&t=' + new Date().getTime();
        if (searchQuery) {
            url += '&search=' + encodeURIComponent(searchQuery);
        }
        
        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var doctors = data.doctors || [];
                    var onlineCount = data.online_count || 0;
                    var offlineCount = data.offline_count || 0;
                    var totalDoctors = data.total_doctors || 0;
                    
                    // ================================================================
                    // UPDATE STATS
                    // ================================================================
                    document.getElementById('onlineStat').textContent = onlineCount;
                    document.getElementById('offlineStat').textContent = offlineCount;
                    document.getElementById('totalStat').textContent = totalDoctors;
                    document.getElementById('onlineCountDisplay').textContent = onlineCount;
                    document.getElementById('offlineCountDisplay').textContent = offlineCount;
                    document.getElementById('totalCountDisplay').textContent = totalDoctors;
                    document.getElementById('doctorsCount').textContent = '(' + totalDoctors + ' doctors)';
                    
                    var availability = totalDoctors > 0 ? Math.round((onlineCount / totalDoctors) * 100) : 0;
                    document.getElementById('availabilityStat').textContent = availability + '%';
                    
                    var now = new Date();
                    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    document.getElementById('onlineStatUpdate').textContent = 'Updated ' + timeStr;
                    document.getElementById('offlineStatUpdate').textContent = 'Updated ' + timeStr;
                    document.getElementById('totalStatUpdate').textContent = 'Updated ' + timeStr;
                    document.getElementById('availabilityStatUpdate').textContent = 'Updated ' + timeStr;
                    document.getElementById('updateBadge').innerHTML = '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + timeStr;
                    document.getElementById('lastUpdateTime').textContent = 'Last updated: ' + timeStr;
                    
                    // ================================================================
                    // CHECK IF DATA CHANGED
                    // ================================================================
                    var dataHash = JSON.stringify(doctors);
                    
                    if (dataHash !== lastDoctorData) {
                        lastDoctorData = dataHash;
                        
                        // ================================================================
                        // REBUILD DOCTORS LIST
                        // ================================================================
                        var container = document.getElementById('doctorsListContainer');
                        
                        if (doctors.length > 0) {
                            var html = '';
                            doctors.forEach(function(doc) {
                                var isOnline = doc.is_online == 1;
                                var color = getUserColor(doc.full_name);
                                var statusClass = isOnline ? 'online' : 'offline';
                                var statusText = isOnline ? '🟢 Online' : '⚪ Offline';
                                var specialty = doc.specialty || 'General Practitioner';
                                var branchName = doc.branch_name || 'Not Assigned';
                                var email = doc.email || 'N/A';
                                var phone = doc.phone || 'N/A';
                                var lastOnline = doc.last_online ? new Date(doc.last_online).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
                                
                                html += `
                                    <div class="doctor-card ${statusClass} status-updated" data-doctor-id="${doc.id}">
                                        <span class="status-indicator ${statusClass}">${statusText}</span>
                                        
                                        <div class="doctor-avatar" style="background: ${color};">
                                            ${escapeHtml(doc.full_name.substring(0, 2).toUpperCase())}
                                        </div>
                                        
                                        <div class="doctor-info">
                                            <div class="doctor-name">
                                                Dr. ${escapeHtml(doc.full_name)}
                                            </div>
                                            <div class="doctor-specialty">
                                                <i class="fas fa-stethoscope mr-1"></i>
                                                ${escapeHtml(specialty)}
                                            </div>
                                            <div class="doctor-branch">
                                                <i class="fas fa-store-alt mr-1"></i>
                                                ${escapeHtml(branchName)}
                                            </div>
                                            <div class="doctor-meta">
                                                <span>
                                                    <i class="fas fa-envelope mr-1"></i>
                                                    ${escapeHtml(email)}
                                                </span>
                                                ${phone ? `<span><i class="fas fa-phone mr-1"></i> ${escapeHtml(phone)}</span>` : ''}
                                                ${lastOnline ? `<span><i class="fas fa-clock mr-1"></i> Last seen: ${escapeHtml(lastOnline)}</span>` : ''}
                                            </div>
                                        </div>
                                        
                                        <div class="flex flex-col gap-2" style="flex-shrink:0;">
                                            ${isOnline ? `<a href="assign_doctor.php?doctor_id=${doc.id}" class="btn btn-success btn-sm" title="Assign this doctor"><i class="fas fa-user-md"></i> Assign</a>` : ''}
                                            <a href="view_doctor.php?id=${doc.id}" class="btn btn-primary btn-sm" title="View Doctor Profile"><i class="fas fa-eye"></i> View</a>
                                        </div>
                                    </div>
                                `;
                            });
                            container.innerHTML = html;
                            
                            // Remove flash after animation
                            setTimeout(function() {
                                document.querySelectorAll('.doctor-card.status-updated').forEach(function(el) {
                                    el.classList.remove('status-updated');
                                });
                            }, 700);
                            
                            // Show toast for update
                            showToast('🔄 Updated', onlineCount + ' online, ' + offlineCount + ' offline', 'info');
                        } else {
                            container.innerHTML = `
                                <div class="text-center py-8 text-gray-400">
                                    <i class="fas fa-user-md text-4xl block mb-3"></i>
                                    ${searchQuery ? `<p class="text-lg">No doctors found matching "<strong>${escapeHtml(searchQuery)}</strong>"</p>` : `<p class="text-lg">No doctors available</p>`}
                                </div>
                            `;
                        }
                    }
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Error updating doctors list:', error);
                isUpdating = false;
            });
    }

    // ================================================================
    // START AUTO-UPDATE
    // ================================================================
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        updateDoctorsList();
        updateInterval = setInterval(updateDoctorsList, 3000);
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

    console.log('%c👨‍⚕️ Braick - Online Doctors (Auto-Update Every 3s)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🟢 Online: <?= $online_count ?>', 'font-size:13px; color:#059669;');
    console.log('%c⚪ Offline: <?= $offline_count ?>', 'font-size:13px; color:#94A3B8;');
    console.log('%c👨‍⚕️ Total: <?= $total_doctors ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🔄 Auto-update every 3 seconds - No refresh needed', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Shows both Online and Offline doctors', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>