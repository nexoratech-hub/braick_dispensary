<?php
// ================================================================
// FILE: frontend/pages/admin/view_branch.php
// ADMIN - VIEW BRANCH DETAILS
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
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// GET DATABASE CONNECTION
// ================================================================
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET BRANCH ID
// ================================================================
$branch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($branch_id <= 0) {
    header('Location: branches.php?branch=all');
    exit;
}

// ================================================================
// GET BRANCH DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        b.*,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND status = 'active') as total_employees,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'doctor' AND status = 'active') as total_doctors,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy' AND status = 'active') as total_pharmacy,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'reception' AND status = 'active') as total_reception,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory' AND status = 'active') as total_laboratory,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'cashier' AND status = 'active') as total_cashiers,
        (SELECT COUNT(*) FROM patients WHERE branch_id = b.id) as total_patients,
        (SELECT COUNT(*) FROM visits WHERE branch_id = b.id) as total_visits,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id) as total_prescriptions,
        (SELECT COUNT(*) FROM otc_sales WHERE branch_id = b.id) as total_otc,
        (SELECT COALESCE(SUM(total_amount), 0) FROM patient_bills WHERE branch_id = b.id AND status = 'paid') as total_revenue,
        (SELECT COALESCE(SUM(total_amount), 0) FROM patient_bills WHERE branch_id = b.id AND status = 'pending') as pending_revenue,
        (SELECT COUNT(*) FROM patient_bills WHERE branch_id = b.id AND status = 'pending') as pending_bills,
        (SELECT COUNT(*) FROM patient_bills WHERE branch_id = b.id AND status = 'paid') as paid_bills,
        (SELECT COUNT(*) FROM patient_bills WHERE branch_id = b.id AND status = 'cancelled') as cancelled_bills,
        (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE branch_id = b.id AND status = 'paid') as total_expenses,
        (SELECT COUNT(*) FROM appointments WHERE branch_id = b.id AND status = 'scheduled') as scheduled_appointments,
        (SELECT COUNT(*) FROM appointments WHERE branch_id = b.id AND status = 'confirmed') as confirmed_appointments,
        (SELECT COUNT(*) FROM appointments WHERE branch_id = b.id AND status = 'completed') as completed_appointments
    FROM branches b
    WHERE b.id = ?
");
$stmt->execute([$branch_id]);
$branch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$branch) {
    header('Location: branches.php?branch=all');
    exit;
}

// ================================================================
// GET RECENT PATIENTS
// ================================================================
$stmt = $db->prepare("
    SELECT id, full_name, patient_id, phone, created_at 
    FROM patients 
    WHERE branch_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$branch_id]);
$recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT EMPLOYEES
// ================================================================
$stmt = $db->prepare("
    SELECT id, full_name, username, role, status, created_at 
    FROM users 
    WHERE branch_id = ? AND role != 'admin'
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$branch_id]);
$recent_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT PRESCRIPTIONS
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, pat.full_name as patient_name 
    FROM prescriptions p
    JOIN patients pat ON p.patient_id = pat.id
    WHERE p.branch_id = ? 
    ORDER BY p.created_at DESC 
    LIMIT 10
");
$stmt->execute([$branch_id]);
$recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT OTC SALES
// ================================================================
$stmt = $db->prepare("
    SELECT * FROM otc_sales 
    WHERE branch_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$branch_id]);
