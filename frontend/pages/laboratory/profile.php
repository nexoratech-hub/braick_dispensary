<?php
// ================================================================
// FILE: frontend/pages/laboratory/profile.php
// LABORATORY - PROFILE
// FIXED: Login session - no default user bypass
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT LABORATORY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_role = $_SESSION['role'] ?? 'laboratory';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'lab.tech';
$user_email = $_SESSION['email'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// DATABASE CONNECTION
// ================================================================
$db = getDB();

// ================================================================
// GET USER STATISTICS
// ================================================================

// 1. Total Tests Completed by this technician
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_request_items lri
    JOIN lab_requests lr ON lri.request_id = lr.id
    WHERE lr.lab_technician_id = ? AND lri.status = 'completed'
");
$stmt->execute([$user_id]);
$total_tests = $stmt->fetch()['count'] ?? 0;

// 2. Total Requests Completed
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_requests 
    WHERE lab_technician_id = ? AND status = 'completed'
");
$stmt->execute([$user_id]);
$total_requests = $stmt->fetch()['count'] ?? 0;

// 3. Pending Requests Assigned
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_requests 
    WHERE lab_technician_id = ? AND status = 'in_progress'
");
$stmt->execute([$user_id]);
$pending_requests = $stmt->fetch()['count'] ?? 0;

// 4. Today's Tests
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_request_items lri
    JOIN lab_requests lr ON lri.request_id = lr.id
    WHERE lr.lab_technician_id = ? AND DATE(lri.completed_at) = ? AND lri.status = 'completed'
");
$stmt->execute([$user_id, $today]);
$today_tests = $stmt->fetch()['count'] ?? 0;

// 5. Recent Activity (Last 5 completed tests)
$stmt = $db->prepare("
    SELECT lri.*, lr.request_number, p.full_name as patient_name
    FROM lab_request_items lri
    JOIN lab_requests lr ON lri.request_id = lr.id
    JOIN patients p ON lr.patient_id = p.id
    WHERE lr.lab_technician_id = ? AND lri.status = 'completed'
    ORDER BY lri.completed_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_activity = $stmt->fetchAll();

// ================================================================
// GET BRANCHES FOR SELECTOR
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branches[] = $row;
}

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$pending_lab_tests = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_lab_tests = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $pending_lab_tests = 0;
}

