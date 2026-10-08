<?php
// ================================================================
// FILE: frontend/pages/reception/view_doctor.php
// RECEPTION - VIEW DOCTOR DETAILS (V2 - SHARED HEADER/SIDEBAR)
// ✅ Using shared reception_header.php & reception_sidebar.php
// ✅ Branch filtered
// ✅ Auto-update doctor status (every 3 seconds)
// BRAICK DISPENSARY
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
// ACCESS CONTROL
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
$user_id     = $_SESSION['user_id']     ?? 0;
$full_name   = $_SESSION['full_name']   ?? 'User';
$role        = $_SESSION['role']        ?? 'reception';
$branch_id   = $_SESSION['branch_id']   ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username    = $_SESSION['username']    ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// HELPER: USER COLOR
// ================================================================
function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777', '#4F46E5', '#E11D48'];
    $index  = abs(crc32($name)) % count($colors);
    return $colors[$index];
}

// ================================================================
// NOTE: time_ago() is provided by reception_header.php
// DO NOT redeclare here.
// ================================================================

require_once __DIR__ . '/../../../backend/config/database.php';

$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($doctor_id <= 0) {
    header('Location: online_doctors.php');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // UNREAD NOTIFICATIONS
    $unread_notifications = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $unread_notifications = 0;
    }

    // GET DOCTOR DETAILS
    $stmt = $db->prepare("
        SELECT u.*, b.name as branch_name
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.id = ? AND u.role = 'doctor' AND u.branch_id = ? AND u.status = 'active'
    ");
    $stmt->execute([$doctor_id, $branch_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        header('Location: online_doctors.php');
        exit;
    }

    // STATISTICS
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $today = date('Y-m-d');

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$doctor_id, $today]);
    $today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status IN ('pending', 'assigned')");
    $stmt->execute([$doctor_id]);
    $pending_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status = 'completed'");
    $stmt->execute([$doctor_id]);
    $completed_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ?");
    $stmt->execute([$doctor_id, $today]);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // TODAY'S APPOINTMENTS LIST
    $stmt = $db->prepare("
        SELECT a.*, p.full_name as patient_name, p.patient_id, p.phone
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = ? AND DATE(a.appointment_date) = ?
        ORDER BY a.appointment_date
        LIMIT 10
    ");
    $stmt->execute([$doctor_id, $today]);
    $today_appointments_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // RECENT PATIENTS
    $stmt = $db->prepare("
        SELECT DISTINCT p.*, v.created_at as last_visit
        FROM patients p
        JOIN visits v ON p.id = v.patient_id
        WHERE v.doctor_id = ?
        ORDER BY v.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$doctor_id]);
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // RECENT VISITS
    $stmt = $db->prepare("
        SELECT v.*, p.full_name as patient_name, p.patient_id
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE v.doctor_id = ?
        ORDER BY v.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$doctor_id]);
    $recent_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $doctor                  = null;
    $total_patients          = 0;
    $today_visits            = 0;
    $pending_visits          = 0;
    $completed_visits        = 0;
    $total_appointments      = 0;
    $today_appointments      = 0;
    $total_prescriptions     = 0;
    $today_appointments_list = [];
    $recent_patients         = [];
    $recent_visits           = [];
    $unread_notifications    = 0;
}

