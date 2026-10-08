<?php
// ================================================================
// FILE: frontend/pages/reception/dashboard.php
// RECEPTION DASHBOARD - WITH SHARED HEADER & SIDEBAR
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK ACCESS (Reception or Admin)
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor':     header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy':   header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier':    header('Location: ../cashier/dashboard.php'); break;
        default:           header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// USER DATA
// ================================================================
$user_id     = $_SESSION['user_id'];
$full_name   = $_SESSION['full_name'] ?? 'User';
$role        = $_SESSION['role'] ?? 'reception';
$branch_id   = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username    = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// TIME AGO (guard against redeclaration from header)
// ================================================================
if (!function_exists('time_ago')) {
    function time_ago($timestamp) {
        if (empty($timestamp)) return 'Just now';
        $time = strtotime($timestamp);
        $diff = time() - $time;
        if ($diff < 60)     return 'Just now';
        if ($diff < 3600)   return floor($diff / 60) . 'm ago';
        if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M d, Y', $time);
    }
}

// ================================================================
// INIT DEFAULTS
// ================================================================
$unread_notifications    = 0;
$doctors                 = [];
$online_doctors_count    = 0;
$total_doctors           = 0;
$online_doctors_list     = [];
$recent_activities       = [];
$today_appointments_list = [];
$recent_patients         = [];