$pending_prescriptions = 0;
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Laboratory</title>
    <link rel="icon" href="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" type="image/png">
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
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
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
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-dark: #0F172A;
            --border-color: #E2E8F0;
            --table-hover: #F1F5F9;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-dark: #F1F5F9;
            --border-color: #334155;
            --primary-bg: #1E3A5F;
            --primary-light: #6EA8FE;
            --gray-100: #1E293B;
            --gray-200: #334155;
            --gray-300: #475569;
            --table-hover: #1E293B;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        [data-theme="dark"] .main-content {
            background: var(--gray-900);
            color: var(--gray-100);
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
            font-size: 1.6rem;
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
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        .page-header .btn-edit {
            background: rgba(255,255,255,0.2);
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
        
        .page-header .btn-edit:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .page-header .btn-edit-outline {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.15);
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
        
        .page-header .btn-edit-outline:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
        
        /* ================================================================
           PROFILE HEADER
           ================================================================ */
        .profile-header {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 30px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 30px;
        }
        
        .profile-header .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
            flex-shrink: 0;
        }
        
        .profile-header .profile-avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            color: white;
            background: var(--primary);
            flex-shrink: 0;
            border: 4px solid var(--primary);
        }
        
        .profile-header .profile-info .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .profile-header .profile-info .profile-role {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .profile-header .profile-info .profile-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        
        .profile-header .profile-info .profile-badges .badge {
            font-size: 0.7rem;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .profile-header .profile-info .profile-badges .badge-blue {
            background: #E8F0FE;
            color: #0B5ED7;
        }
        
        .profile-header .profile-info .profile-badges .badge-green {
            background: #D1FAE5;
            color: #059669;
        }
        
        .profile-header .profile-info .profile-badges .badge-purple {
            background: #F3E8FF;
            color: #7C3AED;
        }
        
        [data-theme="dark"] .profile-header .profile-info .profile-badges .badge-blue {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        [data-theme="dark"] .profile-header .profile-info .profile-badges .badge-green {
            background: #1A3A2A;
            color: #34D399;
        }
        
        [data-theme="dark"] .profile-header .profile-info .profile-badges .badge-purple {
            background: #2A1A3A;
            color: #9B4DCA;
        }
        
        /* ================================================================
           STATS GRID
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-box {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 16px 18px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-box:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
        }
        
        .stat-box .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-box .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-box .stat-icon {
            font-size: 1.2rem;
            margin-bottom: 4px;
            color: var(--primary);
        }
        
        /* ================================================================
           INFO CARD
           ================================================================ */
        .info-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .info-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
        }
        
        .info-card .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-card .card-title i {
            color: var(--primary);
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-row .info-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .info-row .info-value {
            font-size: 0.85rem;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        /* ================================================================
           ACTIVITY ITEMS
           ================================================================ */
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s ease;
        }
        
        .activity-item:hover {
            background: var(--primary-bg);
            border-radius: 8px;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-bg);
            color: var(--primary);
            flex-shrink: 0;
        }
        
        [data-theme="dark"] .activity-item .activity-icon {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .activity-item .activity-info .activity-title {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .activity-item .activity-info .activity-desc {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .activity-item .activity-time {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin-left: auto;
            white-space: nowrap;
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
            margin-bottom: 10px;
        }
        
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
        
        [data-theme="dark"] .footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: var(--radius);
            z-index: 9999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
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
            .profile-header {
                flex-direction: column;
                text-align: center;
                padding: 20px;
            }
            .profile-header .profile-info .profile-badges {
                justify-content: center;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 2px;
            }
            .activity-item {
                flex-wrap: wrap;
            }
            .activity-item .activity-time {
                margin-left: 0;
                width: 100%;
                padding-left: 48px;
            }
            .main-content { padding: 10px; }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            .profile-header .profile-avatar,
            .profile-header .profile-avatar-placeholder {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
            .profile-header .profile-info .profile-name {
                font-size: 1.2rem;
            }
            .page-title { font-size: 1.1rem; }
            .info-card { padding: 14px 16px; }
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
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
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
                <i class="fas fa-user-circle mr-2"></i> My Profile
            </h1>
            <p class="page-subtitle">
                View and manage your profile information
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="edit_profile.php" class="btn-edit">
                <i class="fas fa-edit"></i> Edit Profile
            </a>
            <a href="dashboard.php" class="btn-edit-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PROFILE HEADER -->
    <!-- ================================================================ -->
    <div class="profile-header animate-fade-in-up">
        <?php if (!empty($profile_pic)): ?>
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="profile-avatar">
        <?php else: ?>
            <div class="profile-avatar-placeholder">
                <?= strtoupper(substr($user_full_name, 0, 1)) ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-info">
            <div class="profile-name"><?= htmlspecialchars($user_full_name) ?></div>
            <div class="profile-role">
                <i class="fas fa-flask mr-1"></i> Laboratory Technician
            </div>
            <div class="profile-badges">
                <span class="badge badge-blue">
                    <i class="fas fa-user mr-1"></i> <?= ucfirst($user_role) ?>
                </span>
                <span class="badge badge-green">
                    <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="badge badge-purple">
                    <i class="fas fa-flask mr-1"></i> <?= $total_tests ?> tests done
                </span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-flask"></i></div>
            <p class="stat-number"><?= $total_tests ?></p>
            <p class="stat-label">Total Tests Done</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-file-medical-alt"></i></div>
            <p class="stat-number"><?= $total_requests ?></p>
            <p class="stat-label">Requests Completed</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <p class="stat-number"><?= $pending_requests ?></p>
            <p class="stat-label">In Progress</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <p class="stat-number"><?= $today_tests ?></p>
            <p class="stat-label">Today's Tests</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PROFILE DETAILS & RECENT ACTIVITY -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- Personal Information -->
        <div class="info-card animate-fade-in-up">
            <div class="card-title">
                <i class="fas fa-user-circle"></i>
                Personal Information
            </div>
            <div class="info-row">
                <span class="info-label">Full Name</span>
                <span class="info-value"><?= htmlspecialchars($user_full_name) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Username</span>
                <span class="info-value"><?= htmlspecialchars($user_username) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value"><?= htmlspecialchars($user_email) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Phone</span>
                <span class="info-value"><?= htmlspecialchars($user_phone) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Role</span>
                <span class="info-value">
                    <span class="badge badge-blue" style="font-size:0.7rem;">
                        <?= ucfirst($user_role) ?>
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Branch</span>
                <span class="info-value"><?= htmlspecialchars($user_branch_name) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Member Since</span>
                <span class="info-value"><?= date('F d, Y') ?></span>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="info-card animate-fade-in-up">
            <div class="card-title">
                <i class="fas fa-history"></i>
                Recent Activity
            </div>
            
            <?php if (count($recent_activity) > 0): ?>
                <?php foreach ($recent_activity as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-flask"></i>
                        </div>
                        <div class="activity-info">
                            <div class="activity-title">
                                <?= htmlspecialchars($activity['test_name']) ?>
                            </div>
                            <div class="activity-desc">
                                <i class="fas fa-user mr-1"></i>
                                <?= htmlspecialchars($activity['patient_name'] ?? 'Unknown') ?>
                                <span class="mx-1">|</span>
                                <i class="fas fa-receipt mr-1"></i>
                                <?= htmlspecialchars($activity['request_number'] ?? 'N/A') ?>
                            </div>
                        </div>
                        <div class="activity-time">
                            <?= isset($activity['completed_at']) ? date('M d, Y h:i A', strtotime($activity['completed_at'])) : 'Just now' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-flask"></i>
                    <p>No recent activity</p>
                    <p class="text-xs text-gray-400 mt-1">Complete some tests to see activity here</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="info-card animate-fade-in-up mt-4">
        <div class="card-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="pending_requests.php" class="text-center p-4 border rounded-lg hover:bg-primary-bg transition">
                <i class="fas fa-clock text-2xl text-blue-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Pending Requests</span>
            </a>
            <a href="in_progress.php" class="text-center p-4 border rounded-lg hover:bg-primary-bg transition">
                <i class="fas fa-spinner text-2xl text-blue-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">In Progress</span>
            </a>
            <a href="results_history.php" class="text-center p-4 border rounded-lg hover:bg-primary-bg transition">
                <i class="fas fa-history text-2xl text-blue-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Results History</span>
            </a>
            <a href="reports.php" class="text-center p-4 border rounded-lg hover:bg-primary-bg transition">
                <i class="fas fa-chart-bar text-2xl text-blue-600 block mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Reports</span>
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Laboratory Profile
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
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
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

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
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
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

    console.log('%c🧪 Braick - Laboratory Profile', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Session-based login active - redirects to login if not authenticated', 'font-size:12px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Tests: <?= $total_tests ?> | Requests: <?= $total_requests ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Today\'s Tests: <?= $today_tests ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>