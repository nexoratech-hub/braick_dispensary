<?php
// ================================================================
// FILE: frontend/pages/reception/online_doctors.php
// RECEPTION - ONLINE DOCTORS LIST (BRANCH FILTERED)
// SHOWS BOTH ONLINE AND OFFLINE DOCTORS
// WITH AUTO-UPDATE (3 SECONDS) - NO REFRESH NEEDED
// ✅ USING SHARED HEADER & SIDEBAR
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
// ACCESS CHECK (Reception or Admin)
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
// SESSION DATA
// ================================================================
$user_id     = $_SESSION['user_id'];
$full_name   = $_SESSION['full_name'] ?? 'User';
$role        = $_SESSION['role'] ?? 'reception';
$branch_id   = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username    = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// HELPER: GET USER COLOR (guard against redeclaration)
// ================================================================
if (!function_exists('getUserColor')) {
    function getUserColor($name) {
        $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777', '#4F46E5', '#E11D48'];
        $index = abs(crc32($name)) % count($colors);
        return $colors[$index];
    }
}

// ================================================================
// DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

$search = $_GET['search'] ?? '';
$message = '';
$message_type = '';

// ================================================================
// FETCH DATA
// ================================================================
$doctors       = [];
$online_count  = 0;
$offline_count = 0;
$total_doctors = 0;

