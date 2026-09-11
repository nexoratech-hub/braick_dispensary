<?php
// ================================================================
// FILE: frontend/pages/reception/activities.php
// RECEPTION - ACTIVITY LOGS
// With EXACT sidebar from reception_sidebar.php
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['reception', 'cashier', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Staff';
$user_role = $_SESSION['role'] ?? 'reception';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

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

// ================================================================
// GET BRANCHES
// ================================================================
$branches_list = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// SIDEBAR BADGES - SAME AS reception_sidebar.php
// ================================================================
$patient_count = 0;
$appointment_count = 0;
$pending_appointments = 0;
$today_visits = 0;
$services_count = 0;
$assigned_doctor_count = 0;
$lab_test_count = 0;

try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
    $stmt->execute([$user_branch_id]);
    $patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND DATE(appointment_date) = CURDATE()");
    $stmt->execute([$user_branch_id]);
    $appointment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ? AND status IN ('scheduled', 'pending')");
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
        AND v.status IN ('assigned', 'pending')
    ");
    $stmt->execute([$user_branch_id]);
    $assigned_doctor_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT lt.visit_id) as count 
        FROM lab_tests lt 
        INNER JOIN visits v ON lt.visit_id = v.id
        WHERE lt.branch_id = ? 
        AND lt.doctor_id IS NULL
        AND lt.status IN ('pending', 'in_progress')
        AND v.branch_id = ?
    ");
    $stmt->execute([$user_branch_id, $user_branch_id]);
    $lab_test_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM services WHERE branch_id = ? OR branch_id IS NULL");
    $stmt->execute([$user_branch_id]);
    $services_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// SYSTEM SETTINGS (site name + logo)
// ================================================================
$site_name = 'Braick Dispensary';
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
$user_filter = isset($_GET['user_filter']) ? (int)$_GET['user_filter'] : 0;
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

$where = "1=1";
$params = [];

if (!empty($action_filter)) { $where .= " AND al.action = ?"; $params[] = $action_filter; }
if ($user_filter > 0) { $where .= " AND al.user_id = ?"; $params[] = $user_filter; }
if (!empty($date_from)) { $where .= " AND DATE(al.created_at) >= ?"; $params[] = $date_from; }
if (!empty($date_to)) { $where .= " AND DATE(al.created_at) <= ?"; $params[] = $date_to; }
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
// UNIQUE ACTIONS / USERS
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
$today_logs_count = 0; $week_logs_count = 0; $month_logs_count = 0; $total_activity_count = 0;

try {
    $branch_cond = ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) ? " AND branch_id = " . (int)$selected_branch_id : "";
    
    $stmt = $db->query("SELECT COUNT(*) as c FROM activity_logs WHERE DATE(created_at) = CURDATE()" . $branch_cond);
    $today_logs_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    
    $stmt = $db->query("SELECT COUNT(*) as c FROM activity_logs WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())" . $branch_cond);
    $week_logs_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    
    $stmt = $db->query("SELECT COUNT(*) as c FROM activity_logs WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())" . $branch_cond);
    $month_logs_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    
    $stmt = $db->query("SELECT COUNT(*) as c FROM activity_logs WHERE 1=1" . $branch_cond);
    $total_activity_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
} catch (Exception $e) {}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$default_letter = strtoupper(substr($user_full_name, 0, 1));

// ================================================================
// INCLUDE SHARED SIDEBAR (exact same as reception_sidebar.php)
// ================================================================
include_once __DIR__ . '/../../components/reception_sidebar.php';

