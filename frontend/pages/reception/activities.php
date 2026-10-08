<?php
// ================================================================
// FILE: frontend/pages/reception/activities.php
// RECEPTION - ACTIVITY LOGS
// ✅ Inatumia shared header + sidebar
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
$allowed_roles = ['reception', 'cashier', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    switch ($_SESSION['role']) {
        case 'doctor':     header('Location: ../doctor/dashboard.php');     break;
        case 'pharmacy':   header('Location: ../pharmacy/dashboard.php');   break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        default:           header('Location: ../login.php');                break;
    }
    exit;
}

// ================================================================
// SESSION DATA
// ================================================================
$user_id          = $_SESSION['user_id']       ?? 0;
$user_full_name   = $_SESSION['full_name']     ?? 'Staff';
$user_role        = $_SESSION['role']          ?? 'reception';
$user_branch_id   = $_SESSION['branch_id']     ?? 1;
$user_branch_name = $_SESSION['branch_name']   ?? 'Dodoma';
$user_username    = $_SESSION['username']      ?? '';
$profile_pic      = $_SESSION['profile_pic']   ?? '';

// ================================================================
// DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// BRANCH SELECTION
// ================================================================
$is_admin = ($user_role === 'admin');
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';
if (!$is_admin) {
    $selected_branch_id = $user_branch_id;
}

$display_branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    try {
        $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
        $stmt->execute([$selected_branch_id]);
        $b = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($b) $display_branch_name = $b['name'];
    } catch (Exception $e) {}
} else {
    $selected_branch_id = 'all';
}

// Branches list
$branches_list = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// SIDEBAR BADGES
// ================================================================
$patient_count         = 0;
$appointment_count     = 0;
$pending_appointments  = 0;
$today_visits          = 0;
$services_count        = 0;
$assigned_doctor_count = 0;
$lab_test_count        = 0;

try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
    $stmt->execute([$user_branch_id]);
    $patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND DATE(appointment_date) = CURDATE()");
    $stmt->execute([$user_branch_id]);
    $appointment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND status IN ('scheduled','pending')");
    $stmt->execute([$user_branch_id]);
    $pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$user_branch_id]);
    $today_visits = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT v.id) as count
        FROM visits v
        WHERE v.branch_id = ?
        AND v.doctor_id IS NOT NULL
        AND v.status IN ('assigned','pending')
    ");
    $stmt->execute([$user_branch_id]);
    $assigned_doctor_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT lt.visit_id) as count
        FROM lab_tests lt
        INNER JOIN visits v ON lt.visit_id = v.id
        WHERE lt.branch_id = ?
        AND lt.doctor_id IS NULL
        AND lt.status IN ('pending','in_progress')
        AND v.branch_id = ?
    ");
    $stmt->execute([$user_branch_id, $user_branch_id]);
    $lab_test_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM services WHERE branch_id = ? OR branch_id IS NULL");
    $stmt->execute([$user_branch_id]);
    $services_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// SYSTEM SETTINGS
// ================================================================
$site_name      = 'Braick Dispensary';
$site_logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

try {
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'site_name'");
    $stmt->execute();
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r && !empty($r['setting_value'])) $site_name = $r['setting_value'];

    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'site_logo'");
    $stmt->execute();
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r && !empty($r['setting_value'])) {
        $site_logo_path = '/dispensary_system/frontend/assets/uploads/settings/' . $r['setting_value'];
    }
} catch (Exception $e) {}

// ================================================================
// FILTERS
// ================================================================
$action_filter = isset($_GET['action_filter']) ? trim($_GET['action_filter']) : '';
$user_filter   = isset($_GET['user_filter'])   ? (int)$_GET['user_filter']   : 0;
$date_from     = isset($_GET['date_from'])     ? trim($_GET['date_from'])     : '';
$date_to       = isset($_GET['date_to'])       ? trim($_GET['date_to'])       : '';

$where  = "1=1";
$params = [];