// ================================================================
// PATHS
// ================================================================
$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Doctor - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ================================================================
           VIEW DOCTOR PAGE - SPECIFIC STYLES ONLY
           (Base styles, variables, .card, .footer, .toast-custom,
            .main-content are provided by reception_header.php)
           ================================================================ */

        /* ---------- PAGE HEADER ---------- */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083B8A 100%);
            border-radius: 20px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 40px rgba(11, 94, 215, 0.3);
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
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -80%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header .page-title {
            color: white;
            font-size: 1.75rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            letter-spacing: -0.5px;
        }

        .page-header .page-title i { font-size: 1.9rem; opacity: 0.95; }

        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 6px;
        }

        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.15);
        }

        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 600;
            backdrop-filter: blur(8px);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(255,255,255,0.15);
        }

        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 9px 18px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(8px);
            position: relative;
            z-index: 1;
            cursor: pointer;
        }

        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            color: white;
        }

        .update-badge-light {
            background: rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.9);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(8px);
            font-weight: 600;
        }

        .status-online  { color: #34D399 !important; font-weight: 700; }
        .status-offline { color: rgba(255,255,255,0.7) !important; font-weight: 700; }

        /* ---------- DOCTOR PROFILE CARD ---------- */
        .doctor-profile {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 28px 32px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .doctor-profile::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0B5ED7, #7C3AED, #059669);
        }

        .doctor-profile:hover {
            border-color: var(--primary);
            box-shadow: 0 10px 40px rgba(11, 94, 215, 0.12);
            transform: translateY(-3px);
        }

        .doctor-avatar-large {
            width: 88px;
            height: 88px;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 800;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            letter-spacing: -1px;
        }

        .doctor-name-large {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.5px;
        }

        .doctor-specialty-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 20px;
            background: #E8F0FE;
            color: #0B5ED7;
            border: 1px solid #6EA8FE;
        }

        [data-theme="dark"] .doctor-specialty-badge {
            background: #1E3A5F;
            color: #6EA8FE;
            border-color: #3B82F6;
        }

        .doctor-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.7rem;
            font-weight: 800;
            padding: 5px 14px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .doctor-status-badge.online {
            background: #D1FAE5;
            color: #059669;
            border: 1px solid #34D399;
        }

        .doctor-status-badge.offline {
            background: #F1F5F9;
            color: #94A3B8;
            border: 1px solid #CBD5E1;
        }

        [data-theme="dark"] .doctor-status-badge.online  { background: #1A3A2A; color: #34D399; border-color: #34D399; }
        [data-theme="dark"] .doctor-status-badge.offline { background: #334155; color: #94A3B8; border-color: #475569; }

        .doctor-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-top: 14px;
        }

        .doctor-meta-grid .meta-item {
            font-size: 0.78rem;
            color: var(--text-primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-body);
            padding: 8px 14px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            transition: all 0.25s ease;
        }

        .doctor-meta-grid .meta-item:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.1);
        }

        .doctor-meta-grid .meta-item i {
            color: var(--primary);
            font-size: 0.82rem;
            width: 16px;
            text-align: center;
        }

        /* ---------- STATS GRID ---------- */
        .stats-grid-doctor {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 18px 16px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            position: relative;
            overflow: hidden;
        }

        .stat-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #0B5ED7, #0A4CA8);
        }

        .stat-box:hover {
            border-color: var(--primary);
            transform: translateY(-6px);
            box-shadow: 0 12px 30px rgba(11, 94, 215, 0.15);
        }

        .stat-box .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.1rem;
            background: linear-gradient(135deg, #E8F0FE, #DBEAFE);
            color: #0B5ED7;
        }

        [data-theme="dark"] .stat-box .stat-icon {
            background: linear-gradient(135deg, #1E3A5F, #1E40AF);
            color: #6EA8FE;
        }

        .stat-box .stat-icon.green  { background: linear-gradient(135deg, #D1FAE5, #A7F3D0); color: #059669; }
        .stat-box .stat-icon.orange { background: linear-gradient(135deg, #FEF3C7, #FDE68A); color: #D97706; }
        .stat-box .stat-icon.purple { background: linear-gradient(135deg, #EDE9FE, #DDD6FE); color: #7C3AED; }

        [data-theme="dark"] .stat-box .stat-icon.green  { background: linear-gradient(135deg, #1A3A2A, #065F46); color: #34D399; }
        [data-theme="dark"] .stat-box .stat-icon.orange { background: linear-gradient(135deg, #3D2E0A, #78350F); color: #FBBF24; }
        [data-theme="dark"] .stat-box .stat-icon.purple { background: linear-gradient(135deg, #2D1B5F, #4C1D95); color: #C4B5FD; }

        .stat-box .stat-number {
            font-size: 1.65rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
            letter-spacing: -0.5px;
        }

        .stat-box .stat-number.green  { color: #059669; }
        .stat-box .stat-number.orange { color: #D97706; }
        .stat-box .stat-number.purple { color: #7C3AED; }

        .stat-box .stat-label {
            font-size: 0.68rem;
            color: var(--text-secondary);
            font-weight: 700;
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .stat-box .stat-update {
            font-size: 0.55rem;
            color: var(--text-secondary);
            opacity: 0.6;
            margin-top: 4px;
            font-weight: 500;
        }

        /* ---------- APPOINTMENT ITEMS ---------- */
        .appointment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border-radius: 12px;
            transition: all 0.25s ease;
            margin-bottom: 6px;
            background: var(--bg-body);
            border: 1px solid transparent;
        }

        .appointment-item:hover {
            background: var(--primary-bg);
            border-color: var(--primary);
            transform: translateX(4px);
        }

        .appointment-time {
            font-weight: 800;
            font-size: 0.75rem;
            color: var(--primary);
            min-width: 70px;
        }

        .appointment-patient .name {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-primary);
            display: block;
        }

        .appointment-patient .id {
            font-size: 0.7rem;
            color: var(--text-secondary);
            display: block;
            margin-top: 2px;
            font-family: 'JetBrains Mono', monospace;
        }

        .appointment-status {
            font-size: 0.6rem;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .appointment-status.scheduled  { background: #E8F0FE; color: #0B5ED7; }
        .appointment-status.confirmed  { background: #D1FAE5; color: #059669; }
        .appointment-status.completed  { background: #D1FAE5; color: #059669; }
        .appointment-status.cancelled  { background: #FEE2E2; color: #DC2626; }
        .appointment-status.pending    { background: #FEF3C7; color: #D97706; }

        [data-theme="dark"] .appointment-status.scheduled { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .appointment-status.confirmed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .appointment-status.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .appointment-status.cancelled { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .appointment-status.pending   { background: #3D2E0A; color: #FBBF24; }

        /* ---------- PATIENT AVATAR ---------- */
        .patient-avatar-sm {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 0.75rem;
            flex-shrink: 0;
            box-shadow: 0 3px 10px rgba(0,0,0,0.15);
        }

        /* ---------- VISIT ITEM ---------- */
        .visit-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border-radius: 12px;
            transition: all 0.25s ease;
            margin-bottom: 6px;
            background: var(--bg-body);
            border: 1px solid transparent;
        }

        .visit-item:hover {
            background: var(--primary-bg);
            border-color: var(--primary);
            transform: translateX(4px);
        }

        .visit-item .visit-date {
            font-size: 0.72rem;
            color: var(--text-secondary);
            min-width: 110px;
            font-weight: 600;
        }

        .visit-item .visit-patient .name {
            font-weight: 700;
            font-size: 0.82rem;
            color: var(--text-primary);
            display: block;
        }

        .visit-item .visit-patient .id {
            font-size: 0.68rem;
            color: var(--text-secondary);
            display: block;
            margin-top: 2px;
            font-family: 'JetBrains Mono', monospace;
        }

        .visit-item .visit-status {
            font-size: 0.6rem;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .visit-item .visit-status.pending    { background: #FEF3C7; color: #D97706; }
        .visit-item .visit-status.assigned   { background: #E8F0FE; color: #0B5ED7; }
        .visit-item .visit-status.completed  { background: #D1FAE5; color: #059669; }
        .visit-item .visit-status.cancelled  { background: #FEE2E2; color: #DC2626; }

        [data-theme="dark"] .visit-item .visit-status.pending   { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .visit-item .visit-status.assigned  { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .visit-item .visit-status.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .visit-item .visit-status.cancelled { background: #3A1A1A; color: #F87171; }

        /* ---------- SCROLL CONTAINER ---------- */
        .scroll-container {
            max-height: 260px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .scroll-container::-webkit-scrollbar { width: 5px; }
        .scroll-container::-webkit-scrollbar-track { background: transparent; }
        .scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
            opacity: 0.5;
        }
        .scroll-container::-webkit-scrollbar-thumb:hover { background: var(--primary-dark); }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 1024px) {
            .doctor-profile { padding: 20px 22px; }
        }

        @media (max-width: 768px) {
            .page-header { padding: 18px 20px; flex-direction: column; align-items: flex-start; }
            .page-header .page-title { font-size: 1.3rem; }
            .doctor-profile { padding: 16px; }
            .doctor-name-large { font-size: 1.15rem; }
            .doctor-avatar-large { width: 68px; height: 68px; font-size: 1.5rem; border-radius: 18px; }
            .doctor-meta-grid { grid-template-columns: 1fr; }
            .stats-grid-doctor { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-box { padding: 14px 12px; border-radius: 14px; }
            .stat-box .stat-number { font-size: 1.35rem; }
            .stat-box .stat-icon { width: 36px; height: 36px; font-size: 0.95rem; }
            .visit-item .visit-date { min-width: 90px; font-size: 0.65rem; }
        }

        @media (max-width: 640px) {
            .doctor-profile { padding: 14px; }
            .doctor-meta-grid .meta-item { font-size: 0.72rem; padding: 6px 10px; }
            .stats-grid-doctor { grid-template-columns: 1fr 1fr; }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }

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
<!-- SHARED HEADER & SIDEBAR (INCLUDED ABOVE) -->
<!-- ================================================================ -->

<main class="main-content">

    <?php if ($doctor): ?>

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md"></i>
                Doctor Details
                <span class="role-badge-display">RECEPTION</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                View doctor information and statistics

                <span class="header-badge" id="onlineStatusBadge">
                    <span id="onlineStatusDisplay" class="<?= ($doctor['is_online'] ?? 0) ? 'status-online' : 'status-offline' ?>">
                        <?= ($doctor['is_online'] ?? 0) ? '🟢 Online' : '⚪ Offline' ?>
                    </span>
                </span>

                <span class="header-badge">
                    <i class="fas fa-hashtag"></i>
                    ID: <?= $doctor['id'] ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="online_doctors.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="assign_doctor.php?doctor_id=<?= $doctor['id'] ?>" class="btn-outline-light">
                <i class="fas fa-user-md"></i> Assign Patient
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DOCTOR PROFILE -->
    <!-- ================================================================ -->
    <div class="doctor-profile animate-fade-in-up" style="max-width:1050px;margin:0 auto 24px;">
        <div class="flex items-center gap-6 flex-wrap">
            <div class="doctor-avatar-large" style="background: <?= getUserColor($doctor['full_name']) ?>;">
                <?= strtoupper(substr($doctor['full_name'], 0, 2)) ?>
            </div>

            <div class="flex-1" style="min-width:260px;">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="doctor-name-large">Dr. <?= htmlspecialchars($doctor['full_name']) ?></span>
                    <span class="doctor-specialty-badge">
                        <i class="fas fa-stethoscope"></i>
                        <?= htmlspecialchars($doctor['specialty'] ?? 'General Practitioner') ?>
                    </span>
                    <span class="doctor-status-badge <?= ($doctor['is_online'] ?? 0) ? 'online' : 'offline' ?>" id="doctorStatusBadge">
                        <?= ($doctor['is_online'] ?? 0) ? '🟢 Online' : '⚪ Offline' ?>
                    </span>
                </div>

                <div class="doctor-meta-grid">
                    <span class="meta-item">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor['branch_name'] ?? 'Not Assigned') ?>
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($doctor['email'] ?? 'N/A') ?>
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-phone"></i> <?= htmlspecialchars($doctor['phone'] ?? 'N/A') ?>
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-calendar-alt"></i> Joined: <?= isset($doctor['created_at']) ? date('M d, Y', strtotime($doctor['created_at'])) : 'N/A' ?>
                    </span>
                    <?php if (!empty($doctor['last_online'])): ?>
                        <span class="meta-item">
                            <i class="fas fa-clock"></i> Last seen: <?= date('M d, Y h:i A', strtotime($doctor['last_online'])) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS -->
    <!-- ================================================================ -->
    <div class="stats-grid-doctor animate-fade-in-up" style="max-width:1050px;margin:0 auto 24px;animation-delay:0.05s;" id="statsGrid">
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <p class="stat-number" id="totalPatients"><?= number_format($total_patients) ?></p>
            <p class="stat-label">Total Patients</p>
            <p class="stat-update" id="totalPatientsUpdate">Updated now</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon green"><i class="fas fa-calendar-day"></i></div>
            <p class="stat-number green" id="todayVisits"><?= number_format($today_visits) ?></p>
            <p class="stat-label">Today's Visits</p>
            <p class="stat-update" id="todayVisitsUpdate">Updated now</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon orange"><i class="fas fa-hourglass-half"></i></div>
            <p class="stat-number orange" id="pendingVisits"><?= number_format($pending_visits) ?></p>
            <p class="stat-label">Pending Visits</p>
            <p class="stat-update" id="pendingVisitsUpdate">Updated now</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <p class="stat-number green" id="completedVisits"><?= number_format($completed_visits) ?></p>
            <p class="stat-label">Completed Visits</p>
            <p class="stat-update" id="completedVisitsUpdate">Updated now</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon purple"><i class="fas fa-calendar-check"></i></div>
            <p class="stat-number purple" id="totalAppointments"><?= number_format($total_appointments) ?></p>
            <p class="stat-label">Total Appointments</p>
            <p class="stat-update" id="totalAppointmentsUpdate">Updated now</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <p class="stat-number" id="todayAppointments"><?= number_format($today_appointments) ?></p>
            <p class="stat-label">Today's Appointments</p>
            <p class="stat-update" id="todayAppointmentsUpdate">Updated now</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon purple"><i class="fas fa-prescription"></i></div>
            <p class="stat-number purple" id="totalPrescriptions"><?= number_format($total_prescriptions) ?></p>
            <p class="stat-label">Prescriptions</p>
            <p class="stat-update" id="totalPrescriptionsUpdate">Updated now</p>
        </div>
        <div class="stat-box">
            <div class="stat-icon orange"><i class="fas fa-tasks"></i></div>
            <p class="stat-number orange" id="workload"><?= number_format($pending_visits + $today_visits) ?></p>
            <p class="stat-label">Total Workload</p>
            <p class="stat-update" id="workloadUpdate">Updated now</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TODAY'S APPOINTMENTS & RECENT PATIENTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 animate-fade-in-up" style="max-width:1050px;margin:0 auto;animation-delay:0.1s;">

        <!-- Today's Appointments -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-check title-blue"></i> Today's Appointments
                    <span class="text-sm font-normal text-gray-400" id="appointmentsCount">(<?= count($today_appointments_list) ?>)</span>
                </h3>
            </div>
            <div class="scroll-container" id="todayAppointmentsList">
                <?php if (count($today_appointments_list) > 0): ?>
                    <?php foreach ($today_appointments_list as $appt): ?>
                        <div class="appointment-item">
                            <span class="appointment-time">
                                <i class="fas fa-clock" style="font-size:0.7rem;opacity:0.7;"></i>
                                <?= date('h:i A', strtotime($appt['appointment_date'])) ?>
                            </span>
                            <div class="appointment-patient flex-1 ml-3">
                                <span class="name"><?= htmlspecialchars($appt['patient_name']) ?></span>
                                <span class="id"><?= htmlspecialchars($appt['patient_id']) ?></span>
                            </div>
                            <span class="appointment-status <?= $appt['status'] ?>">
                                <?= ucfirst($appt['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-6 text-gray-400">
                        <i class="fas fa-calendar-check text-2xl block mb-2 opacity-50"></i>
                        <p class="text-sm font-medium">No appointments today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Patients -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-injured title-green"></i> Recent Patients
                    <span class="text-sm font-normal text-gray-400">(<?= count($recent_patients) ?>)</span>
                </h3>
            </div>
            <div class="scroll-container" id="recentPatientsList">
                <?php if (count($recent_patients) > 0): ?>
                    <?php foreach ($recent_patients as $patient): ?>
                        <div class="appointment-item">
                            <div class="flex items-center gap-3 flex-1">
                                <div class="patient-avatar-sm" style="background: linear-gradient(135deg, <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>, <?= '#' . substr(md5($patient['full_name'] . 'x'), 0, 6) ?>);">
                                    <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="name"><?= htmlspecialchars($patient['full_name']) ?></p>
                                    <p class="id"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p style="font-size:0.65rem;color:var(--text-secondary);font-weight:600;">
                                    <?= isset($patient['last_visit']) ? time_ago($patient['last_visit']) : 'N/A' ?>
                                </p>
                                <a href="view_patient.php?id=<?= $patient['id'] ?>" style="font-size:0.7rem;color:var(--primary);font-weight:700;text-decoration:none;">View →</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-6 text-gray-400">
                        <i class="fas fa-users text-2xl block mb-2 opacity-50"></i>
                        <p class="text-sm font-medium">No patients yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ================================================================ -->
    <!-- RECENT VISITS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="max-width:1050px;margin:20px auto 0;animation-delay:0.15s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-clinic-medical title-orange"></i> Recent Visits
                <span class="text-sm font-normal text-gray-400">(<?= count($recent_visits) ?>)</span>
            </h3>
        </div>
        <div class="scroll-container" id="recentVisitsList" style="max-height:240px;">
            <?php if (count($recent_visits) > 0): ?>
                <?php foreach ($recent_visits as $visit): ?>
                    <div class="visit-item">
                        <span class="visit-date">
                            <i class="fas fa-calendar-alt" style="font-size:0.65rem;opacity:0.7;"></i>
                            <?= date('M d, Y h:i A', strtotime($visit['created_at'])) ?>
                        </span>
                        <div class="visit-patient flex-1 ml-3">
                            <span class="name"><?= htmlspecialchars($visit['patient_name']) ?></span>
                            <span class="id"><?= htmlspecialchars($visit['patient_id']) ?></span>
                        </div>
                        <span class="visit-status <?= $visit['status'] ?>">
                            <?= ucfirst($visit['status']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-clinic-medical text-2xl block mb-2 opacity-50"></i>
                    <p class="text-sm font-medium">No visits recorded</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-user-md text-4xl block mb-3 opacity-50"></i>
            <p class="text-lg font-bold">Doctor not found</p>
            <a href="online_doctors.php" class="text-primary hover:underline font-semibold">Back to doctors list</a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            View Doctor
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($full_name) ?></strong>
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
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

<script>
    // ================================================================
    // CLOCK
    // ================================================================
    function updateClock() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ft = document.getElementById('footerTimestamp');
        if (ft) ft.textContent = 'Last updated: ' + timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ================================================================
    // TOAST (fallback if not defined by header)
    // ================================================================
    if (typeof window.showToast !== 'function') {
        window.showToast = function(title, message, type) {
            var toast = document.getElementById('toast');
            if (!toast) return;
            var toastTitle = document.getElementById('toastTitle');
            var toastMessage = document.getElementById('toastMessage');
            toast.className = 'toast-custom ' + (type || 'info');
            toastTitle.textContent = title;
            toastMessage.textContent = message;
            toast.style.display = 'flex';
            toast.classList.add('show');
            clearTimeout(toast.timeout);
            toast.timeout = setTimeout(function() {
                toast.classList.remove('show');
                setTimeout(function() { toast.style.display = 'none'; }, 400);
            }, 3500);
        };
    }

    // ================================================================
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        if (!btn) return;
        btn.innerHTML = '<span class="spinner"></span> Loading...';
        btn.disabled = true;

        setTimeout(function() {
            window.location.reload();
        }, 800);
    }

    // ================================================================
    // AUTO-UPDATE DOCTOR STATUS (3 SECONDS)
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;

    function updateDoctorData() {
        if (isUpdating) return;
        isUpdating = true;

        var doctorId = <?= json_encode($doctor_id) ?>;
        var branchId = <?= json_encode($branch_id) ?>;

        var url = '/dispensary_system/frontend/api/get_online_doctors.php?branch_id=' + branchId + '&t=' + new Date().getTime();

        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var doctors = data.doctors || [];
                    var currentDoctor = null;
                    for (var i = 0; i < doctors.length; i++) {
                        if (doctors[i].id == doctorId) {
                            currentDoctor = doctors[i];
                            break;
                        }
                    }

                    if (currentDoctor) {
                        var isOnline = currentDoctor.is_online == 1;

                        var statusBadge   = document.getElementById('doctorStatusBadge');
                        var statusDisplay = document.getElementById('onlineStatusDisplay');

                        if (statusBadge) {
                            statusBadge.className = 'doctor-status-badge ' + (isOnline ? 'online' : 'offline');
                            statusBadge.textContent = isOnline ? '🟢 Online' : '⚪ Offline';
                        }

                        if (statusDisplay) {
                            statusDisplay.className = isOnline ? 'status-online' : 'status-offline';
                            statusDisplay.textContent = isOnline ? '🟢 Online' : '⚪ Offline';
                        }

                        var now = new Date();
                        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        var updateBadge = document.getElementById('updateBadge');
                        if (updateBadge) {
                            updateBadge.innerHTML = '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + timeStr;
                        }

                        if (window.lastStatus !== undefined && window.lastStatus !== isOnline) {
                            showToast('🔄 Doctor Status Updated', 'Doctor is now ' + (isOnline ? 'Online 🟢' : 'Offline ⚪'), 'info');
                        }
                        window.lastStatus = isOnline;
                    }
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Error updating doctor data:', error);
                isUpdating = false;
            });
    }

    function startAutoUpdate() {
        if (updateInterval) clearInterval(updateInterval);
        updateDoctorData();
        updateInterval = setInterval(updateDoctorData, 3000);
    }

    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) stopAutoUpdate();
        else startAutoUpdate();
    });

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(startAutoUpdate, 2000);
    });

    console.log('%c👨‍⚕️ Braick - View Doctor (V2 - SHARED HEADER)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#6EA8FE;');
    console.log('%c👨‍⚕️ Doctor: <?= htmlspecialchars($doctor['full_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Using shared reception_header.php & reception_sidebar.php', 'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c🔄 Auto-update every 3 seconds', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>