$recent_otc = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
$page_title = 'View Branch - ' . htmlspecialchars($branch['name']);
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($branch['name']) ?> - Branch Details</title>
    
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
        
        .branch-badge {
            display: inline-block;
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
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
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
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: var(--radius);
            padding: 18px 20px;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .stat-card .stat-icon.blue { background: var(--primary-bg); color: var(--primary); }
        .stat-card .stat-icon.green { background: var(--success-bg); color: var(--success); }
        .stat-card .stat-icon.purple { background: var(--purple-bg); color: var(--purple); }
        .stat-card .stat-icon.orange { background: var(--warning-bg); color: var(--warning); }
        .stat-card .stat-icon.red { background: var(--danger-bg); color: var(--danger); }
        .stat-card .stat-icon.teal { background: var(--teal-bg); color: var(--teal); }
        
        .stat-card .stat-number {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        /* ================================================================
           DETAIL CARD
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .detail-card .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-card .card-title i {
            color: var(--primary);
        }
        
        .detail-card .card-title .badge-count {
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.85rem;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .detail-value {
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .detail-value .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .detail-value .status-badge.active {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .detail-value .status-badge.inactive {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        /* ================================================================
           LIST ITEMS
           ================================================================ */
        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s ease;
            border-radius: 6px;
        }
        
        .list-item:hover {
            background: var(--primary-bg);
        }
        
        .list-item:last-child {
            border-bottom: none;
        }
        
        .list-item .item-info .name {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        
        .list-item .item-info .sub {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .list-item .item-info .sub i {
            margin-right: 4px;
        }
        
        .list-item .item-actions .btn-sm {
            padding: 2px 10px;
            font-size: 0.6rem;
            border-radius: 6px;
            text-decoration: none;
            background: var(--primary);
            color: white;
            transition: all 0.3s;
        }
        
        .list-item .item-actions .btn-sm:hover {
            background: var(--primary-dark);
        }
        
        /* ================================================================
           SCROLL CONTAINER
           ================================================================ */
        .scroll-container {
            max-height: 280px;
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
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2rem;
            color: var(--border-color);
            display: block;
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
            font-weight: 700;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-card .stat-number { font-size: 1.2rem; }
            .detail-card { padding: 16px; }
            .detail-row { flex-direction: column; gap: 2px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 12px 14px; }
            .stat-card .stat-number { font-size: 1rem; }
            .stat-card .stat-icon { width: 32px; height: 32px; font-size: 0.9rem; }
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
        <span class="branch-badge">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch['name']) ?>
        </span>
        
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
                <i class="fas fa-store-alt"></i>
                <?= htmlspecialchars($branch['name']) ?>
                <span class="role-badge-display">BRANCH</span>
                <?php if ($branch['status'] === 'active'): ?>
                    <span class="branch-tag" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-circle text-[6px]"></i> Active
                    </span>
                <?php else: ?>
                    <span class="branch-tag" style="background:rgba(248,113,113,0.2);border-color:rgba(248,113,113,0.3);color:#F87171;">
                        <i class="fas fa-circle text-[6px]"></i> Inactive
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-map-marker-alt"></i>
                <strong><?= htmlspecialchars($branch['location'] ?? 'N/A') ?></strong>
                <span class="branch-tag" style="background:rgba(255,255,255,0.1);">
                    <i class="fas fa-phone"></i> <?= htmlspecialchars($branch['phone'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag" style="background:rgba(255,255,255,0.1);">
                    <i class="fas fa-envelope"></i> <?= htmlspecialchars($branch['email'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="edit_branch.php?id=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit Branch
            </a>
            <a href="branches.php?branch=all" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        
        <!-- Total Employees -->
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div>
                    <p class="stat-number"><?= number_format($branch['total_employees'] ?? 0) ?></p>
                    <p class="stat-label">Total Employees</p>
                </div>
            </div>
        </div>
        
        <!-- Total Patients -->
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon green"><i class="fas fa-user-injured"></i></div>
                <div>
                    <p class="stat-number"><?= number_format($branch['total_patients'] ?? 0) ?></p>
                    <p class="stat-label">Total Patients</p>
                </div>
            </div>
        </div>
        
        <!-- Total Visits -->
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon purple"><i class="fas fa-clinic-medical"></i></div>
                <div>
                    <p class="stat-number"><?= number_format($branch['total_visits'] ?? 0) ?></p>
                    <p class="stat-label">Total Visits</p>
                </div>
            </div>
        </div>
        
        <!-- Total Revenue -->
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <p class="stat-number">TSh <?= number_format($branch['total_revenue'] ?? 0, 0) ?></p>
                    <p class="stat-label">Total Revenue</p>
                </div>
            </div>
        </div>
        
        <!-- Total Prescriptions -->
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon teal"><i class="fas fa-prescription"></i></div>
                <div>
                    <p class="stat-number"><?= number_format($branch['total_prescriptions'] ?? 0) ?></p>
                    <p class="stat-label">Prescriptions</p>
                </div>
            </div>
        </div>
        
        <!-- Total OTC -->
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon orange"><i class="fas fa-shopping-cart"></i></div>
                <div>
                    <p class="stat-number"><?= number_format($branch['total_otc'] ?? 0) ?></p>
                    <p class="stat-label">OTC Sales</p>
                </div>
            </div>
        </div>
        
        <!-- Total Expenses -->
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon red"><i class="fas fa-arrow-up"></i></div>
                <div>
                    <p class="stat-number">TSh <?= number_format($branch['total_expenses'] ?? 0, 0) ?></p>
                    <p class="stat-label">Total Expenses</p>
                </div>
            </div>
        </div>
        
        <!-- Net Profit -->
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <div class="stat-icon blue"><i class="fas fa-chart-line"></i></div>
                <div>
                    <p class="stat-number">TSh <?= number_format(($branch['total_revenue'] ?? 0) - ($branch['total_expenses'] ?? 0), 0) ?></p>
                    <p class="stat-label">Net Profit</p>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- DETAILS & LISTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        <!-- Branch Information -->
        <div class="detail-card lg:col-span-1 animate-fade-in-up" style="animation-delay:0.05s;">
            <div class="card-title">
                <i class="fas fa-info-circle"></i>
                Branch Information
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Branch Name</span>
                <span class="detail-value"><strong><?= htmlspecialchars($branch['name']) ?></strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Location</span>
                <span class="detail-value"><?= htmlspecialchars($branch['location'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?= htmlspecialchars($branch['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?= htmlspecialchars($branch['email'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="status-badge <?= $branch['status'] === 'active' ? 'active' : 'inactive' ?>">
                        <?= ucfirst($branch['status'] ?? 'Active') ?>
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Created</span>
                <span class="detail-value"><?= date('F d, Y', strtotime($branch['created_at'] ?? 'now')) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Last Updated</span>
                <span class="detail-value"><?= date('F d, Y', strtotime($branch['updated_at'] ?? 'now')) ?></span>
            </div>
        </div>
        
        <!-- Staff Breakdown -->
        <div class="detail-card lg:col-span-1 animate-fade-in-up" style="animation-delay:0.1s;">
            <div class="card-title">
                <i class="fas fa-user-tie"></i>
                Staff Breakdown
                <span class="badge-count"><?= $branch['total_employees'] ?? 0 ?> total</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">👨‍⚕️ Doctors</span>
                <span class="detail-value"><strong><?= number_format($branch['total_doctors'] ?? 0) ?></strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">💊 Pharmacy</span>
                <span class="detail-value"><strong><?= number_format($branch['total_pharmacy'] ?? 0) ?></strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">💉 Laboratory</span>
                <span class="detail-value"><strong><?= number_format($branch['total_laboratory'] ?? 0) ?></strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">📋 Reception</span>
                <span class="detail-value"><strong><?= number_format($branch['total_reception'] ?? 0) ?></strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">💰 Cashiers</span>
                <span class="detail-value"><strong><?= number_format($branch['total_cashiers'] ?? 0) ?></strong></span>
            </div>
            
            <!-- Bill Summary -->
            <div style="margin-top:12px;padding-top:12px;border-top:2px solid var(--border-color);">
                <div class="detail-row">
                    <span class="detail-label">Paid Bills</span>
                    <span class="detail-value" style="color:var(--success);"><?= number_format($branch['paid_bills'] ?? 0) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Pending Bills</span>
                    <span class="detail-value" style="color:var(--warning);"><?= number_format($branch['pending_bills'] ?? 0) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Cancelled Bills</span>
                    <span class="detail-value" style="color:var(--danger);"><?= number_format($branch['cancelled_bills'] ?? 0) ?></span>
                </div>
            </div>
            
            <!-- Appointments Summary -->
            <div style="margin-top:12px;padding-top:12px;border-top:2px solid var(--border-color);">
                <div class="detail-row">
                    <span class="detail-label">📅 Scheduled</span>
                    <span class="detail-value"><?= number_format($branch['scheduled_appointments'] ?? 0) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">✅ Confirmed</span>
                    <span class="detail-value" style="color:var(--success);"><?= number_format($branch['confirmed_appointments'] ?? 0) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">✔️ Completed</span>
                    <span class="detail-value" style="color:var(--primary);"><?= number_format($branch['completed_appointments'] ?? 0) ?></span>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="detail-card lg:col-span-1 animate-fade-in-up" style="animation-delay:0.15s;">
            <div class="card-title">
                <i class="fas fa-chart-bar"></i>
                Quick Stats
            </div>
            
            <div style="margin-bottom:12px;padding:12px;background:var(--primary-bg);border-radius:var(--radius);">
                <p class="detail-label">Total Revenue</p>
                <p class="detail-value" style="font-size:1.4rem;color:var(--primary);">
                    TSh <?= number_format($branch['total_revenue'] ?? 0, 0) ?>
                </p>
            </div>
            
            <div style="margin-bottom:12px;padding:12px;background:var(--danger-bg);border-radius:var(--radius);">
                <p class="detail-label">Total Expenses</p>
                <p class="detail-value" style="font-size:1.4rem;color:var(--danger);">
                    TSh <?= number_format($branch['total_expenses'] ?? 0, 0) ?>
                </p>
            </div>
            
            <div style="padding:12px;background:var(--success-bg);border-radius:var(--radius);">
                <p class="detail-label">Net Profit</p>
                <p class="detail-value" style="font-size:1.4rem;color:var(--success);">
                    TSh <?= number_format(($branch['total_revenue'] ?? 0) - ($branch['total_expenses'] ?? 0), 0) ?>
                </p>
            </div>
            
            <div style="margin-top:12px;padding:12px;background:var(--warning-bg);border-radius:var(--radius);">
                <p class="detail-label">Pending Revenue</p>
                <p class="detail-value" style="font-size:1.2rem;color:var(--warning);">
                    TSh <?= number_format($branch['pending_revenue'] ?? 0, 0) ?>
                </p>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- LISTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5">
        
        <!-- Recent Patients -->
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.2s;">
            <div class="card-title">
                <i class="fas fa-user-injured"></i>
                Recent Patients
                <span class="badge-count"><?= count($recent_patients) ?></span>
            </div>
            
            <div class="scroll-container">
                <?php if (count($recent_patients) > 0): ?>
                    <?php foreach ($recent_patients as $patient): ?>
                        <div class="list-item">
                            <div class="item-info">
                                <div class="name"><?= htmlspecialchars($patient['full_name']) ?></div>
                                <div class="sub">
                                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id']) ?>
                                    <i class="fas fa-phone ml-2"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="view_patient.php?id=<?= $patient['id'] ?>" class="btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-injured"></i>
                        <p>No patients registered</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Employees -->
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.25s;">
            <div class="card-title">
                <i class="fas fa-user-tie"></i>
                Recent Employees
                <span class="badge-count"><?= count($recent_employees) ?></span>
            </div>
            
            <div class="scroll-container">
                <?php if (count($recent_employees) > 0): ?>
                    <?php foreach ($recent_employees as $employee): ?>
                        <div class="list-item">
                            <div class="item-info">
                                <div class="name"><?= htmlspecialchars($employee['full_name']) ?></div>
                                <div class="sub">
                                    <i class="fas fa-user-tag"></i> <?= ucfirst($employee['role']) ?>
                                    <span class="status-badge <?= $employee['status'] === 'active' ? 'active' : 'inactive' ?>" style="font-size:0.5rem;padding:1px 8px;margin-left:4px;">
                                        <?= ucfirst($employee['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="view_employee.php?id=<?= $employee['id'] ?>" class="btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-tie"></i>
                        <p>No employees assigned</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Prescriptions -->
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.3s;">
            <div class="card-title">
                <i class="fas fa-prescription"></i>
                Recent Prescriptions
                <span class="badge-count"><?= count($recent_prescriptions) ?></span>
            </div>
            
            <div class="scroll-container">
                <?php if (count($recent_prescriptions) > 0): ?>
                    <?php foreach ($recent_prescriptions as $pres): ?>
                        <div class="list-item">
                            <div class="item-info">
                                <div class="name"><?= htmlspecialchars($pres['patient_name']) ?></div>
                                <div class="sub">
                                    <i class="fas fa-pills"></i> <?= htmlspecialchars($pres['medication'] ?? 'N/A') ?>
                                    <span class="status-badge <?= $pres['status'] === 'dispensed' ? 'active' : ($pres['status'] === 'pending' ? 'active' : 'inactive') ?>" style="font-size:0.5rem;padding:1px 8px;margin-left:4px;">
                                        <?= ucfirst($pres['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="view_prescription.php?id=<?= $pres['id'] ?>" class="btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-prescription"></i>
                        <p>No prescriptions</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent OTC Sales -->
        <div class="detail-card animate-fade-in-up" style="animation-delay:0.35s;">
            <div class="card-title">
                <i class="fas fa-shopping-cart"></i>
                Recent OTC Sales
                <span class="badge-count"><?= count($recent_otc) ?></span>
            </div>
            
            <div class="scroll-container">
                <?php if (count($recent_otc) > 0): ?>
                    <?php foreach ($recent_otc as $sale): ?>
                        <div class="list-item">
                            <div class="item-info">
                                <div class="name"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></div>
                                <div class="sub">
                                    <i class="fas fa-receipt"></i> <?= htmlspecialchars($sale['sale_number']) ?>
                                    <i class="fas fa-money-bill-wave ml-2"></i> TSh <?= number_format($sale['net_amount'] ?? 0, 0) ?>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="view_otc_sale.php?id=<?= $sale['id'] ?>" class="btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <p>No OTC sales</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5">
        <a href="add_employee.php?branch=<?= $branch_id ?>" class="quick-action" style="padding:16px;border-radius:12px;text-align:center;transition:all 0.3s ease;cursor:pointer;text-decoration:none;display:block;border:1px solid var(--border-color);background:var(--bg-card);">
            <span class="icon" style="font-size:1.6rem;display:block;margin-bottom:6px;color:#0B5ED7;">👤</span>
            <span class="label" style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">Add Employee</span>
        </a>
        
        <a href="edit_branch.php?id=<?= $branch_id ?>" class="quick-action" style="padding:16px;border-radius:12px;text-align:center;transition:all 0.3s ease;cursor:pointer;text-decoration:none;display:block;border:1px solid var(--border-color);background:var(--bg-card);">
            <span class="icon" style="font-size:1.6rem;display:block;margin-bottom:6px;color:#059669;">✏️</span>
            <span class="label" style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">Edit Branch</span>
        </a>
        
        <a href="branch_reports.php?id=<?= $branch_id ?>" class="quick-action" style="padding:16px;border-radius:12px;text-align:center;transition:all 0.3s ease;cursor:pointer;text-decoration:none;display:block;border:1px solid var(--border-color);background:var(--bg-card);">
            <span class="icon" style="font-size:1.6rem;display:block;margin-bottom:6px;color:#7C3AED;">📊</span>
            <span class="label" style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">Reports</span>
        </a>
        
        <a href="branches.php?branch=all" class="quick-action" style="padding:16px;border-radius:12px;text-align:center;transition:all 0.3s ease;cursor:pointer;text-decoration:none;display:block;border:1px solid var(--border-color);background:var(--bg-card);">
            <span class="icon" style="font-size:1.6rem;display:block;margin-bottom:6px;color:#D97706;">🏢</span>
            <span class="label" style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">All Branches</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            <?= htmlspecialchars($branch['name']) ?> - Details
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
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

    console.log('%c🏢 Braick Dispensary - View Branch', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch['name']) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📍 Location: <?= htmlspecialchars($branch['location'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👥 Employees: <?= number_format($branch['total_employees'] ?? 0) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 Patients: <?= number_format($branch['total_patients'] ?? 0) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Revenue: TSh <?= number_format($branch['total_revenue'] ?? 0, 0) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Expenses: TSh <?= number_format($branch['total_expenses'] ?? 0, 0) ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c📈 Profit: TSh <?= number_format(($branch['total_revenue'] ?? 0) - ($branch['total_expenses'] ?? 0), 0) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔒 Login protection: Active', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>