if (!empty($action_filter)) { $where .= " AND al.action = ?"; $params[] = $action_filter; }
if ($user_filter > 0)       { $where .= " AND al.user_id = ?"; $params[] = $user_filter; }
if (!empty($date_from))     { $where .= " AND DATE(al.created_at) >= ?"; $params[] = $date_from; }
if (!empty($date_to))       { $where .= " AND DATE(al.created_at) <= ?"; $params[] = $date_to; }
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $where .= " AND al.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// ================================================================
// FETCH LOGS
// ================================================================
$logs = [];
try {
    $sql = "
        SELECT al.*, u.full_name as user_full_name, u.username as user_username,
               u.role as user_role, b.name as branch_name, p.full_name as patient_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN branches b ON al.branch_id = b.id
        LEFT JOIN patients p ON al.patient_id = p.id
        WHERE $where
        ORDER BY al.created_at DESC
        LIMIT 1000
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$total_logs = count($logs);

// ================================================================
// UNIQUE FILTERS
// ================================================================
$unique_actions = [];
try {
    $stmt = $db->query("SELECT DISTINCT action FROM activity_logs WHERE action IS NOT NULL AND action != '' ORDER BY action");
    $unique_actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$unique_users = [];
try {
    $stmt = $db->query("
        SELECT DISTINCT u.id, u.full_name, u.username
        FROM activity_logs al JOIN users u ON al.user_id = u.id
        ORDER BY u.full_name
    ");
    $unique_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// STATS
// ================================================================
$today_logs_count     = 0;
$week_logs_count      = 0;
$month_logs_count     = 0;
$total_activity_count = 0;

try {
    $branch_cond = ($selected_branch_id !== 'all' && is_numeric($selected_branch_id))
        ? " AND branch_id = " . (int)$selected_branch_id
        : "";

    $stmt = $db->query("SELECT COUNT(*) as c FROM activity_logs WHERE DATE(created_at) = CURDATE()" . $branch_cond);
    $today_logs_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

    $stmt = $db->query("SELECT COUNT(*) as c FROM activity_logs WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())" . $branch_cond);
    $week_logs_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

    $stmt = $db->query("SELECT COUNT(*) as c FROM activity_logs WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())" . $branch_cond);
    $month_logs_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

    $stmt = $db->query("SELECT COUNT(*) as c FROM activity_logs WHERE 1=1" . $branch_cond);
    $total_activity_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
} catch (Exception $e) {}

// ================================================================
// PROFILE URLS
// ================================================================
$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$default_letter = strtoupper(substr($user_full_name, 0, 1));

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/reception_header.php';
include_once __DIR__ . '/../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= (isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true') ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Activity Logs - <?= htmlspecialchars($site_name) ?></title>

    <link rel="icon" href="<?= htmlspecialchars($site_logo_path) ?>" type="image/png">
    <link rel="shortcut icon" href="<?= htmlspecialchars($site_logo_path) ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* ================================================================
           PAGE-SPECIFIC STYLES
           (Header/Sidebar styles handled by shared components)
           ================================================================ */

        /* CARD */
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 22px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
            transition: all .25s ease;
        }
        .card:hover {
            border-color: rgba(11, 94, 215, 0.25);
            box-shadow: 0 6px 20px rgba(11, 94, 215, 0.06);
        }

        /* STAT CARDS */
        .stat-card {
            border-radius: 14px;
            padding: 18px 20px;
            text-decoration: none;
            color: #fff;
            display: block;
            transition: all .3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.18);
            color: #fff;
        }
        .stat-card.blue   { background: linear-gradient(135deg, #2563EB, #1E40AF); }
        .stat-card.green  { background: linear-gradient(135deg, #10B981, #047857); }
        .stat-card.orange { background: linear-gradient(135deg, #F59E0B, #B45309); }
        .stat-card.purple { background: linear-gradient(135deg, #8B5CF6, #6D28D9); }
        .stat-card .stat-number {
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1.15;
            color: #fff;
        }
        .stat-card .stat-label {
            font-size: 0.66rem;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .stat-card .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255,255,255,0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            backdrop-filter: blur(6px);
        }

        /* FILTER INPUT */
        .filter-input {
            padding: 9px 12px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all .2s ease;
            cursor: pointer;
        }
        .filter-input:hover { border-color: #94A3B8; }
        .filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }

        /* TABLE */
        .logs-table {
            width: 100%;
            min-width: 1100px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.78rem;
        }
        .logs-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--primary);
            color: #fff;
            padding: 11px 12px;
            font-size: 0.62rem;
            text-transform: uppercase;
            font-weight: 700;
            text-align: left;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }
        .logs-table thead th:first-child { border-top-left-radius: 10px; }
        .logs-table thead th:last-child  { border-top-right-radius: 10px; }

        .logs-table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: background .2s ease;
        }
        .logs-table tbody tr:hover { background: var(--bg-body); }
        .logs-table tbody td {
            padding: 11px 12px;
            color: var(--text-primary);
            vertical-align: top;
        }

        /* ACTION BADGES */
        .action-badge {
            display: inline-block;
            padding: 3px 11px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        /* BRANCH BADGE */
        .branch-badge {
            display: inline-block;
            padding: 3px 11px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            background: var(--primary-bg);
            color: var(--primary);
        }

        /* SCROLL BUTTONS */
        .scroll-btn {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all .25s ease;
        }
        .scroll-btn:hover:not(:disabled) {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        .scroll-btn:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }

        /* SEARCH INPUT (blue gradient) */
        .table-search-input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            border: 2px solid var(--primary);
            border-radius: 10px;
            font-size: 0.85rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            outline: none;
            font-weight: 500;
            height: 42px;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
            transition: all .25s ease;
        }
        .table-search-input::placeholder { color: rgba(255,255,255,0.85); }
        .table-search-input:focus {
            box-shadow: 0 6px 18px rgba(11, 94, 215, 0.35);
            transform: translateY(-1px);
        }

        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.68rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 700; }

        /* ANIMATIONS */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp .4s ease forwards;
            opacity: 0;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .stat-card .stat-number { font-size: 1.35rem; }
        }
        @media (max-width: 768px) {
            .stat-card { padding: 14px; }
            .stat-card .stat-number { font-size: 1.15rem; }
            .stat-card .stat-icon { width: 34px; height: 34px; font-size: .95rem; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- ============================================================
         PAGE HEADER
         ============================================================ -->
    <div class="card animate-fade-in-up" style="margin-bottom:20px;background:linear-gradient(135deg, var(--primary), var(--primary-dark));border:none;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 style="color:#fff;font-size:1.5rem;font-weight:700;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <i class="fas fa-history"></i> My Activity Logs
                    <span style="background:rgba(255,255,255,0.2);color:#fff;padding:3px 12px;border-radius:999px;font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">
                        <?= strtoupper(htmlspecialchars($user_role)) ?>
                    </span>
                    <span style="background:rgba(255,255,255,0.15);color:#fff;padding:3px 12px;border-radius:999px;font-size:0.7rem;font-weight:500;">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($display_branch_name) ?>
                    </span>
                </h1>
                <p style="color:rgba(255,255,255,0.9);font-size:0.85rem;margin-top:6px;">
                    <strong><?= number_format($total_logs) ?></strong> log entries
                    <?php if (!empty($action_filter)): ?> · Action: <strong><?= htmlspecialchars($action_filter) ?></strong><?php endif; ?>
                    <?php if (!empty($date_from) || !empty($date_to)): ?>
                        · <strong><?= htmlspecialchars($date_from ?: 'Any') ?> → <?= htmlspecialchars($date_to ?: 'Any') ?></strong>
                    <?php endif; ?>
                </p>
            </div>
            <a href="dashboard.php?branch=<?= htmlspecialchars($selected_branch_id) ?>"
               style="background:rgba(255,255,255,0.2);color:#fff;padding:9px 18px;border-radius:10px;font-size:0.78rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;border:1px solid rgba(255,255,255,0.2);backdrop-filter:blur(6px);">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- ============================================================
         STATS CARDS
         ============================================================ -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">

        <a href="activities.php?branch=<?= htmlspecialchars($selected_branch_id) ?>" class="stat-card blue animate-fade-in-up">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <div class="stat-number"><?= number_format($total_activity_count) ?></div>
                    <div class="stat-label">Total Logs</div>
                </div>
                <div class="stat-icon"><i class="fas fa-list"></i></div>
            </div>
        </a>

        <a href="activities.php?branch=<?= htmlspecialchars($selected_branch_id) ?>&date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>"
           class="stat-card green animate-fade-in-up" style="animation-delay:.05s;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <div class="stat-number"><?= number_format($today_logs_count) ?></div>
                    <div class="stat-label">Today</div>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            </div>
        </a>

        <a href="activities.php?branch=<?= htmlspecialchars($selected_branch_id) ?>"
           class="stat-card orange animate-fade-in-up" style="animation-delay:.1s;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <div class="stat-number"><?= number_format($week_logs_count) ?></div>
                    <div class="stat-label">This Week</div>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
            </div>
        </a>

        <a href="activities.php?branch=<?= htmlspecialchars($selected_branch_id) ?>"
           class="stat-card purple animate-fade-in-up" style="animation-delay:.15s;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <div class="stat-number"><?= number_format($month_logs_count) ?></div>
                    <div class="stat-label">This Month</div>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            </div>
        </a>

    </div>

    <!-- ============================================================
         FILTERS
         ============================================================ -->
    <div class="card animate-fade-in-up" style="margin-bottom:20px;animation-delay:.2s;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">

            <select name="action_filter" class="filter-input">
                <option value="">📋 All Actions</option>
                <?php foreach ($unique_actions as $act): ?>
                    <option value="<?= htmlspecialchars($act) ?>" <?= $action_filter === $act ? 'selected' : '' ?>>
                        <?= htmlspecialchars($act) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="user_filter" class="filter-input">
                <option value="">👤 All Users</option>
                <?php foreach ($unique_users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $user_filter == $u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['full_name'] ?? $u['username']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="filter-input">
            <input type="date" name="date_to"   value="<?= htmlspecialchars($date_to) ?>"   class="filter-input">

            <button type="submit"
                    style="padding:9px 20px;border-radius:10px;font-weight:600;font-size:0.8rem;border:none;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(11,94,215,0.25);">
                <i class="fas fa-filter"></i> Filter
            </button>

            <a href="activities.php?branch=<?= htmlspecialchars($selected_branch_id) ?>"
               style="padding:9px 16px;border-radius:10px;font-weight:600;font-size:0.8rem;border:2px solid var(--border-color);background:transparent;color:var(--text-secondary);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ============================================================
         LOGS TABLE
         ============================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:.25s;">

        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px;padding-bottom:12px;border-bottom:2px solid var(--border-color);">

            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;flex:1;min-width:0;">
                <div style="position:relative;min-width:280px;flex:1;max-width:400px;">
                    <i class="fas fa-search" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,0.9);font-size:0.85rem;pointer-events:none;z-index:1;"></i>
                    <input type="text" id="tableSearchInput" class="table-search-input" placeholder="🔍 Auto-search logs..." autocomplete="off">
                </div>

                <h3 style="font-size:0.9rem;font-weight:600;margin:0;color:var(--text-primary);">
                    <i class="fas fa-list" style="color:var(--primary);"></i>
                    <span id="countDisplay" style="font-size:0.75rem;color:var(--text-secondary);">
                        (<strong style="color:var(--primary);"><?= count($logs) ?></strong> entries)
                    </span>
                </h3>

                <span id="searchInfo" style="font-size:0.7rem;color:var(--primary);padding:6px 12px;background:var(--primary-bg);border-radius:8px;display:none;font-weight:600;border:1px solid var(--primary);">
                    <i class="fas fa-filter"></i> <strong id="searchCount">0</strong> match
                </span>
            </div>

            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                <button type="button" class="scroll-btn" onclick="scrollTable('left')" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="scroll-btn" onclick="scrollTable('right')" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <?php if (count($logs) > 0): ?>
            <div id="tableWrap" style="overflow-x:auto;overflow-y:auto;max-height:600px;border-radius:10px;">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th style="min-width:130px;">Date/Time</th>
                            <th style="min-width:150px;">User</th>
                            <th style="min-width:140px;">Action</th>
                            <th style="min-width:300px;">Details</th>
                            <th style="min-width:120px;">Branch</th>
                            <th style="min-width:120px;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php
                        $num = 1;
                        foreach ($logs as $log):
                            // Badge color by action
                            $badge_bg = 'var(--primary-bg)';
                            $badge_color = 'var(--primary)';
                            $action_lower = strtolower($log['action'] ?? '');
                            if (strpos($action_lower, 'login') !== false) {
                                $badge_bg = 'var(--success-bg)'; $badge_color = 'var(--success)';
                            } elseif (strpos($action_lower, 'logout') !== false) {
                                $badge_bg = '#F1F5F9'; $badge_color = 'var(--text-secondary)';
                            } elseif (strpos($action_lower, 'delete') !== false || strpos($action_lower, 'deactivate') !== false) {
                                $badge_bg = 'var(--danger-bg)'; $badge_color = 'var(--danger)';
                            } elseif (strpos($action_lower, 'update') !== false || strpos($action_lower, 'edit') !== false) {
                                $badge_bg = 'var(--warning-bg)'; $badge_color = 'var(--warning)';
                            } elseif (strpos($action_lower, 'create') !== false || strpos($action_lower, 'add') !== false) {
                                $badge_bg = '#EDE9FE'; $badge_color = '#7C3AED';
                            }

                            $search_text = strtolower(
                                ($log['action'] ?? '') . ' ' . ($log['details'] ?? '') . ' ' .
                                ($log['user_full_name'] ?? '') . ' ' . ($log['user_username'] ?? '') . ' ' .
                                ($log['user_role'] ?? '') . ' ' . ($log['branch_name'] ?? '') . ' ' .
                                ($log['ip_address'] ?? '')
                            );
                        ?>
                            <tr class="log-row" data-search="<?= htmlspecialchars($search_text) ?>">
                                <td style="text-align:center;font-weight:600;color:var(--text-secondary);"><?= $num++ ?></td>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($log['created_at'])) ?></strong>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($log['user_full_name'])): ?>
                                        <strong><?= htmlspecialchars($log['user_full_name']) ?></strong>
                                        <div style="font-size:0.6rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.3px;"><?= htmlspecialchars($log['user_role'] ?? '') ?></div>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="action-badge" style="background:<?= $badge_bg ?>;color:<?= $badge_color ?>;">
                                        <?= htmlspecialchars($log['action'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="font-size:0.75rem;max-width:500px;">
                                    <?= htmlspecialchars($log['details'] ?? '-') ?>
                                </td>
                                <td>
                                    <?php if (!empty($log['branch_name'])): ?>
                                        <span class="branch-badge">🏥 <?= htmlspecialchars($log['branch_name']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.7rem;color:var(--text-secondary);font-family:'Courier New',monospace;">
                                    <?= htmlspecialchars($log['ip_address'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr id="noResults" style="display:none;">
                            <td colspan="7" style="text-align:center;padding:40px 20px;color:var(--text-secondary);">
                                <i class="fas fa-search-minus" style="font-size:2.5rem;color:var(--border-color);display:block;margin-bottom:10px;"></i>
                                <p>No logs match your search</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:40px 20px;color:var(--text-secondary);">
                <i class="fas fa-history" style="font-size:3rem;color:var(--border-color);display:block;margin-bottom:12px;"></i>
                <p style="font-size:0.95rem;font-weight:600;">No logs found</p>
                <p style="font-size:0.8rem;margin-top:4px;">Try adjusting your filters.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand"><?= htmlspecialchars($site_name) ?></span> Management System
            <span style="margin:0 6px;opacity:.4;">|</span>
            My Activity Logs
            <span style="margin:0 6px;opacity:.4;">|</span>
            <?= number_format($total_logs) ?> entries shown
            <span style="margin:0 6px;opacity:.4;">|</span>
            &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<script>
    // ================================================================
    // GLOBAL SEARCH → syncs with table search (header search already exists)
    // ================================================================
    function globalSearch() {
        var q = document.getElementById('globalSearchInput')?.value.trim();
        if (!q) return;
        var tableInput = document.getElementById('tableSearchInput');
        if (tableInput) {
            tableInput.value = q;
            tableInput.dispatchEvent(new Event('input'));
            document.getElementById('tableWrap')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    // Header search uses 'searchInput' — hook it
    document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); globalSearch(); }
    });

    // ================================================================
    // TABLE AUTO-SEARCH
    // ================================================================
    (function () {
        var input       = document.getElementById('tableSearchInput');
        var noResults   = document.getElementById('noResults');
        var countDisplay = document.getElementById('countDisplay');
        var searchInfo  = document.getElementById('searchInfo');
        var searchCount = document.getElementById('searchCount');
        if (!input) return;

        var totalRows = document.querySelectorAll('.log-row').length;

        input.addEventListener('input', function () {
            var query = this.value.toLowerCase().trim();
            var rows = document.querySelectorAll('.log-row');
            var visibleCount = 0;

            rows.forEach(function (row) {
                var searchData = row.getAttribute('data-search') || '';
                if (query === '' || searchData.indexOf(query) !== -1) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (countDisplay) {
                countDisplay.innerHTML = query === ''
                    ? '(<strong style="color:var(--primary);">' + totalRows + '</strong> entries)'
                    : '(<strong style="color:var(--primary);">' + visibleCount + '</strong> of ' + totalRows + ')';
            }
            if (searchInfo && searchCount) {
                if (query === '') { searchInfo.style.display = 'none'; }
                else { searchInfo.style.display = 'inline-flex'; searchCount.textContent = visibleCount; }
            }
            if (noResults) {
                noResults.style.display = (visibleCount === 0 && query !== '') ? '' : 'none';
            }
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { this.value = ''; this.dispatchEvent(new Event('input')); this.blur(); }
        });
    })();

    // ================================================================
    // TABLE HORIZONTAL SCROLL
    // ================================================================
    function scrollTable(dir) {
        var wrap = document.getElementById('tableWrap');
        if (wrap) wrap.scrollBy({ left: dir === 'left' ? -400 : 400, behavior: 'smooth' });
    }
    function updateScrollButtons() {
        var wrap = document.getElementById('tableWrap');
        var l = document.querySelector('button[onclick="scrollTable(\'left\')"]');
        var r = document.querySelector('button[onclick="scrollTable(\'right\')"]');
        if (!wrap || !l || !r) return;
        var sl = wrap.scrollLeft;
        var ms = wrap.scrollWidth - wrap.clientWidth;
        l.disabled = sl <= 5;
        r.disabled = sl >= ms - 5 || ms <= 0;
    }
    document.addEventListener('DOMContentLoaded', function () {
        var wrap = document.getElementById('tableWrap');
        if (wrap) {
            wrap.addEventListener('scroll', updateScrollButtons);
            setTimeout(updateScrollButtons, 200);
        }
        window.addEventListener('resize', function () { setTimeout(updateScrollButtons, 200); });
    });

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    console.log('%c📋 Reception Activities - Braick Dispensary', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
    console.log('%c✅ Inatumia shared header + sidebar', 'font-size:13px;color:#34D399;');
    console.log('%c✅ Total logs: <?= $total_logs ?>', 'font-size:13px;color:#059669;');
</script>

</body>
</html>