// ================================================================
// INCLUDE HEADER CSS (for top-nav styles)
// ================================================================
include_once __DIR__ . '/../../components/reception_header.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Activity Logs - <?= htmlspecialchars($site_name) ?></title>
    <link rel="icon" href="<?= $site_logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $site_logo_path ?>" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* Additional styles for activities page */
        .filter-input {
            padding: 8px 12px;
            border: 2px solid var(--border-color, #E2E8F0);
            border-radius: 8px;
            font-size: 0.8rem;
            background: var(--bg-card, #fff);
            color: var(--text-primary, #1E293B);
            outline: none;
        }
        .filter-input:focus { border-color: var(--primary, #0B5ED7); }
    </style>
</head>
<body>

<!-- ============================================================ -->
<!-- EMBEDDED RECEPTION HEADER (matches top-nav from reception_header.php) -->
<!-- ============================================================ -->
<header class="top-nav">
    <div style="display:flex;align-items:center;gap:12px;flex:1;">
        <button id="sidebarToggle" class="sidebar-toggle-btn">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search" style="color:var(--text-secondary);margin-left:12px;font-size:0.8rem;"></i>
            <input type="text" id="globalSearchInput" placeholder="Search...">
            <button class="search-btn" onclick="globalSearch()"><i class="fas fa-search"></i></button>
        </div>
    </div>
    
    <div class="header-right">
        <select class="branch-selector" onchange="switchBranch(this.value)" <?= !$is_admin ? 'disabled' : '' ?>>
            <?php if ($is_admin): ?>
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All</option>
            <?php endif; ?>
            <?php foreach ($branches_list as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime">
            <i class="fas fa-clock clock-icon"></i>
            <span id="clockDisplay"><?= date('d M Y • H:i:s') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <a href="profile.php" style="text-decoration:none;">
            <?php if (!empty($profile_pic)): ?>
                <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <?php endif; ?>
            <div class="avatar-default" style="<?= !empty($profile_pic) ? 'display:none;' : 'display:flex;' ?>">
                <?= $default_letter ?>
            </div>
        </a>
    </div>
</header>

<!-- ============================================================ -->
<!-- MAIN CONTENT -->
<!-- ============================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="card animate-fade-in-up" style="margin-bottom:20px;background:linear-gradient(135deg, var(--primary), var(--primary-dark));border:none;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 style="color:white;font-size:1.5rem;font-weight:700;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <i class="fas fa-history"></i> My Activity Logs
                    <span style="background:rgba(255,255,255,0.2);color:white;padding:2px 10px;border-radius:20px;font-size:0.6rem;font-weight:600;text-transform:uppercase;">
                        <?= strtoupper($user_role) ?>
                    </span>
                    <span style="background:rgba(255,255,255,0.15);color:white;padding:2px 12px;border-radius:20px;font-size:0.7rem;font-weight:500;">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($display_branch_name) ?>
                    </span>
                </h1>
                <p style="color:rgba(255,255,255,0.85);font-size:0.85rem;margin-top:6px;">
                    <strong><?= number_format($total_logs) ?></strong> log entries
                    <?php if (!empty($action_filter)): ?> · Action: <strong><?= htmlspecialchars($action_filter) ?></strong><?php endif; ?>
                    <?php if (!empty($date_from) || !empty($date_to)): ?>
                        · <strong><?= htmlspecialchars($date_from ?: 'Any') ?> → <?= htmlspecialchars($date_to ?: 'Any') ?></strong>
                    <?php endif; ?>
                </p>
            </div>
            <a href="dashboard.php?branch=<?= $selected_branch_id ?>" style="background:var(--success);color:white;padding:8px 18px;border-radius:20px;font-size:0.75rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- STATS -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
        <a href="activities.php?branch=<?= $selected_branch_id ?>" class="stat-card blue">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div>
                    <div class="stat-number"><?= number_format($total_activity_count) ?></div>
                    <div class="stat-label">Total Logs</div>
                </div>
                <div class="stat-icon"><i class="fas fa-list"></i></div>
            </div>
        </a>
        <a href="activities.php?branch=<?= $selected_branch_id ?>&date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>" class="stat-card green">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div>
                    <div class="stat-number"><?= number_format($today_logs_count) ?></div>
                    <div class="stat-label">Today</div>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            </div>
        </a>
        <a href="activities.php?branch=<?= $selected_branch_id ?>" class="stat-card orange">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div>
                    <div class="stat-number"><?= number_format($week_logs_count) ?></div>
                    <div class="stat-label">This Week</div>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
            </div>
        </a>
        <a href="activities.php?branch=<?= $selected_branch_id ?>" class="stat-card purple">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div>
                    <div class="stat-number"><?= number_format($month_logs_count) ?></div>
                    <div class="stat-label">This Month</div>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            </div>
        </a>
    </div>

    <!-- FILTERS -->
    <div class="card" style="margin-bottom:20px;">
        <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            
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
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="filter-input">
            
            <button type="submit" style="padding:8px 20px;border-radius:8px;font-weight:600;font-size:0.8rem;border:none;background:var(--primary);color:white;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-filter"></i> Filter
            </button>
            
            <a href="activities.php?branch=<?= $selected_branch_id ?>" style="padding:8px 16px;border-radius:8px;font-weight:600;font-size:0.8rem;border:2px solid var(--border-color);background:transparent;color:var(--text-secondary);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- LOGS TABLE -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px;padding-bottom:10px;border-bottom:2px solid var(--border-color);">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;flex:1;min-width:0;">
                <div style="position:relative;min-width:280px;flex:1;max-width:400px;">
                    <i class="fas fa-search" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,0.9);font-size:0.85rem;pointer-events:none;z-index:1;"></i>
                    <input type="text" id="tableSearchInput" placeholder="🔍 Auto-search logs..." autocomplete="off" style="width:100%;padding:10px 14px 10px 40px;border:2px solid var(--primary);border-radius:10px;font-size:0.85rem;background:linear-gradient(135deg, var(--primary), var(--primary-dark));color:white;outline:none;font-weight:500;height:42px;box-shadow:0 4px 12px rgba(11, 94, 215, 0.25);">
                </div>
                
                <h3 style="font-size:0.9rem;font-weight:600;margin:0;color:var(--text-primary);">
                    <i class="fas fa-list" style="color:var(--primary);"></i> 
                    <span id="countDisplay" style="font-size:0.75rem;color:var(--text-secondary);">(<strong style="color:var(--primary);"><?= count($logs) ?></strong> entries)</span>
                </h3>
                
                <span id="searchInfo" style="font-size:0.7rem;color:var(--primary);padding:6px 12px;background:var(--primary-bg);border-radius:8px;display:none;font-weight:600;border:1px solid var(--primary);">
                    <i class="fas fa-filter"></i> <strong id="searchCount">0</strong> match
                </span>
            </div>
            
            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                <button type="button" id="scrollBtnLeft" onclick="scrollTable('left')" title="Scroll Left" style="width:38px;height:38px;border-radius:8px;border:2px solid var(--border-color);background:var(--bg-card);color:var(--text-primary);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all 0.3s;">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" id="scrollBtnRight" onclick="scrollTable('right')" title="Scroll Right" style="width:38px;height:38px;border-radius:8px;border:2px solid var(--border-color);background:var(--bg-card);color:var(--text-primary);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all 0.3s;">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <?php if (count($logs) > 0): ?>
            <div id="tableWrap" style="overflow-x:auto;overflow-y:auto;max-height:600px;">
                <table style="width:100%;min-width:1100px;border-collapse:separate;border-spacing:0;font-size:0.78rem;">
                    <thead>
                        <tr>
                            <th style="position:sticky;top:0;z-index:10;background:var(--primary);color:white;padding:10px 12px;font-size:0.62rem;text-transform:uppercase;font-weight:700;text-align:left;width:40px;">#</th>
                            <th style="position:sticky;top:0;z-index:10;background:var(--primary);color:white;padding:10px 12px;font-size:0.62rem;text-transform:uppercase;font-weight:700;text-align:left;min-width:130px;">Date/Time</th>
                            <th style="position:sticky;top:0;z-index:10;background:var(--primary);color:white;padding:10px 12px;font-size:0.62rem;text-transform:uppercase;font-weight:700;text-align:left;min-width:150px;">User</th>
                            <th style="position:sticky;top:0;z-index:10;background:var(--primary);color:white;padding:10px 12px;font-size:0.62rem;text-transform:uppercase;font-weight:700;text-align:left;min-width:140px;">Action</th>
                            <th style="position:sticky;top:0;z-index:10;background:var(--primary);color:white;padding:10px 12px;font-size:0.62rem;text-transform:uppercase;font-weight:700;text-align:left;min-width:300px;">Details</th>
                            <th style="position:sticky;top:0;z-index:10;background:var(--primary);color:white;padding:10px 12px;font-size:0.62rem;text-transform:uppercase;font-weight:700;text-align:left;min-width:120px;">Branch</th>
                            <th style="position:sticky;top:0;z-index:10;background:var(--primary);color:white;padding:10px 12px;font-size:0.62rem;text-transform:uppercase;font-weight:700;text-align:left;min-width:120px;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php 
                        $num = 1;
                        foreach ($logs as $log): 
                            $badge_bg = 'var(--primary-bg)'; $badge_color = 'var(--primary)';
                            $action_lower = strtolower($log['action'] ?? '');
                            if (strpos($action_lower, 'login') !== false) { $badge_bg = 'var(--success-bg)'; $badge_color = 'var(--success)'; }
                            elseif (strpos($action_lower, 'logout') !== false) { $badge_bg = '#F1F5F9'; $badge_color = 'var(--text-secondary)'; }
                            elseif (strpos($action_lower, 'delete') !== false || strpos($action_lower, 'deactivate') !== false) { $badge_bg = 'var(--danger-bg)'; $badge_color = 'var(--danger)'; }
                            elseif (strpos($action_lower, 'update') !== false || strpos($action_lower, 'edit') !== false) { $badge_bg = 'var(--warning-bg)'; $badge_color = 'var(--warning)'; }
                            elseif (strpos($action_lower, 'create') !== false || strpos($action_lower, 'add') !== false) { $badge_bg = '#EDE9FE'; $badge_color = '#7C3AED'; }
                            
                            $search_text = strtolower(
                                ($log['action'] ?? '') . ' ' . ($log['details'] ?? '') . ' ' .
                                ($log['user_full_name'] ?? '') . ' ' . ($log['user_username'] ?? '') . ' ' .
                                ($log['user_role'] ?? '') . ' ' . ($log['branch_name'] ?? '') . ' ' .
                                ($log['ip_address'] ?? '')
                            );
                        ?>
                            <tr class="log-row" data-search="<?= htmlspecialchars($search_text) ?>" style="border-bottom:1px solid var(--border-color);">
                                <td style="padding:10px 12px;text-align:center;"><?= $num++ ?></td>
                                <td style="padding:10px 12px;">
                                    <strong><?= date('d/m/Y', strtotime($log['created_at'])) ?></strong>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                                </td>
                                <td style="padding:10px 12px;">
                                    <?php if (!empty($log['user_full_name'])): ?>
                                        <strong><?= htmlspecialchars($log['user_full_name']) ?></strong>
                                        <div style="font-size:0.6rem;color:var(--text-secondary);"><?= htmlspecialchars($log['user_role'] ?? '') ?></div>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">System</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:10px 12px;">
                                    <span style="background:<?= $badge_bg ?>;color:<?= $badge_color ?>;padding:2px 10px;border-radius:12px;font-size:0.6rem;font-weight:600;display:inline-block;">
                                        <?= htmlspecialchars($log['action'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="padding:10px 12px;font-size:0.75rem;max-width:500px;"><?= htmlspecialchars($log['details'] ?? '-') ?></td>
                                <td style="padding:10px 12px;">
                                    <?php if (!empty($log['branch_name'])): ?>
                                        <span style="background:var(--primary-bg);color:var(--primary);padding:2px 10px;border-radius:12px;font-size:0.6rem;font-weight:600;">🏥 <?= htmlspecialchars($log['branch_name']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:10px 12px;font-size:0.7rem;color:var(--text-secondary);font-family:monospace;"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
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

    <footer class="footer">
        <p>
            <span class="footer-brand"><?= htmlspecialchars($site_name) ?></span> Management System
            <span>|</span> My Activity Logs
            <span>|</span> <?= number_format($total_logs) ?> entries shown
            <span>|</span> &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<script>
// GLOBAL SEARCH
function globalSearch() {
    var q = document.getElementById('globalSearchInput').value.trim();
    if (q) {
        var tableInput = document.getElementById('tableSearchInput');
        if (tableInput) {
            tableInput.value = q;
            tableInput.dispatchEvent(new Event('input'));
            document.getElementById('tableWrap')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}
document.getElementById('globalSearchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); globalSearch(); }
});

// AUTO SEARCH - TABLE FILTER
(function() {
    var input = document.getElementById('tableSearchInput');
    var noResults = document.getElementById('noResults');
    var countDisplay = document.getElementById('countDisplay');
    var searchInfo = document.getElementById('searchInfo');
    var searchCount = document.getElementById('searchCount');
    if (!input) return;
    
    var totalRows = document.querySelectorAll('.log-row').length;
    
    input.addEventListener('input', function() {
        var query = this.value.toLowerCase().trim();
        var rows = document.querySelectorAll('.log-row');
        var visibleCount = 0;
        
        rows.forEach(function(row) {
            var searchData = row.getAttribute('data-search') || '';
            if (query === '' || searchData.indexOf(query) !== -1) {
                row.style.display = ''; visibleCount++;
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
    
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { this.value = ''; this.dispatchEvent(new Event('input')); this.blur(); }
    });
})();

// SCROLL
function scrollTable(dir) {
    var wrap = document.getElementById('tableWrap');
    if (wrap) wrap.scrollBy({ left: dir === 'left' ? -400 : 400, behavior: 'smooth' });
}
function updateScrollButtons() {
    var wrap = document.getElementById('tableWrap');
    var l = document.getElementById('scrollBtnLeft');
    var r = document.getElementById('scrollBtnRight');
    if (!wrap || !l || !r) return;
    var sl = wrap.scrollLeft, ms = wrap.scrollWidth - wrap.clientWidth;
    l.disabled = sl <= 5; r.disabled = sl >= ms - 5 || ms <= 0;
    l.style.opacity = l.disabled ? '0.35' : '1';
    r.style.opacity = r.disabled ? '0.35' : '1';
}
document.addEventListener('DOMContentLoaded', function() {
    var wrap = document.getElementById('tableWrap');
    if (wrap) { wrap.addEventListener('scroll', updateScrollButtons); setTimeout(updateScrollButtons, 200); }
    window.addEventListener('resize', function() { setTimeout(updateScrollButtons, 200); });
});

// BRANCH
function switchBranch(branchId) {
    var url = new URL(window.location.href);
    url.searchParams.set('branch', branchId);
    window.location.href = url.toString();
}

// DARK MODE
(function() {
    var t = document.getElementById('darkModeToggle');
    var icon = document.getElementById('darkIcon');
    var text = document.getElementById('darkText');
    var html = document.documentElement;
    if (localStorage.getItem('darkMode') === 'true') {
        html.setAttribute('data-theme', 'dark');
        if (icon) icon.className = 'fas fa-sun';
        if (text) text.textContent = 'Light';
    }
    t?.addEventListener('click', function() {
        if (html.getAttribute('data-theme') === 'dark') {
            html.removeAttribute('data-theme');
            if (icon) icon.className = 'fas fa-moon';
            if (text) text.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
            document.cookie = "dark_mode=false; path=/";
        } else {
            html.setAttribute('data-theme', 'dark');
            if (icon) icon.className = 'fas fa-sun';
            if (text) text.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });
})();

// CLOCK
setInterval(function() {
    var now = new Date();
    var d = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    var tm = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var el = document.getElementById('clockDisplay');
    if (el) el.textContent = d + ' • ' + tm;
}, 1000);

console.log('%c📋 Reception Activities - Braick Dispensary', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Sidebar from reception_sidebar.php (exact)', 'font-size:13px; color:#34D399;');
console.log('%c✅ Total logs: <?= $total_logs ?>', 'font-size:13px; color:#059669;');
</script>

</body>
</html>