try {
    $db = Database::getInstance()->getConnection();

    // ---------- GET ALL DOCTORS ----------
    $query = "
        SELECT u.*, b.name as branch_name 
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.role = 'doctor' 
        AND u.status = 'active' 
        AND u.branch_id = ?
    ";
    $params = [$branch_id];

    if (!empty($search)) {
        $query .= " AND (u.full_name LIKE ? OR u.specialty LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $query .= " ORDER BY u.is_online DESC, u.full_name";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---------- COUNT ONLINE / OFFLINE ----------
    foreach ($doctors as $doc) {
        if (!empty($doc['is_online'])) {
            $online_count++;
        } else {
            $offline_count++;
        }
    }
    $total_doctors = count($doctors);

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_notifications = $stmt->fetch()['total'] ?? 0;
    }
} catch (Exception $e) {
    $unread_notifications = 0;
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
    <title>Online Doctors - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* ================================================================
           ONLINE DOCTORS - PAGE-SPECIFIC STYLES ONLY
           (Shared styles are in reception_header.php)
           ================================================================ */

        /* ---------- PAGE HEADER ---------- */
        .page-header {
            background: linear-gradient(135deg, var(--primary, #0B5ED7) 0%, var(--primary-dark, #0A4CA8) 100%);
            border-radius: 20px;
            padding: 26px 32px;
            margin-bottom: 24px;
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
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            letter-spacing: -0.02em;
            margin: 0;
        }
        .page-header .page-title i { font-size: 1.85rem; opacity: 0.95; }

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

        .role-badge-display {
            background: rgba(255,255,255,0.22);
            color: #fff;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(6px);
        }

        .header-badge {
            background: rgba(255,255,255,0.15);
            color: #fff;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(6px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.15);
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
            background: var(--bg-card, #fff);
            border-radius: 16px;
            padding: 18px 20px;
            border: 1px solid var(--border-color, #E2E8F0);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--primary, #0B5ED7);
            transition: all 0.3s;
        }
        .stat-card[data-type="online"]::before  { background: linear-gradient(90deg, #059669, #34D399); }
        .stat-card[data-type="offline"]::before { background: linear-gradient(90deg, #94A3B8, #CBD5E1); }
        .stat-card[data-type="total"]::before   { background: linear-gradient(90deg, #0B5ED7, #6EA8FE); }
        .stat-card[data-type="avail"]::before   { background: linear-gradient(90deg, #D97706, #FBBF24); }

        .stat-card:hover {
            border-color: var(--primary, #0B5ED7);
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(11, 94, 215, 0.15);
        }

        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.15;
            margin: 6px 0 2px;
            color: var(--primary, #0B5ED7);
        }
        .stat-card .stat-number.green  { color: #059669; }
        .stat-card .stat-number.gray   { color: #94A3B8; }
        .stat-card .stat-number.orange { color: #D97706; }

        .stat-card .stat-label {
            font-size: 0.72rem;
            color: var(--text-secondary, #64748B);
            font-weight: 600;
        }
        .stat-card .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 2px;
            display: inline-block;
        }
        .stat-card .stat-update {
            font-size: 0.6rem;
            color: var(--text-secondary, #64748B);
            opacity: 0.7;
            margin-top: 6px;
        }

        /* ================================================================
           DOCTOR CARD
           ================================================================ */
        .doctor-card {
            background: var(--bg-card, #fff);
            border-radius: 16px;
            padding: 18px 22px;
            border: 1px solid var(--border-color, #E2E8F0);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 12px;
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
            position: relative;
            overflow: hidden;
        }
        .doctor-card.online {
            border-left: 4px solid #059669;
        }
        .doctor-card.offline {
            border-left: 4px solid #94A3B8;
            opacity: 0.9;
        }
        .doctor-card:hover {
            border-color: var(--primary, #0B5ED7);
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(11, 94, 215, 0.15);
            opacity: 1;
        }

        .doctor-card .status-indicator {
            position: absolute;
            top: 14px;
            right: 16px;
            font-size: 0.62rem;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 12px;
            letter-spacing: 0.02em;
        }
        .doctor-card .status-indicator.online {
            background: #D1FAE5;
            color: #059669;
            border: 1px solid #6EE7B7;
        }
        .doctor-card .status-indicator.offline {
            background: #F1F5F9;
            color: #64748B;
            border: 1px solid #CBD5E1;
        }
        [data-theme="dark"] .doctor-card .status-indicator.online {
            background: #1A3A2A;
            color: #34D399;
            border-color: #1F5A3E;
        }
        [data-theme="dark"] .doctor-card .status-indicator.offline {
            background: #1E293B;
            color: #94A3B8;
            border-color: #334155;
        }

        .doctor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
            letter-spacing: 0.5px;
        }

        .doctor-card .doctor-info { flex: 1; min-width: 0; }

        .doctor-card .doctor-name {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-primary, #1E293B);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 4px;
        }

        .doctor-card .doctor-specialty {
            font-size: 0.82rem;
            color: var(--primary, #0B5ED7);
            font-weight: 500;
            margin-bottom: 2px;
        }
        .doctor-card .doctor-specialty i {
            color: var(--primary, #0B5ED7);
            opacity: 0.75;
        }

        .doctor-card .doctor-branch {
            font-size: 0.72rem;
            color: var(--text-secondary, #64748B);
            margin-bottom: 6px;
        }

        .doctor-card .doctor-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }
        .doctor-card .doctor-meta span {
            font-size: 0.68rem;
            color: var(--text-secondary, #64748B);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--bg-body, #F1F5F9);
            padding: 3px 10px;
            border-radius: 10px;
            border: 1px solid var(--border-color, #E2E8F0);
        }
        .doctor-card .doctor-meta span i {
            color: var(--primary, #0B5ED7);
            opacity: 0.75;
            font-size: 0.65rem;
        }

        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 9px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.25s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary, #0B5ED7), var(--primary-dark, #0A4CA8));
            color: #fff;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3);
            color: #fff;
        }
        .btn-success {
            background: linear-gradient(135deg, #059669, #047857);
            color: #fff;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.3);
            color: #fff;
        }
        .btn-outline {
            background: transparent;
            color: var(--text-secondary, #64748B);
            border: 2px solid var(--border-color, #E2E8F0);
        }
        .btn-outline:hover {
            background: var(--bg-body, #F1F5F9);
            border-color: var(--primary, #0B5ED7);
            color: var(--primary, #0B5ED7);
        }
        .btn-sm {
            padding: 5px 12px;
            font-size: 0.72rem;
            border-radius: 8px;
        }

        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card, #fff);
            border-radius: 16px;
            padding: 20px 22px;
            border: 1px solid var(--border-color, #E2E8F0);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
        }
        .card:hover {
            border-color: var(--primary, #0B5ED7);
            box-shadow: var(--shadow-md, 0 4px 16px rgba(0,0,0,0.08));
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color, #E2E8F0);
        }
        .card-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-primary, #1E293B);
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }
        .title-blue { color: var(--primary, #0B5ED7); }

        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: var(--text-secondary, #64748B);
        }
        .empty-state i {
            font-size: 3rem;
            display: block;
            margin-bottom: 12px;
            opacity: 0.35;
        }
        .empty-state p { font-size: 0.9rem; }

        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }

        @keyframes flashUpdate {
            0%   { background-color: rgba(11, 94, 215, 0.05); }
            30%  { background-color: rgba(11, 94, 215, 0.15); }
            70%  { background-color: rgba(11, 94, 215, 0.08); }
            100% { background-color: transparent; }
        }
        .doctor-card.status-updated {
            animation: flashUpdate 0.8s ease;
        }

        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 768px) {
            .page-header { padding: 18px 20px; border-radius: 16px; }
            .page-header .page-title { font-size: 1.3rem; }
            .doctor-card { flex-wrap: wrap; gap: 12px; padding: 14px 16px; }
            .doctor-card .doctor-avatar { width: 48px; height: 48px; font-size: 1.1rem; }
            .stat-card .stat-number { font-size: 1.6rem; }
        }
        @media (max-width: 480px) {
            .page-header .page-title { font-size: 1.1rem; }
            .doctor-card { padding: 12px 14px; }
            .doctor-card .doctor-name { font-size: 0.88rem; }
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
                <i class="fas fa-user-md"></i>
                Online Doctors
                <span class="role-badge-display">RECEPTION</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                View all doctors in <strong><?= htmlspecialchars($branch_name) ?></strong>

                <span class="header-badge">
                    <i class="fas fa-circle" style="color:#34D399;font-size:0.6rem;"></i>
                    <span id="onlineCountDisplay"><?= (int)$online_count ?></span> Online
                </span>

                <span class="header-badge">
                    <i class="fas fa-circle" style="color:#94A3B8;font-size:0.6rem;"></i>
                    <span id="offlineCountDisplay"><?= (int)$offline_count ?></span> Offline
                </span>

                <span class="header-badge">
                    <i class="fas fa-user-md"></i>
                    <span id="totalCountDisplay"><?= (int)$total_doctors ?></span> Total
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <button onclick="location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- MESSAGE -->
    <!-- ============================================================ -->
    <?php if ($message): ?>
        <div class="card animate-fade-in-up" style="max-width:1100px;margin:0 auto 16px;border-left:4px solid <?= $message_type === 'success' ? '#059669' : '#DC2626' ?>;">
            <div style="display:flex;align-items:center;gap:10px;">
                <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"
                   style="color:<?= $message_type === 'success' ? '#059669' : '#DC2626' ?>;font-size:1.2rem;"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- SEARCH INFO -->
    <!-- ============================================================ -->
    <?php if (!empty($search)): ?>
        <div style="max-width:1100px;margin:0 auto 16px;">
            <span style="display:inline-flex;align-items:center;gap:8px;background:#FEF3C7;color:#92400E;padding:6px 14px;border-radius:20px;font-size:0.78rem;font-weight:600;border:1px solid #FCD34D;">
                <i class="fas fa-search"></i> Results for: "<?= htmlspecialchars($search) ?>"
                <a href="online_doctors.php" style="color:#92400E;text-decoration:none;margin-left:4px;">
                    <i class="fas fa-times"></i>
                </a>
            </span>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- STATS CARDS -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5" style="max-width:1100px;margin:0 auto 20px;">
        <div class="stat-card animate-fade-in-up" data-type="online" style="animation-delay:0.05s;">
            <span class="stat-icon">🟢</span>
            <p class="stat-number green" id="onlineStat"><?= (int)$online_count ?></p>
            <p class="stat-label">Online Doctors</p>
            <p class="stat-update" id="onlineStatUpdate">Updated now</p>
        </div>
        <div class="stat-card animate-fade-in-up" data-type="offline" style="animation-delay:0.1s;">
            <span class="stat-icon">⚪</span>
            <p class="stat-number gray" id="offlineStat"><?= (int)$offline_count ?></p>
            <p class="stat-label">Offline Doctors</p>
            <p class="stat-update" id="offlineStatUpdate">Updated now</p>
        </div>
        <div class="stat-card animate-fade-in-up" data-type="total" style="animation-delay:0.15s;">
            <span class="stat-icon">👨‍⚕️</span>
            <p class="stat-number" id="totalStat"><?= (int)$total_doctors ?></p>
            <p class="stat-label">Total Doctors</p>
            <p class="stat-update" id="totalStatUpdate">Updated now</p>
        </div>
        <div class="stat-card animate-fade-in-up" data-type="avail" style="animation-delay:0.2s;">
            <span class="stat-icon">📊</span>
            <p class="stat-number orange" id="availabilityStat">
                <?= $total_doctors > 0 ? round(($online_count / $total_doctors) * 100) : 0 ?>%
            </p>
            <p class="stat-label">Availability</p>
            <p class="stat-update" id="availabilityStatUpdate">Updated now</p>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- DOCTORS LIST -->
    <!-- ============================================================ -->
    <div class="card animate-fade-in-up" style="max-width:1100px;margin:0 auto;" id="doctorsCard">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue"></i>
                Doctors List
                <span style="font-size:0.78rem;font-weight:500;color:var(--text-secondary);" id="doctorsCount">
                    (<?= (int)$total_doctors ?> doctors)
                </span>
            </h3>
            <span style="font-size:0.72rem;color:var(--text-secondary);" id="lastUpdateTime">
                Last updated: <?= date('h:i:s A') ?>
            </span>
        </div>

        <div id="doctorsListContainer">
            <?php if (count($doctors) > 0): ?>
                <?php foreach ($doctors as $doctor):
                    $is_online = !empty($doctor['is_online']);
                    $color = getUserColor($doctor['full_name']);
                    $status_class = $is_online ? 'online' : 'offline';
                    $status_text = $is_online ? '🟢 Online' : '⚪ Offline';
                ?>
                    <div class="doctor-card <?= $status_class ?>" data-doctor-id="<?= (int)$doctor['id'] ?>">
                        <span class="status-indicator <?= $status_class ?>"><?= $status_text ?></span>

                        <div class="doctor-avatar" style="background: <?= htmlspecialchars($color) ?>;">
                            <?= strtoupper(substr($doctor['full_name'], 0, 2)) ?>
                        </div>

                        <div class="doctor-info">
                            <div class="doctor-name">
                                Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                            </div>
                            <div class="doctor-specialty">
                                <i class="fas fa-stethoscope"></i>
                                <?= htmlspecialchars($doctor['specialty'] ?? 'General Practitioner') ?>
                            </div>
                            <div class="doctor-branch">
                                <i class="fas fa-store-alt"></i>
                                <?= htmlspecialchars($doctor['branch_name'] ?? 'Not Assigned') ?>
                            </div>
                            <div class="doctor-meta">
                                <span>
                                    <i class="fas fa-envelope"></i>
                                    <?= htmlspecialchars($doctor['email'] ?? 'N/A') ?>
                                </span>
                                <?php if (!empty($doctor['phone'])): ?>
                                    <span>
                                        <i class="fas fa-phone"></i>
                                        <?= htmlspecialchars($doctor['phone']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($doctor['last_online'])): ?>
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        Last seen: <?= date('M d, Y h:i A', strtotime($doctor['last_online'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
                            <?php if ($is_online): ?>
                                <a href="assign_doctor.php?doctor_id=<?= (int)$doctor['id'] ?>"
                                   class="btn btn-success btn-sm" title="Assign this doctor">
                                    <i class="fas fa-user-md"></i> Assign
                                </a>
                            <?php endif; ?>
                            <a href="view_doctor.php?id=<?= (int)$doctor['id'] ?>"
                               class="btn btn-primary btn-sm" title="View Doctor Profile">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-md"></i>
                    <?php if (!empty($search)): ?>
                        <p>No doctors found matching "<strong><?= htmlspecialchars($search) ?></strong>"</p>
                    <?php else: ?>
                        <p>No doctors available in <?= htmlspecialchars($branch_name) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- FOOTER -->
    <!-- ============================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            Online Doctors
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

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DATE & TIME UPDATER
    // ================================================================
    function updateDateTime() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        const ft = document.getElementById('footerTimestamp');
        if (ft) ft.textContent = 'Last updated: ' + timeStr;
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

    // ================================================================
    // GET USER COLOR (JS version)
    // ================================================================
    function getUserColor(name) {
        const colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777', '#4F46E5', '#E11D48'];
        let index = 0;
        for (let i = 0; i < name.length; i++) {
            index = (index + name.charCodeAt(i)) % colors.length;
        }
        return colors[index];
    }

    // ================================================================
    // ESCAPE HTML
    // ================================================================
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ================================================================
    // AUTO-UPDATE DOCTORS LIST (3 SECONDS)
    // ================================================================
    let updateInterval = null;
    let isUpdating = false;
    let lastDoctorData = '';

    function updateDoctorsList() {
        if (isUpdating) return;
        isUpdating = true;

        const branchId = <?= json_encode($branch_id) ?>;
        const searchQuery = <?= json_encode($search) ?>;

        let url = '/dispensary_system/frontend/api/get_online_doctors.php?branch_id=' + branchId + '&t=' + Date.now();
        if (searchQuery) {
            url += '&search=' + encodeURIComponent(searchQuery);
        }

        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const doctors = data.doctors || [];
                    const onlineCount = data.online_count || 0;
                    const offlineCount = data.offline_count || 0;
                    const totalDoctors = data.total_doctors || 0;

                    // Update stats
                    document.getElementById('onlineStat').textContent = onlineCount;
                    document.getElementById('offlineStat').textContent = offlineCount;
                    document.getElementById('totalStat').textContent = totalDoctors;
                    document.getElementById('onlineCountDisplay').textContent = onlineCount;
                    document.getElementById('offlineCountDisplay').textContent = offlineCount;
                    document.getElementById('totalCountDisplay').textContent = totalDoctors;
                    document.getElementById('doctorsCount').textContent = '(' + totalDoctors + ' doctors)';

                    const availability = totalDoctors > 0 ? Math.round((onlineCount / totalDoctors) * 100) : 0;
                    document.getElementById('availabilityStat').textContent = availability + '%';

                    const now = new Date();
                    const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    document.getElementById('onlineStatUpdate').textContent = 'Updated ' + timeStr;
                    document.getElementById('offlineStatUpdate').textContent = 'Updated ' + timeStr;
                    document.getElementById('totalStatUpdate').textContent = 'Updated ' + timeStr;
                    document.getElementById('availabilityStatUpdate').textContent = 'Updated ' + timeStr;
                    document.getElementById('updateBadge').innerHTML = '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + timeStr;
                    document.getElementById('lastUpdateTime').textContent = 'Last updated: ' + timeStr;

                    // Detect change
                    const dataHash = JSON.stringify(doctors);

                    if (dataHash !== lastDoctorData) {
                        lastDoctorData = dataHash;
                        const container = document.getElementById('doctorsListContainer');

                        if (doctors.length > 0) {
                            let html = '';
                            doctors.forEach(doc => {
                                const isOnline = doc.is_online == 1;
                                const color = getUserColor(doc.full_name);
                                const statusClass = isOnline ? 'online' : 'offline';
                                const statusText = isOnline ? '🟢 Online' : '⚪ Offline';
                                const specialty = doc.specialty || 'General Practitioner';
                                const branchName = doc.branch_name || 'Not Assigned';
                                const email = doc.email || 'N/A';
                                const phone = doc.phone || '';
                                const lastOnline = doc.last_online
                                    ? new Date(doc.last_online).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
                                    : '';

                                html += `
                                    <div class="doctor-card ${statusClass} status-updated" data-doctor-id="${doc.id}">
                                        <span class="status-indicator ${statusClass}">${statusText}</span>
                                        <div class="doctor-avatar" style="background: ${color};">
                                            ${escapeHtml(doc.full_name.substring(0, 2).toUpperCase())}
                                        </div>
                                        <div class="doctor-info">
                                            <div class="doctor-name">Dr. ${escapeHtml(doc.full_name)}</div>
                                            <div class="doctor-specialty"><i class="fas fa-stethoscope"></i> ${escapeHtml(specialty)}</div>
                                            <div class="doctor-branch"><i class="fas fa-store-alt"></i> ${escapeHtml(branchName)}</div>
                                            <div class="doctor-meta">
                                                <span><i class="fas fa-envelope"></i> ${escapeHtml(email)}</span>
                                                ${phone ? `<span><i class="fas fa-phone"></i> ${escapeHtml(phone)}</span>` : ''}
                                                ${lastOnline ? `<span><i class="fas fa-clock"></i> Last seen: ${escapeHtml(lastOnline)}</span>` : ''}
                                            </div>
                                        </div>
                                        <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
                                            ${isOnline ? `<a href="assign_doctor.php?doctor_id=${doc.id}" class="btn btn-success btn-sm"><i class="fas fa-user-md"></i> Assign</a>` : ''}
                                            <a href="view_doctor.php?id=${doc.id}" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> View</a>
                                        </div>
                                    </div>
                                `;
                            });
                            container.innerHTML = html;

                            setTimeout(() => {
                                document.querySelectorAll('.doctor-card.status-updated').forEach(el => {
                                    el.classList.remove('status-updated');
                                });
                            }, 900);

                            showToast('🔄 Updated', onlineCount + ' online, ' + offlineCount + ' offline', 'info');
                        } else {
                            container.innerHTML = `
                                <div class="empty-state">
                                    <i class="fas fa-user-md"></i>
                                    ${searchQuery
                                        ? `<p>No doctors found matching "<strong>${escapeHtml(searchQuery)}</strong>"</p>`
                                        : `<p>No doctors available</p>`}
                                </div>
                            `;
                        }
                    }
                }
                isUpdating = false;
            })
            .catch(error => {
                console.error('Error updating doctors list:', error);
                isUpdating = false;
            });
    }

    // ================================================================
    // START / STOP AUTO-UPDATE
    // ================================================================
    function startAutoUpdate() {
        if (updateInterval) clearInterval(updateInterval);
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
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stopAutoUpdate();
        else startAutoUpdate();
    });

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(startAutoUpdate, 2000);
    });

    console.log('%c👨‍⚕️ Braick - Online Doctors (Auto-Update Every 3s)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🟢 Online: <?= (int)$online_count ?>', 'font-size:13px; color:#059669;');
    console.log('%c⚪ Offline: <?= (int)$offline_count ?>', 'font-size:13px; color:#94A3B8;');
    console.log('%c👨‍⚕️ Total: <?= (int)$total_doctors ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Using shared header & sidebar', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>