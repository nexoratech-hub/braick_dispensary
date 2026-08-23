<?php
// ================================================================
// FILE: frontend/pages/laboratory/profile.php
// LABORATORY - PROFILE (UPDATED FOR NEW DATABASE)
// FIXED: Using lab_tests table instead of lab_request_items
// BRAICK DISPENSARY - dispensary_db
// ================================================================

// ================================================================
// START SESSION
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
// DATABASE CONNECTION - NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET USER STATISTICS - USING lab_tests TABLE
// ================================================================

// 1. Total Tests Completed by this technician
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE performed_by = ? AND status = 'completed'
");
$stmt->execute([$user_id]);
$total_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. Total Tests In Progress by this technician
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE performed_by = ? AND status = 'in_progress'
");
$stmt->execute([$user_id]);
$in_progress_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 3. Total Pending Tests by this technician
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE performed_by = ? AND (status IS NULL OR status = 'pending')
");
$stmt->execute([$user_id]);
$pending_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 4. Today's Tests Completed
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE performed_by = ? AND status = 'completed' AND DATE(completed_at) = ?
");
$stmt->execute([$user_id, $today]);
$today_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 5. Recent Activity (Last 5 completed tests)
$stmt = $db->prepare("
    SELECT 
        lt.*,
        v.visit_number,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        u.full_name as doctor_name
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON lt.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    WHERE lt.performed_by = ? AND lt.status = 'completed'
    ORDER BY lt.completed_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$pending_lab_tests = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests 
        WHERE branch_id = ? AND (status IS NULL OR status = 'pending')
    ");
    $stmt->execute([$user_branch_id]);
    $pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_lab_tests = 0;
}

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
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
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
           MAIN CONTENT
           ================================================================ */
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
            border: 1px solid rgba(255,255,255,0.1);
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
            transition: all 0.3s ease;
        }
        
        .profile-header:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
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
        
        .badge-blue {
            background: #E8F0FE;
            color: #0B5ED7;
        }
        
        .badge-green {
            background: #D1FAE5;
            color: #059669;
        }
        
        .badge-purple {
            background: #F3E8FF;
            color: #7C3AED;
        }
        
        [data-theme="dark"] .badge-blue {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        [data-theme="dark"] .badge-green {
            background: #1A3A2A;
            color: #34D399;
        }
        
        [data-theme="dark"] .badge-purple {
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
           QUICK ACTIONS - 3 BUTTONS
           ================================================================ */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .quick-action-item {
            text-align: center;
            padding: 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
            background: var(--bg-card);
        }
        
        .quick-action-item:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.1);
        }
        
        .quick-action-item i {
            font-size: 1.8rem;
            color: var(--primary);
            display: block;
            margin-bottom: 8px;
        }
        
        .quick-action-item .label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-primary);
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
           GRID
           ================================================================ */
        .grid {
            display: grid;
            gap: 20px;
        }
        
        .grid-cols-1 {
            grid-template-columns: 1fr;
        }
        
        .grid-cols-2 {
            grid-template-columns: 1fr 1fr;
        }
        
        .grid-cols-3 {
            grid-template-columns: 1fr 1fr 1fr;
        }
        
        .grid-cols-4 {
            grid-template-columns: 1fr 1fr 1fr 1fr;
        }
        
        .lg\:grid-cols-2 {
            grid-template-columns: 1fr 1fr;
        }
        
        .lg\:grid-cols-4 {
            grid-template-columns: 1fr 1fr 1fr 1fr;
        }
        
        .gap-4 {
            gap: 16px;
        }
        
        .gap-5 {
            gap: 20px;
        }
        
        .mt-4 {
            margin-top: 16px;
        }
        
        .p-4 {
            padding: 16px;
        }
        
        .border {
            border: 2px solid var(--border-color);
        }
        
        .rounded-lg {
            border-radius: 10px;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-sm {
            font-size: 0.85rem;
        }
        
        .text-xs {
            font-size: 0.7rem;
        }
        
        .text-2xl {
            font-size: 1.5rem;
        }
        
        .text-blue-600 {
            color: var(--primary);
        }
        
        .text-gray-400 {
            color: var(--gray-400);
        }
        
        .text-gray-700 {
            color: var(--gray-700);
        }
        
        .font-medium {
            font-weight: 500;
        }
        
        .block {
            display: block;
        }
        
        .mb-2 {
            margin-bottom: 8px;
        }
        
        .mt-1 {
            margin-top: 4px;
        }
        
        .ml-2 {
            margin-left: 8px;
        }
        
        .mr-1 {
            margin-right: 4px;
        }
        
        .mx-2 {
            margin-left: 8px;
            margin-right: 8px;
        }
        
        .hover\:bg-primary-bg:hover {
            background: var(--primary-bg);
        }
        
        .transition {
            transition: all 0.3s ease;
        }
        
        [data-theme="dark"] .text-blue-600 {
            color: var(--primary-light);
        }
        
        [data-theme="dark"] .text-gray-700 {
            color: var(--gray-300);
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
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
            .quick-actions-grid {
                grid-template-columns: 1fr 1fr 1fr;
                max-width: 100%;
            }
            .grid-cols-2 {
                grid-template-columns: 1fr 1fr;
            }
            .lg\:grid-cols-2 {
                grid-template-columns: 1fr;
            }
            .lg\:grid-cols-4 {
                grid-template-columns: 1fr 1fr;
            }
            .quick-action-item {
                padding: 12px;
            }
            .quick-action-item i {
                font-size: 1.4rem;
            }
            .quick-action-item .label {
                font-size: 0.65rem;
            }
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
            .quick-actions-grid {
                grid-template-columns: 1fr 1fr;
            }
            .quick-action-item {
                padding: 10px;
            }
            .quick-action-item i {
                font-size: 1.2rem;
            }
            .quick-action-item .label {
                font-size: 0.6rem;
            }
        }
    </style>
</head>
<body>

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
        <div class="flex gap-2 flex-wrap">
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
            <p class="stat-number"><?= $pending_tests ?></p>
            <p class="stat-label">Pending Tests</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-spinner"></i></div>
            <p class="stat-number"><?= $in_progress_tests ?></p>
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
                                <?= htmlspecialchars($activity['test_name'] ?? 'Test') ?>
                            </div>
                            <div class="activity-desc">
                                <i class="fas fa-user mr-1"></i>
                                <?= htmlspecialchars($activity['patient_name'] ?? 'Unknown') ?>
                                <span class="mx-1">|</span>
                                <i class="fas fa-receipt mr-1"></i>
                                <?= htmlspecialchars($activity['visit_number'] ?? 'N/A') ?>
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
    <!-- QUICK ACTIONS - 3 BUTTONS -->
    <!-- ================================================================ -->
    <div class="info-card animate-fade-in-up mt-4">
        <div class="card-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </div>
        <div class="quick-actions-grid">
            <a href="pending_requests.php" class="quick-action-item">
                <i class="fas fa-clock"></i>
                <span class="label">Pending Requests</span>
            </a>
            <a href="in_progress.php" class="quick-action-item">
                <i class="fas fa-spinner"></i>
                <span class="label">In Progress</span>
            </a>
            <a href="results_history.php" class="quick-action-item">
                <i class="fas fa-history"></i>
                <span class="label">Results History</span>
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
            <span id="footerTime"><?= date('h:i:s A') ?></span>
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
    // DARK MODE - SYNC WITH HEADER
    // ================================================================
    document.addEventListener('darkModeChanged', function(e) {
        var isDark = e.detail && e.detail.isDark;
        var html = document.documentElement;
        
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE - SYNC WITH HEADER
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggleBtn');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DATE & TIME - UPDATE LIVE
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) {
            ftEl.textContent = timeStr;
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

    console.log('%c🧪 Braick - Laboratory Profile (FIXED - Using lab_tests)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Using NEW DATABASE: dispensary_db', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Using lab_tests table (NOT lab_request_items)', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📊 Total Tests: <?= $total_tests ?> | Pending: <?= $pending_tests ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 In Progress: <?= $in_progress_tests ?> | Today: <?= $today_tests ?>', 'font-size:13px; color:#059669;');
</script>

</body>
</html>