try {
    $db = Database::getInstance()->getConnection();
    $today = date('Y-m-d');

    // ---------- UNREAD NOTIFICATIONS ----------
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;

    // ---------- DOCTORS ----------
    $stmt = $db->prepare("
        SELECT id, full_name, specialty, is_online 
        FROM users 
        WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
        ORDER BY is_online DESC, full_name
    ");
    $stmt->execute([$branch_id]);
    $doctors = $stmt->fetchAll();

    foreach ($doctors as $doc) {
        if ($doc['is_online'] == 1) $online_doctors_count++;
    }
    $total_doctors       = count($doctors);
    $online_doctors_list = array_filter($doctors, fn($d) => $d['is_online'] == 1);

    // ---------- RECENT ACTIVITIES ----------
    try {
        $stmt = $db->prepare("
            SELECT action, details, created_at 
            FROM activity_logs 
            WHERE branch_id = ? OR branch_id IS NULL
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$branch_id]);
        $recent_activities = $stmt->fetchAll();
    } catch (Exception $e) {
        $recent_activities = [];
    }

    // ---------- TODAY'S APPOINTMENTS ----------
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name, p.patient_id, u.full_name as doctor_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.doctor_id = u.id
        WHERE DATE(a.appointment_date) = CURDATE() AND a.branch_id = ?
        ORDER BY a.appointment_date
        LIMIT 10
    ");
    $stmt->execute([$branch_id]);
    $today_appointments_list = $stmt->fetchAll();

    // ---------- RECENT PATIENTS ----------
    $stmt = $db->prepare("
        SELECT * FROM patients 
        WHERE branch_id = ?
        ORDER BY created_at DESC 
        LIMIT 8
    ");
    $stmt->execute([$branch_id]);
    $recent_patients = $stmt->fetchAll();

} catch (Exception $e) {
    // Keep defaults
}

// ================================================================
// STATS
// ================================================================
$stats = [
    'online_doctors'       => $online_doctors_count,
    'total_doctors'        => $total_doctors,
    'total_patients'       => 0,
    'total_visits'         => 0,
    'total_appointments'   => 0,
    'today_patients'       => 0,
    'today_visits'         => 0,
    'today_appointments'   => count($today_appointments_list),
    'pending_appointments' => 0
];

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $stats['total_patients'] = $stmt->fetch()['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $stats['total_visits'] = $stmt->fetch()['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $stats['total_appointments'] = $stmt->fetch()['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE branch_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$branch_id, $today]);
    $stats['today_patients'] = $stmt->fetch()['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$branch_id, $today]);
    $stats['today_visits'] = $stmt->fetch()['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND status IN ('scheduled', 'pending')");
    $stmt->execute([$branch_id]);
    $stats['pending_appointments'] = $stmt->fetch()['count'] ?? 0;

} catch (Exception $e) {
    // Keep defaults
}

// ================================================================
// ASSETS
// ================================================================
$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= (isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true') ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reception Dashboard - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* ================================================================
           DASHBOARD-SPECIFIC STYLES
           (Shared base styles are in reception_header.php)
           ================================================================ */

        /* ---------- PAGE HEADER ---------- */
        .page-header {
            background: linear-gradient(135deg, var(--primary, #0B5ED7) 0%, var(--primary-dark, #0A4CA8) 100%);
            border-radius: 20px;
            padding: 26px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 30px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }
        .page-header::before,
        .page-header::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .page-header::before {
            width: 340px; height: 340px;
            top: -180px; right: -80px;
            background: rgba(255,255,255,0.06);
        }
        .page-header::after {
            width: 200px; height: 200px;
            bottom: -120px; left: -40px;
            background: rgba(255,255,255,0.04);
        }
        .page-header .page-title {
            color: #fff;
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            letter-spacing: -0.02em;
        }
        .page-header .page-title i { font-size: 1.9rem; opacity: 0.95; }

        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
            position: relative;
            z-index: 1;
        }
        .page-header .page-subtitle strong { color: #fff; font-weight: 600; }

        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: #fff;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(6px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .page-header .header-badge .online-count {
            color: #34D399;
            font-weight: 700;
        }

        .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 9px 18px;
            border-radius: 11px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(6px);
            position: relative;
            z-index: 1;
            cursor: pointer;
        }
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.18);
            color: #fff;
        }

        .update-badge-light {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.85);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(6px);
            font-weight: 500;
        }

        /* ================================================================
           STAT CARDS
           ================================================================ */
        .stat-card {
            border-radius: 18px;
            padding: 22px 24px;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: block;
            color: #fff;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
            isolation: isolate;
            min-height: 130px;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 160px; height: 160px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            transition: transform 0.6s ease;
            pointer-events: none;
            z-index: 0;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            bottom: -70px; right: -30px;
            width: 130px; height: 130px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
            pointer-events: none;
            z-index: 0;
        }
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 18px 45px rgba(0,0,0,0.18);
            color: #fff;
        }
        .stat-card:hover::before { transform: scale(1.2); }
        .stat-card:active { transform: scale(0.97); }

        .stat-card.blue   { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.green  { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.red    { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        .stat-card.teal   { background: linear-gradient(135deg, #0D9488, #0F766E); }
        .stat-card.pink   { background: linear-gradient(135deg, #DB2777, #BE185D); }
        .stat-card.indigo { background: linear-gradient(135deg, #4F46E5, #4338CA); }

        .stat-card .stat-icon {
            font-size: 1.7rem;
            margin-bottom: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }

        .stat-card .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.15;
            letter-spacing: -0.02em;
            position: relative;
            z-index: 1;
        }
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.85);
            font-weight: 500;
            margin-top: 3px;
            position: relative;
            z-index: 1;
        }
        .stat-card .stat-badge {
            position: absolute;
            top: 14px;
            right: 14px;
            background: rgba(255,255,255,0.18);
            padding: 3px 11px;
            border-radius: 12px;
            font-size: 0.58rem;
            font-weight: 600;
            color: rgba(255,255,255,0.95);
            backdrop-filter: blur(6px);
            z-index: 2;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .stat-card .stat-arrow {
            position: absolute;
            bottom: 14px;
            right: 18px;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.45);
            transition: all 0.3s ease;
            z-index: 2;
        }
        .stat-card:hover .stat-arrow {
            transform: translateX(5px);
            color: rgba(255,255,255,0.95);
        }

        /* ================================================================
           CARD CONTAINERS
           ================================================================ */
        .card {
            background: var(--bg-card, #fff);
            border-radius: 16px;
            padding: 20px 22px;
            border: 1px solid var(--border-color, #E2E8F0);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,0.05));
        }
        .card:hover {
            border-color: var(--primary, #0B5ED7);
            box-shadow: var(--shadow-md, 0 4px 12px rgba(0,0,0,0.08));
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .card-title {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--text-primary, #1E293B);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .title-blue   { color: var(--primary, #0B5ED7); }
        .title-green  { color: var(--success, #059669); }
        .title-orange { color: #D97706; }

        .view-all-link {
            font-size: 0.78rem;
            color: var(--primary, #0B5ED7);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        .view-all-link:hover {
            text-decoration: underline;
            transform: translateX(2px);
        }

        /* ================================================================
           APPOINTMENT ITEMS
           ================================================================ */
        .appointment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            border-radius: 10px;
            border-bottom: 1px solid var(--border-color, #E2E8F0);
            transition: all 0.25s ease;
            gap: 12px;
        }
        .appointment-item:hover {
            background: var(--bg-body, #F1F5F9);
            transform: translateX(3px);
        }
        .appointment-item:last-child { border-bottom: none; }

        .appointment-time {
            font-weight: 600;
            font-size: 0.75rem;
            color: var(--primary, #0B5ED7);
            min-width: 62px;
            padding: 3px 8px;
            background: var(--primary-bg, #E8F0FE);
            border-radius: 8px;
            text-align: center;
        }

        .appointment-patient .name {
            font-weight: 600;
            font-size: 0.82rem;
            color: var(--text-primary, #1E293B);
        }
        .appointment-patient .doctor {
            font-size: 0.68rem;
            color: var(--text-secondary, #64748B);
            margin-top: 1px;
        }

        .appointment-status {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 3px 11px;
            border-radius: 12px;
            white-space: nowrap;
        }
        .appointment-status.scheduled  { background: #E8F0FE; color: #0B5ED7; }
        .appointment-status.confirmed  { background: #D1FAE5; color: #059669; }
        .appointment-status.completed  { background: #D1FAE5; color: #059669; }
        .appointment-status.cancelled  { background: #FEE2E2; color: #DC2626; }
        .appointment-status.pending    { background: #FEF3C7; color: #D97706; }

        [data-theme="dark"] .appointment-status.scheduled  { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .appointment-status.confirmed  { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .appointment-status.completed  { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .appointment-status.cancelled  { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .appointment-status.pending    { background: #3D2E0A; color: #FBBF24; }

        /* ================================================================
           LIST ROWS (Doctors & Patients)
           ================================================================ */
        .list-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 12px;
            border-radius: 10px;
            transition: all 0.25s ease;
            gap: 10px;
            border-bottom: 1px solid var(--border-color, #E2E8F0);
        }
        .list-row:last-child { border-bottom: none; }
        .list-row:hover { background: var(--bg-body, #F1F5F9); }

        .doctor-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 0.8rem;
            flex-shrink: 0;
            box-shadow: 0 3px 8px rgba(11, 94, 215, 0.25);
        }

        .patient-avatar-sm {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .online-dot {
            display: inline-block;
            width: 9px; height: 9px;
            border-radius: 50%;
            background: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.2);
            animation: pulse-dot 1.8s infinite;
            flex-shrink: 0;
        }

        /* ================================================================
           ACTIVITY ITEMS
           ================================================================ */
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            border-bottom: 1px solid var(--border-color, #E2E8F0);
            transition: all 0.25s ease;
        }
        .activity-item:hover { background: var(--bg-body, #F1F5F9); }
        .activity-item:last-child { border-bottom: none; }

        .activity-icon {
            width: 32px; height: 32px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            flex-shrink: 0;
            background: var(--primary-bg, #E8F0FE);
            color: var(--primary, #0B5ED7);
        }
        .activity-content .action {
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--text-primary, #1E293B);
            line-height: 1.3;
        }
        .activity-content .details {
            font-size: 0.72rem;
            color: var(--text-secondary, #64748B);
            margin-top: 1px;
        }
        .activity-content .time {
            font-size: 0.65rem;
            color: var(--text-secondary, #64748B);
            opacity: 0.75;
            margin-top: 3px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* ================================================================
           QUICK ACTIONS
           ================================================================ */
        .quick-action {
            padding: 18px 14px;
            border-radius: 14px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
            border: 1.5px solid var(--border-color, #E2E8F0);
            background: var(--bg-card, #fff);
            position: relative;
            overflow: hidden;
        }
        .quick-action:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(11, 94, 215, 0.15);
        }
        .quick-action .icon {
            font-size: 1.7rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px; height: 52px;
            border-radius: 14px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .quick-action .icon.blue   { background: #E8F0FE; color: #0B5ED7; }
        .quick-action .icon.green  { background: #D1FAE5; color: #059669; }
        .quick-action .icon.purple { background: #EDE9FE; color: #7C3AED; }
        .quick-action .icon.orange { background: #FEF3C7; color: #D97706; }

        .quick-action .label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-primary, #1E293B);
            transition: color 0.3s;
        }

        .quick-action.blue:hover   { background: #0B5ED7; border-color: #0B5ED7; }
        .quick-action.green:hover  { background: #059669; border-color: #059669; }
        .quick-action.purple:hover { background: #7C3AED; border-color: #7C3AED; }
        .quick-action.orange:hover { background: #D97706; border-color: #D97706; }

        .quick-action:hover .label { color: #fff; }
        .quick-action:hover .icon  { background: rgba(255,255,255,0.2) !important; color: #fff !important; }

        /* ================================================================
           SCROLL CONTAINER
           ================================================================ */
        .scroll-container {
            max-height: 260px;
            overflow-y: auto;
            padding-right: 4px;
        }
        .scroll-container::-webkit-scrollbar { width: 5px; }
        .scroll-container::-webkit-scrollbar-track {
            background: var(--bg-body, #F1F5F9);
            border-radius: 4px;
        }
        .scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary, #0B5ED7);
            border-radius: 4px;
        }

        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 28px 12px;
            color: var(--text-secondary, #64748B);
        }
        .empty-state i {
            font-size: 2rem;
            display: block;
            margin-bottom: 8px;
            opacity: 0.4;
        }
        .empty-state p { font-size: 0.8rem; }

        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); opacity: 1; }
            50%      { transform: scale(1.25); opacity: 0.75; }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }

        /* ================================================================
           DARK MODE
           ================================================================ */
        [data-theme="dark"] .appointment-time {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        [data-theme="dark"] .activity-icon {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        [data-theme="dark"] .doctor-avatar {
            background: linear-gradient(135deg, #1E3A5F, #0A4CA8);
        }
        [data-theme="dark"] .quick-action .icon.blue   { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .quick-action .icon.green  { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .quick-action .icon.purple { background: #2A1A4A; color: #A78BFA; }
        [data-theme="dark"] .quick-action .icon.orange { background: #3D2E0A; color: #FBBF24; }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 768px) {
            .page-header { padding: 18px 20px; border-radius: 16px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stat-card { padding: 16px 18px; min-height: 115px; }
            .stat-card .stat-number { font-size: 1.7rem; }
            .stat-card .stat-icon { width: 40px; height: 40px; font-size: 1.4rem; }
            .scroll-container { max-height: 220px; }
        }
        @media (max-width: 640px) {
            .stat-card { padding: 14px 16px; min-height: 105px; border-radius: 14px; }
            .stat-card .stat-number { font-size: 1.45rem; }
            .stat-card .stat-icon { width: 36px; height: 36px; font-size: 1.25rem; margin-bottom: 6px; }
            .stat-card .stat-label { font-size: 0.68rem; }
            .page-header .page-title { font-size: 1.15rem; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT (Header & Sidebar already included above) -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ============================================================ -->
    <!-- PAGE HEADER -->
    <!-- ============================================================ -->
    <div class="page-header animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-home"></i>
                Reception Dashboard
                <span class="role-badge-display" style="background:rgba(255,255,255,0.22);color:#fff;">RECEPTION</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                Welcome back, <strong><?= htmlspecialchars($full_name) ?></strong>!
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-calendar-day"></i> <?= date('F d, Y') ?>
                </span>
                <span class="header-badge" id="onlineDoctorBadge">
                    <i class="fas fa-user-md"></i>
                    <span class="online-count" id="onlineDoctorCount"><?= $online_doctors_count ?></span> Online
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="new_patient.php" class="btn-outline-light">
                <i class="fas fa-user-plus"></i> Register Patient
            </a>
            <a href="new_appointment.php" class="btn-outline-light">
                <i class="fas fa-calendar-plus"></i> New Appointment
            </a>
            <button onclick="location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- STATS CARDS -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-5">
        <a href="online_doctors.php" class="stat-card blue animate-fade-in-up" style="animation-delay:0.05s;">
            <span class="stat-icon">🟢</span>
            <div class="stat-number"><?= $online_doctors_count ?></div>
            <div class="stat-label">Online Doctors</div>
            <span class="stat-badge"><?= $total_doctors ?> Total</span>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>

        <a href="patients.php" class="stat-card purple animate-fade-in-up" style="animation-delay:0.1s;">
            <span class="stat-icon">👥</span>
            <div class="stat-number"><?= number_format($stats['total_patients']) ?></div>
            <div class="stat-label">Total Patients</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>

        <a href="visits.php" class="stat-card green animate-fade-in-up" style="animation-delay:0.15s;">
            <span class="stat-icon">🏥</span>
            <div class="stat-number"><?= number_format($stats['total_visits']) ?></div>
            <div class="stat-label">Total Visits</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>

        <a href="appointments.php" class="stat-card indigo animate-fade-in-up" style="animation-delay:0.2s;">
            <span class="stat-icon">📅</span>
            <div class="stat-number"><?= number_format($stats['total_appointments']) ?></div>
            <div class="stat-label">Total Appointments</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>

        <a href="visits.php?filter=today" class="stat-card orange animate-fade-in-up" style="animation-delay:0.25s;">
            <span class="stat-icon">👤</span>
            <div class="stat-number"><?= $stats['today_patients'] ?></div>
            <div class="stat-label">Today's Patients</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>

        <a href="visits.php?filter=today" class="stat-card teal animate-fade-in-up" style="animation-delay:0.3s;">
            <span class="stat-icon">🩺</span>
            <div class="stat-number"><?= $stats['today_visits'] ?></div>
            <div class="stat-label">Today's Visits</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>

        <a href="appointments.php?filter=today" class="stat-card pink animate-fade-in-up" style="animation-delay:0.35s;">
            <span class="stat-icon">📋</span>
            <div class="stat-number"><?= $stats['today_appointments'] ?></div>
            <div class="stat-label">Today's Appointments</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>

        <a href="appointments.php?status=pending" class="stat-card red animate-fade-in-up" style="animation-delay:0.4s;">
            <span class="stat-icon">⏳</span>
            <div class="stat-number"><?= $stats['pending_appointments'] ?></div>
            <div class="stat-label">Pending Appointments</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
    </div>

    <!-- ============================================================ -->
    <!-- APPOINTMENTS & ONLINE DOCTORS -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

        <!-- Today's Appointments -->
        <div class="card lg:col-span-2 animate-fade-in-up" style="animation-delay:0.45s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-check title-blue"></i>
                    Today's Appointments
                    <span style="font-size:0.75rem;font-weight:400;color:var(--text-secondary);">(<?= count($today_appointments_list) ?>)</span>
                </h3>
                <a href="appointments.php" class="view-all-link">View All →</a>
            </div>

            <div class="scroll-container">
                <?php if (count($today_appointments_list) > 0): ?>
                    <?php foreach ($today_appointments_list as $appt): ?>
                        <div class="appointment-item">
                            <span class="appointment-time"><?= date('h:i A', strtotime($appt['appointment_date'])) ?></span>
                            <div class="appointment-patient" style="flex:1;">
                                <span class="name"><?= htmlspecialchars($appt['patient_name']) ?></span>
                                <span class="doctor" style="display:block;">Dr. <?= htmlspecialchars($appt['doctor_name']) ?></span>
                            </div>
                            <span class="appointment-status <?= $appt['status'] ?>">
                                <?= ucfirst($appt['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <p>No appointments scheduled for today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Online Doctors -->
        <div class="card animate-fade-in-up" style="animation-delay:0.5s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-md title-green"></i>
                    Online Doctors
                    <span style="font-size:0.75rem;font-weight:400;color:var(--text-secondary);">(<?= count($online_doctors_list) ?>)</span>
                </h3>
                <a href="online_doctors.php" class="view-all-link">View All →</a>
            </div>

            <div class="scroll-container">
                <?php if (count($online_doctors_list) > 0): ?>
                    <?php foreach ($online_doctors_list as $doc): ?>
                        <div class="list-row">
                            <div style="display:flex;align-items:center;gap:11px;">
                                <div class="doctor-avatar">
                                    <?= strtoupper(substr($doc['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p style="font-weight:600;font-size:0.82rem;color:var(--text-primary);margin:0;">
                                        <?= htmlspecialchars($doc['full_name']) ?>
                                    </p>
                                    <p style="font-size:0.7rem;color:var(--text-secondary);margin:0;">
                                        <?= htmlspecialchars($doc['specialty'] ?? 'General Practitioner') ?>
                                    </p>
                                </div>
                            </div>
                            <span class="online-dot" title="Online"></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-md"></i>
                        <p>No doctors online</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- RECENT PATIENTS & ACTIVITIES -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        <!-- Recent Patients -->
        <div class="card animate-fade-in-up" style="animation-delay:0.55s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-injured title-blue"></i>
                    Recent Patients
                </h3>
                <a href="patients.php" class="view-all-link">View All →</a>
            </div>

            <div class="scroll-container">
                <?php if (count($recent_patients) > 0): ?>
                    <?php foreach ($recent_patients as $patient): ?>
                        <div class="list-row">
                            <div style="display:flex;align-items:center;gap:11px;flex:1;min-width:0;">
                                <div class="patient-avatar-sm" style="background:<?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
                                    <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                                </div>
                                <div style="min-width:0;flex:1;">
                                    <p style="font-weight:600;font-size:0.82rem;color:var(--text-primary);margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?= htmlspecialchars($patient['full_name']) ?>
                                    </p>
                                    <p style="font-size:0.7rem;color:var(--text-secondary);margin:0;">
                                        <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?> •
                                        <?= htmlspecialchars($patient['phone'] ?? 'No phone') ?>
                                    </p>
                                </div>
                            </div>
                            <div style="text-align:right;flex-shrink:0;">
                                <p style="font-size:0.65rem;color:var(--text-secondary);margin:0 0 3px 0;">
                                    <?= isset($patient['created_at']) ? time_ago($patient['created_at']) : 'N/A' ?>
                                </p>
                                <a href="view_patient.php?id=<?= $patient['id'] ?>"
                                   style="font-size:0.7rem;color:var(--primary);text-decoration:none;font-weight:500;">
                                    View →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No patients registered yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="card animate-fade-in-up" style="animation-delay:0.6s;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock title-orange"></i>
                    Recent Activities
                </h3>
                <a href="activities.php" class="view-all-link">View All →</a>
            </div>

            <div class="scroll-container">
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="activity-content" style="flex:1;">
                                <p class="action"><?= htmlspecialchars($activity['action'] ?? 'Action') ?></p>
                                <p class="details"><?= htmlspecialchars($activity['details'] ?? '') ?></p>
                                <p class="time">
                                    <i class="far fa-clock" style="font-size:0.6rem;"></i>
                                    <?= isset($activity['created_at']) ? time_ago($activity['created_at']) : 'Just now' ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clock"></i>
                        <p>No recent activities</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5 animate-fade-in-up" style="animation-delay:0.65s;">
        <a href="new_patient.php" class="quick-action blue">
            <span class="icon blue"><i class="fas fa-user-plus"></i></span>
            <span class="label">Register Patient</span>
        </a>
        <a href="new_appointment.php" class="quick-action green">
            <span class="icon green"><i class="fas fa-calendar-plus"></i></span>
            <span class="label">New Appointment</span>
        </a>
        <a href="patients.php" class="quick-action purple">
            <span class="icon purple"><i class="fas fa-users"></i></span>
            <span class="label">View Patients</span>
        </a>
        <a href="assign_doctor.php" class="quick-action orange">
            <span class="icon orange"><i class="fas fa-user-md"></i></span>
            <span class="label">Assign Doctor</span>
        </a>
    </div>

    <!-- ============================================================ -->
    <!-- FOOTER -->
    <!-- ============================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            Reception Dashboard
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
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

<script>
    // ================================================================
    // DATE & TIME UPDATER
    // ================================================================
    function updateDateTime() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        const footer = document.getElementById('footerTimestamp');
        if (footer) footer.textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        const toast = document.getElementById('toast');
        if (!toast) return;
        document.getElementById('toastTitle').textContent = title;
        document.getElementById('toastMessage').textContent = message;
        toast.className = 'toast-custom ' + type;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    console.log('%c🏥 Braick - Reception Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👋 Welcome, <?= htmlspecialchars($full_name) ?>!', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Using shared header & sidebar', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>