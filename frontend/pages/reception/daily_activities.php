<?php
// ================================================================
// FILE: frontend/pages/reception/daily_activities.php
// RECEPTIONIST - MY DAILY ACTIVITIES (FINAL - BRANCH ONLY HEADER)
// ================================================================

date_default_timezone_set('Africa/Dar_es_Salaam');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'reception') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: ../admin/dashboard.php'); break;
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// USER DATA
$user_id          = $_SESSION['user_id'];
$user_full_name   = $_SESSION['full_name'] ?? 'Receptionist';
$user_role        = $_SESSION['role'] ?? 'reception';
$user_branch_id   = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username    = $_SESSION['username'] ?? '';
$profile_pic      = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$selected_branch_id = $_GET['branch'] ?? $user_branch_id;

// ================================================================
// AUTO-CREATE TABLE
// ================================================================
try {
    $db->exec("CREATE TABLE IF NOT EXISTS daily_activities (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        branch_id INT(11) DEFAULT NULL,
        activity_date DATE NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        category VARCHAR(100) DEFAULT NULL,
        start_time TIME DEFAULT NULL,
        end_time TIME DEFAULT NULL,
        duration_minutes INT(11) DEFAULT NULL,
        status ENUM('pending','in_progress','completed','cancelled') DEFAULT 'completed',
        priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
        patient_id INT(11) DEFAULT NULL,
        visit_id INT(11) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_date (user_id, activity_date),
        INDEX idx_activity_date (activity_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    error_log("daily_activities table check: " . $e->getMessage());
}

$flash_message = '';
$flash_type = '';

// ================================================================
// HANDLE ADD ACTIVITY
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_activity') {
    try {
        $act_date = date('Y-m-d');
        $act_title = trim($_POST['title'] ?? '');
        $act_desc = trim($_POST['description'] ?? '');
        $act_category = trim($_POST['category'] ?? '');
        $act_start = date('H:i:s');
        $act_status = $_POST['status'] ?? 'completed';
        $act_priority = $_POST['priority'] ?? 'medium';

        if (empty($act_title)) throw new Exception("Activity title is required.");

        $stmt = $db->prepare("
            INSERT INTO daily_activities 
                (user_id, branch_id, activity_date, title, description, category,
                 start_time, end_time, duration_minutes, status, priority, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id, $user_branch_id, $act_date,
            $act_title, $act_desc, $act_category,
            $act_start, $act_status, $act_priority
        ]);

        $flash_message = "✅ Activity added successfully!";
        $flash_type = 'success';
        
        echo '<script>setTimeout(function(){ window.location.href = "daily_activities.php"; }, 1500);</script>';
        
    } catch (Exception $e) {
        $flash_message = "❌ Error: " . $e->getMessage();
        $flash_type = 'error';
    }
}

// ================================================================
// HANDLE EDIT ACTIVITY
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_activity') {
    try {
        $act_id = (int)($_POST['activity_id'] ?? 0);
        $act_title = trim($_POST['title'] ?? '');
        $act_desc = trim($_POST['description'] ?? '');
        $act_category = trim($_POST['category'] ?? '');
        $act_status = $_POST['status'] ?? 'completed';
        $act_priority = $_POST['priority'] ?? 'medium';

        if ($act_id <= 0) throw new Exception("Invalid activity.");
        if (empty($act_title)) throw new Exception("Activity title is required.");

        $stmt = $db->prepare("SELECT id FROM daily_activities WHERE id = ? AND user_id = ?");
        $stmt->execute([$act_id, $user_id]);
        if (!$stmt->fetch()) throw new Exception("Activity not found.");

        $stmt = $db->prepare("
            UPDATE daily_activities 
            SET title = ?, description = ?, category = ?, 
                status = ?, priority = ?, updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([
            $act_title, $act_desc, $act_category,
            $act_status, $act_priority,
            $act_id, $user_id
        ]);

        $flash_message = "✅ Activity updated successfully!";
        $flash_type = 'success';
        
        echo '<script>setTimeout(function(){ window.location.href = "daily_activities.php"; }, 1500);</script>';
        
    } catch (Exception $e) {
        $flash_message = "❌ Error: " . $e->getMessage();
        $flash_type = 'error';
    }
}

// ================================================================
// FILTERS
// ================================================================
$filter_type     = $_GET['filter'] ?? 'today';
$date_from       = $_GET['date_from'] ?? date('Y-m-d');
$date_to         = $_GET['date_to'] ?? date('Y-m-d');
$time_from       = $_GET['time_from'] ?? '';
$time_to         = $_GET['time_to'] ?? '';
$search_global   = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');

function toAmPm($time24) {
    if (empty($time24)) return '';
    $ts = strtotime($time24);
    return $ts ? date('g:i A', $ts) : $time24;
}

$where_date = '';
$date_params = [];

switch ($filter_type) {
    case 'all': $where_date = "1=1"; break;
    case 'today': $where_date = "activity_date = CURDATE()"; break;
    case '1d': $where_date = "activity_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)"; break;
    case '1w': $where_date = "activity_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)"; break;
    case '1m': $where_date = "activity_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)"; break;
    case '3m': $where_date = "activity_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)"; break;
    case '6m': $where_date = "activity_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)"; break;
    case '1y': $where_date = "activity_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)"; break;
    case 'custom':
        $where_date = "activity_date BETWEEN ? AND ?";
        $date_params = [$date_from, $date_to];
        break;
    default: $where_date = "activity_date = CURDATE()";
}

$where = ["user_id = ?", $where_date];
$params = [$user_id];
$params = array_merge($params, $date_params);

if (!empty($time_from)) { $where[] = "start_time >= ?"; $params[] = $time_from . ':00'; }
if (!empty($time_to)) { $where[] = "start_time <= ?"; $params[] = $time_to . ':00'; }
if (!empty($category_filter)) { $where[] = "category = ?"; $params[] = $category_filter; }
if (!empty($search_global)) {
    $where[] = "(title LIKE ? OR description LIKE ? OR category LIKE ?)";
    $params[] = "%$search_global%";
    $params[] = "%$search_global%";
    $params[] = "%$search_global%";
}

$where_clause = implode(" AND ", $where);

$sql = "SELECT id, activity_date, title, description, category,
               start_time, end_time, duration_minutes, status, priority, created_at
        FROM daily_activities
        WHERE $where_clause
        ORDER BY activity_date DESC, start_time DESC, id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// STATS
// ================================================================
$today_activities = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM daily_activities WHERE user_id = ? AND activity_date = CURDATE()");
    $stmt->execute([$user_id]);
    $today_activities = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

$this_week_activities = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM daily_activities WHERE user_id = ? AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)");
    $stmt->execute([$user_id]);
    $this_week_activities = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

$this_month_activities = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM daily_activities WHERE user_id = ? AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
    $stmt->execute([$user_id]);
    $this_month_activities = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

$all_time_activities = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM daily_activities WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $all_time_activities = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

$categories = [];
try {
    $stmt = $db->prepare("SELECT DISTINCT category FROM daily_activities WHERE user_id = ? AND category IS NOT NULL AND category != '' ORDER BY category");
    $stmt->execute([$user_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$predefined_categories = [
    'Patient Registration'  => ['icon' => 'fa-user-plus',       'color' => '#0B5ED7'],
    'Patient Check-in'      => ['icon' => 'fa-user-check',      'color' => '#059669'],
    'Appointment Booking'   => ['icon' => 'fa-calendar-plus',   'color' => '#7C3AED'],
    'Appointment Reschedule'=> ['icon' => 'fa-calendar-alt',    'color' => '#D97706'],
    'Phone Inquiry'         => ['icon' => 'fa-phone',           'color' => '#0D9488'],
    'Patient Inquiry'       => ['icon' => 'fa-question-circle', 'color' => '#0B5ED7'],
    'Billing Assistance'    => ['icon' => 'fa-file-invoice',    'color' => '#D97706'],
    'Doctor Assignment'     => ['icon' => 'fa-user-md',         'color' => '#059669'],
    'Patient Records'       => ['icon' => 'fa-folder-open',     'color' => '#7C3AED'],
    'Data Entry'            => ['icon' => 'fa-keyboard',        'color' => '#0B5ED7'],
    'Cash Handling'         => ['icon' => 'fa-money-bill-wave', 'color' => '#059669'],
    'Report'                => ['icon' => 'fa-file-alt',        'color' => '#64748B'],
    'Meeting'               => ['icon' => 'fa-users',           'color' => '#D97706'],
    'Training'              => ['icon' => 'fa-graduation-cap',  'color' => '#0D9488'],
    'Other'                 => ['icon' => 'fa-ellipsis-h',      'color' => '#64748B'],
];

function formatDuration($minutes) {
    if (!$minutes || $minutes <= 0) return '—';
    $h = floor($minutes / 60); $m = $minutes % 60;
    if ($h > 0 && $m > 0) return "{$h}h {$m}m";
    if ($h > 0) return "{$h}h";
    return "{$m}m";
}
function statusMeta($status) {
    $map = [
        'completed'   => ['label' => 'Completed',   'icon' => 'fa-check-circle', 'class' => 'completed'],
        'in_progress' => ['label' => 'In Progress', 'icon' => 'fa-spinner',      'class' => 'in_progress'],
        'pending'     => ['label' => 'Pending',     'icon' => 'fa-clock',        'class' => 'pending'],
        'cancelled'   => ['label' => 'Cancelled',   'icon' => 'fa-times-circle', 'class' => 'cancelled'],
    ];
    return $map[$status] ?? $map['completed'];
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$default_letter = strtoupper(substr($user_full_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Daily Activities - <?= htmlspecialchars($user_branch_name) ?></title>
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
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
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --page-primary: #0B5ED7;
            --page-primary-dark: #0A4CA8;
            --page-primary-bg: #E8F0FE;
            --page-bg-card: #FFFFFF;
            --page-text-primary: #1E293B;
            --page-text-secondary: #64748B;
            --page-border: #E2E8F0;
            --page-hover: #F8FAFC;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --page-bg-card: #1E293B;
            --page-text-primary: #F1F5F9;
            --page-text-secondary: #94A3B8;
            --page-border: #334155;
            --page-hover: #0F172A;
            --page-primary-bg: #1E3A5F;
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
        
        /* TOP NAV */
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
            max-width: 350px;
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
            font-size: 0.82rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
            font-size: 0.78rem;
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.78rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover { background: var(--primary-dark); }
        
        .top-nav .datetime {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .top-nav .avatar {
            width: 38px;
            height: 38px;
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
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 0.78rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .branch-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            box-shadow: 0 3px 10px rgba(5, 150, 105, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .avatar-default {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            color: white;
            background: var(--primary);
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .avatar-default:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        /* MAIN CONTENT */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
        /* PAGE HEADER */
        .page-header-da {
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4FB0 50%, #083D8A 100%);
            border-radius: 20px;
            padding: 26px 32px;
            margin-bottom: 22px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            color: white;
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.35), 0 4px 12px rgba(11, 94, 215, 0.2);
            position: relative;
            overflow: hidden;
        }
        .page-header-da::before {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .page-header-da h1 {
            font-size: 1.55rem;
            font-weight: 800;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 2;
        }
        .page-header-da h1 i {
            width: 46px; height: 46px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .page-header-da .subtitle {
            font-size: 0.88rem;
            color: rgba(255,255,255,0.9);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }
        .page-header-da .badge-info {
            background: rgba(255,255,255,0.15);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.25);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-add-activity {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.88rem;
            border: 2px solid rgba(255,255,255,0.3);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            position: relative;
            z-index: 2;
            white-space: nowrap;
            backdrop-filter: blur(10px);
        }
        .btn-add-activity:hover {
            background: white;
            color: #0B5ED7;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        }
        
        /* FLASH */
        .flash-da {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-weight: 600;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .flash-da.success { background: #D1FAE5; color: #059669; border: 2px solid #059669; }
        .flash-da.error { background: #FEE2E2; color: #DC2626; border: 2px solid #DC2626; }
        [data-theme="dark"] .flash-da.success { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .flash-da.error { background: #3A1A1A; color: #F87171; }
        
        /* STATS */
        .stats-grid-da {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        .stat-card-da {
            background: var(--page-bg-card);
            border-radius: 14px;
            padding: 18px 20px;
            border: 2px solid var(--page-border);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .stat-card-da:hover {
            transform: translateY(-3px);
            border-color: var(--page-primary);
            box-shadow: 0 10px 28px rgba(11, 94, 215, 0.12);
        }
        .stat-icon-da {
            width: 50px; height: 50px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0; color: white;
        }
        .stat-icon-da.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-icon-da.green { background: linear-gradient(135deg, #059669, #047857); }
        .stat-icon-da.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-icon-da.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card-da .stat-value {
            font-size: 1.5rem; font-weight: 800;
            color: var(--page-text-primary);
            margin: 0; line-height: 1.1;
            font-family: 'Courier New', monospace;
        }
        .stat-card-da .stat-label {
            font-size: 0.68rem; color: var(--page-text-secondary);
            text-transform: uppercase; font-weight: 700;
            letter-spacing: 0.05em; margin: 4px 0 0 0;
        }
        
        /* FILTERS */
        .filter-card-da {
            background: var(--page-bg-card);
            border-radius: 14px;
            padding: 18px 22px;
            border: 2px solid var(--page-border);
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .filter-title-da {
            font-size: 0.78rem; font-weight: 700;
            color: var(--page-text-primary);
            margin-bottom: 12px;
            display: flex; align-items: center; gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .filter-title-da i { color: var(--page-primary); }
        
        .filter-chips-da {
            display: flex; flex-wrap: wrap; gap: 8px;
            margin-bottom: 16px;
        }
        .filter-chip-da {
            padding: 7px 16px;
            border-radius: 20px;
            font-size: 0.74rem; font-weight: 600;
            background: var(--page-hover);
            color: var(--page-text-secondary);
            border: 2px solid var(--page-border);
            cursor: pointer; transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
            font-family: inherit;
        }
        .filter-chip-da:hover {
            border-color: var(--page-primary);
            color: var(--page-primary);
            transform: translateY(-2px);
        }
        .filter-chip-da.active {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white; border-color: transparent;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .filter-row-da {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px; align-items: end;
        }
        .filter-group-da label {
            font-size: 0.65rem; font-weight: 700;
            color: var(--page-text-secondary);
            text-transform: uppercase;
            margin-bottom: 5px; display: block;
            letter-spacing: 0.04em;
        }
        .filter-group-da input,
        .filter-group-da select {
            width: 100%;
            padding: 9px 12px;
            border: 2px solid var(--page-border);
            border-radius: 10px;
            font-size: 0.8rem;
            background: var(--page-bg-card);
            color: var(--page-text-primary);
            outline: none; font-family: inherit;
            transition: all 0.3s; font-weight: 500;
        }
        .filter-group-da input:focus,
        .filter-group-da select:focus {
            border-color: var(--page-primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        [data-theme="dark"] .filter-group-da input,
        [data-theme="dark"] .filter-group-da select {
            background: #0F172A; color: #F1F5F9;
        }
        [data-theme="dark"] .filter-group-da select option {
            background: #1E293B; color: #F1F5F9;
        }
        
        .btn-da {
            padding: 9px 20px;
            border-radius: 10px;
            font-weight: 600; font-size: 0.8rem;
            border: none; cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center; justify-content: center;
            gap: 8px; text-decoration: none;
            font-family: inherit; min-height: 40px;
        }
        .btn-da:hover { transform: translateY(-2px); }
        .btn-primary-da {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        .btn-outline-da {
            background: transparent;
            color: var(--page-text-secondary);
            border: 2px solid var(--page-border);
        }
        .btn-outline-da:hover {
            border-color: var(--page-primary);
            color: var(--page-primary);
            background: var(--page-primary-bg);
        }
        
        /* SEARCH BAR */
        .search-wrap-da {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: 16px;
            padding: 18px 22px;
            margin-bottom: 20px;
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.3);
            position: relative;
            overflow: hidden;
        }
        .search-wrap-da::before {
            content: '';
            position: absolute;
            top: -50%; right: -5%;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        .search-box-da {
            position: relative;
            display: flex; align-items: center;
            z-index: 2;
        }
        .search-box-da i.search-icon {
            position: absolute;
            left: 18px;
            color: #0B5ED7;
            font-size: 1.05rem;
            pointer-events: none;
            z-index: 3;
        }
        .search-box-da input {
            width: 100%;
            padding: 15px 130px 15px 50px;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            background: white;
            color: #1E293B;
            outline: none;
            font-family: inherit;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        [data-theme="dark"] .search-box-da input {
            background: #1E293B;
            color: #F1F5F9;
        }
        .search-box-da input::placeholder { color: #94A3B8; font-weight: 500; }
        .search-box-da .search-hint {
            position: absolute;
            right: 18px;
            font-size: 0.68rem; font-weight: 700;
            color: #64748B; background: #F1F5F9;
            padding: 4px 12px; border-radius: 12px;
            pointer-events: none; z-index: 3;
        }
        .search-box-da .clear-btn {
            position: absolute;
            right: 100px;
            background: transparent; border: none;
            color: #DC2626; font-size: 0.95rem;
            cursor: pointer; padding: 6px 10px;
            border-radius: 8px; display: none; z-index: 4;
        }
        .search-box-da .clear-btn.visible { display: block; }
        
        .search-result-info {
            text-align: center;
            margin-top: 12px;
            font-size: 0.78rem;
            color: rgba(255,255,255,0.9);
            font-weight: 600;
            position: relative; z-index: 2;
        }
        .search-result-info strong { color: white; font-weight: 800; }
        
        /* TABLE CARD */
        .activities-card-da {
            background: var(--page-bg-card);
            border-radius: 16px;
            border: 2px solid var(--page-border);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 20px;
        }
        .card-header-da {
            padding: 16px 22px;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-header-da h3 {
            font-size: 0.95rem;
            font-weight: 800;
            margin: 0;
            display: flex; align-items: center; gap: 10px;
        }
        .card-header-da .count-badge {
            background: rgba(255,255,255,0.2);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            border: 1px solid rgba(255,255,255,0.25);
        }
        
        .data-table-da {
            width: 100%; border-collapse: collapse; font-size: 0.82rem;
        }
        .data-table-da thead th {
            background: var(--page-hover);
            color: var(--page-text-secondary);
            font-weight: 800; padding: 12px 16px;
            font-size: 0.64rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-align: left;
            border-bottom: 2px solid var(--page-border);
            white-space: nowrap;
        }
        .data-table-da td {
            padding: 13px 16px;
            border-bottom: 1px solid var(--page-border);
            color: var(--page-text-primary);
            vertical-align: top;
        }
        .data-table-da tbody tr:last-child td { border-bottom: none; }
        .data-table-da tbody tr { transition: background 0.2s ease; }
        .data-table-da tbody tr:hover td { background: var(--page-hover); }
        .data-table-da tbody tr.hidden-by-search { display: none; }
        
        .activity-time-block {
            display: flex; flex-direction: column; gap: 3px;
            font-family: 'Courier New', monospace; font-size: 0.78rem;
        }
        .activity-time-block .date-line {
            font-weight: 800;
            color: var(--page-text-primary);
            display: flex; align-items: center; gap: 5px;
        }
        .activity-time-block .date-line i { color: #0B5ED7; font-size: 0.72rem; }
        .activity-time-block .time-line {
            font-weight: 700; color: #0B5ED7;
            display: flex; align-items: center; gap: 5px;
        }
        .activity-time-block .time-line i { color: #0B5ED7; font-size: 0.68rem; }
        .activity-time-block .duration-line {
            font-size: 0.68rem;
            color: var(--page-text-secondary);
            font-weight: 600;
            display: flex; align-items: center; gap: 4px;
        }
        
        .activity-title-da {
            font-weight: 700;
            color: var(--page-text-primary);
            font-size: 0.85rem;
            display: block; margin-bottom: 3px;
        }
        .activity-desc-da {
            font-size: 0.72rem;
            color: var(--page-text-secondary);
            display: block; line-height: 1.5;
        }
        
        .category-pill-da {
            font-size: 0.62rem; padding: 4px 11px;
            border-radius: 12px;
            background: var(--page-primary-bg);
            color: var(--page-primary);
            font-weight: 700; display: inline-flex;
            align-items: center; gap: 5px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border: 1.5px solid rgba(11, 94, 215, 0.2);
        }
        [data-theme="dark"] .category-pill-da {
            background: #1E3A5F;
            color: #6EA8FE;
            border-color: rgba(110, 168, 254, 0.3);
        }
        
        .status-badge-da {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 11px; border-radius: 20px;
            font-size: 0.62rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.03em;
            white-space: nowrap;
        }
        .status-badge-da.completed { background: #D1FAE5; color: #059669; }
        .status-badge-da.in_progress { background: #FEF3C7; color: #D97706; }
        .status-badge-da.pending { background: #E8F0FE; color: #0B5ED7; }
        .status-badge-da.cancelled { background: #FEE2E2; color: #DC2626; }
        
        [data-theme="dark"] .status-badge-da.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge-da.in_progress { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge-da.pending { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge-da.cancelled { background: #3A1A1A; color: #F87171; }
        
        mark.hl-da {
            background: #FEF08A;
            color: #854D0E;
            padding: 1px 3px;
            border-radius: 3px;
            font-weight: 900;
        }
        [data-theme="dark"] mark.hl-da {
            background: #854D0E;
            color: #FEF08A;
        }
        
        .btn-action-da {
            width: 36px; height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center; justify-content: center;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.25s;
            text-decoration: none;
            flex-shrink: 0;
        }
        .btn-action-da:hover { transform: translateY(-2px) scale(1.05); }
        .btn-action-da.edit {
            background: rgba(11, 94, 215, 0.1);
            color: #0B5ED7;
            border: 1.5px solid rgba(11, 94, 215, 0.3);
        }
        .btn-action-da.edit:hover {
            background: #0B5ED7;
            color: white;
            box-shadow: 0 6px 16px rgba(11, 94, 215, 0.4);
        }
        
        .empty-state-da {
            text-align: center;
            padding: 60px 20px;
            background: var(--page-bg-card);
            border-radius: 16px;
            border: 2px solid var(--page-border);
        }
        .empty-state-da i {
            font-size: 3.5rem;
            color: var(--page-border);
            margin-bottom: 16px;
            display: block;
        }
        .empty-state-da h3 {
            font-size: 1.1rem;
            color: var(--page-text-primary);
            margin: 0 0 8px 0;
            font-weight: 700;
        }
        .empty-state-da p {
            color: var(--page-text-secondary);
            font-size: 0.85rem;
            margin: 0 0 20px 0;
        }
        
        /* MODAL */
        .modal-overlay-da {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            width: 100vw; height: 100vh;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(8px);
            z-index: 99999;
            display: none;
            padding: 20px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }
        .modal-overlay-da.active { display: block; }
        
        .modal-box-da {
            background: var(--page-bg-card);
            border-radius: 20px;
            max-width: 680px;
            width: 100%;
            margin: 20px auto;
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
            animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            border-top: 5px solid #0B5ED7;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 400px;
        }
        @keyframes modalPop {
            0% { opacity: 0; transform: scale(0.85) translateY(20px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }
        
        .modal-header-da {
            padding: 22px 28px;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
            border-radius: 20px 20px 0 0;
        }
        .modal-header-da h3 {
            font-size: 1.15rem;
            font-weight: 800;
            margin: 0;
            display: flex; align-items: center; gap: 10px;
        }
        .modal-close-da {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            border: none; cursor: pointer;
            color: white; font-size: 1rem;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        .modal-close-da:hover { background: rgba(255,255,255,0.35); transform: rotate(90deg); }
        
        .modal-body-da {
            padding: 24px 28px;
            overflow-y: auto;
            overflow-x: hidden;
            flex: 1;
            max-height: calc(100vh - 240px);
            min-height: 200px;
            -webkit-overflow-scrolling: touch;
        }
        .modal-body-da::-webkit-scrollbar { width: 10px; }
        .modal-body-da::-webkit-scrollbar-track { background: var(--page-hover); border-radius: 10px; }
        .modal-body-da::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #0B5ED7, #0A4CA8);
            border-radius: 10px;
            border: 2px solid var(--page-hover);
        }
        
        .modal-footer-da {
            padding: 18px 28px;
            border-top: 2px solid var(--page-border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: var(--page-hover);
            flex-wrap: wrap;
            flex-shrink: 0;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.06);
            border-radius: 0 0 20px 20px;
        }
        
        /* FORM */
        .form-grid-da {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-grid-da .full { grid-column: 1 / -1; }
        
        .form-group-da label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--page-text-primary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .form-group-da label i { color: #0B5ED7; font-size: 0.85rem; }
        .form-group-da label .required { color: #DC2626; font-weight: 900; }
        
        .form-group-da input[type="text"],
        .form-group-da textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--page-border);
            border-radius: 12px;
            font-size: 0.88rem;
            background: var(--page-bg-card);
            color: var(--page-text-primary);
            outline: none;
            font-family: inherit;
            transition: all 0.3s;
            font-weight: 500;
        }
        .form-group-da input[type="text"]:focus,
        .form-group-da textarea:focus {
            border-color: #0B5ED7;
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
        }
        .form-group-da input[type="text"]::placeholder,
        .form-group-da textarea::placeholder {
            color: var(--page-text-secondary);
            opacity: 0.6;
            font-weight: 400;
        }
        
        .form-group-da select {
            width: 100%;
            padding: 12px 42px 12px 16px;
            border: 2px solid var(--page-border);
            border-radius: 12px;
            font-size: 0.88rem;
            background: var(--page-bg-card);
            color: var(--page-text-primary);
            outline: none;
            font-family: inherit;
            transition: all 0.3s;
            font-weight: 600;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%230B5ED7' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 14px;
        }
        .form-group-da select:hover {
            border-color: #0B5ED7;
            background-color: var(--page-primary-bg);
        }
        .form-group-da select:focus {
            border-color: #0B5ED7;
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.15);
        }
        [data-theme="dark"] .form-group-da select {
            background-color: #0F172A;
            color: #F1F5F9;
        }
        [data-theme="dark"] .form-group-da select option {
            background: #1E293B; color: #F1F5F9; padding: 10px;
        }
        
        .form-group-da textarea { min-height: 90px; resize: vertical; line-height: 1.6; }
        [data-theme="dark"] .form-group-da input[type="text"],
        [data-theme="dark"] .form-group-da textarea {
            background: #0F172A; color: #F1F5F9;
        }
        
        .auto-info-box {
            padding: 14px 18px;
            background: linear-gradient(135deg, #E8F0FE, #DBEAFE);
            border: 2px solid #0B5ED7;
            border-radius: 12px;
            font-size: 0.82rem;
            color: #0A4CA8;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            line-height: 1.6;
        }
        [data-theme="dark"] .auto-info-box {
            background: linear-gradient(135deg, #1E3A5F, #0A4CA8);
            color: #93C5FD;
        }
        .auto-info-box i { font-size: 1.3rem; color: #0B5ED7; flex-shrink: 0; }
        
        /* CATEGORY */
        .category-input-wrapper { display: flex; flex-direction: column; gap: 10px; }
        
        .category-mode-tabs {
            display: flex; gap: 6px; padding: 4px;
            background: var(--page-hover);
            border-radius: 12px;
            border: 2px solid var(--page-border);
        }
        [data-theme="dark"] .category-mode-tabs { background: #0F172A; }
        
        .category-mode-tab {
            flex: 1; padding: 9px 14px;
            border-radius: 9px; font-size: 0.75rem; font-weight: 700;
            border: none; cursor: pointer;
            transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center;
            gap: 6px; background: transparent;
            color: var(--page-text-secondary);
            font-family: inherit;
        }
        .category-mode-tab:hover { background: var(--page-primary-bg); color: #0B5ED7; }
        .category-mode-tab.active {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .category-mode-content { display: none; }
        .category-mode-content.active { display: block; }
        
        .category-dropdown-wrapper { position: relative; }
        .category-dropdown-wrapper .cat-icon-preview {
            position: absolute;
            left: 14px; top: 50%;
            transform: translateY(-50%);
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            background: var(--page-primary-bg);
            color: #0B5ED7;
            pointer-events: none;
            z-index: 2;
        }
        .category-dropdown-wrapper select { padding-left: 58px !important; }
        
        .manual-input-wrapper { position: relative; }
        .manual-input-wrapper i.manual-icon {
            position: absolute;
            left: 14px; top: 50%;
            transform: translateY(-50%);
            color: #0B5ED7; font-size: 0.9rem;
            pointer-events: none;
        }
        .manual-input-wrapper input { padding-left: 42px !important; }
        
        .category-suggestions {
            display: flex; flex-wrap: wrap;
            gap: 6px; margin-top: 10px;
        }
        .category-suggestion-chip {
            padding: 5px 12px;
            border-radius: 16px;
            font-size: 0.7rem; font-weight: 600;
            background: var(--page-primary-bg);
            color: #0B5ED7;
            border: 1.5px solid rgba(11, 94, 215, 0.25);
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex; align-items: center; gap: 4px;
            font-family: inherit;
        }
        .category-suggestion-chip:hover {
            background: #0B5ED7; color: white;
            border-color: #0B5ED7;
            transform: translateY(-2px);
        }
        
        /* SAVE BUTTONS */
        .btn-modal-da {
            padding: 13px 28px;
            border-radius: 12px;
            font-weight: 800; font-size: 0.88rem;
            border: none; cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex; align-items: center; gap: 10px;
            font-family: inherit;
            min-height: 46px;
        }
        .btn-modal-da.cancel {
            background: var(--page-bg-card);
            color: var(--page-text-secondary);
            border: 2px solid var(--page-border);
        }
        .btn-modal-da.cancel:hover {
            background: var(--page-hover);
            border-color: #DC2626;
            color: #DC2626;
            transform: translateY(-2px);
        }
        .btn-modal-da.submit {
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(11, 94, 215, 0.4);
            border: 2px solid transparent;
            min-width: 160px;
            justify-content: center;
        }
        .btn-modal-da.submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 32px rgba(11, 94, 215, 0.55);
            background: linear-gradient(135deg, #0A4CA8 0%, #083D8A 100%);
        }
        
        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 280px; }
        }
        
        @media (max-width: 768px) {
            .top-nav { padding: 0 12px; }
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header-da { padding: 18px 20px; }
            .page-header-da h1 { font-size: 1.2rem; }
            .stats-grid-da { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card-da { padding: 12px 14px; }
            .stat-icon-da { width: 40px; height: 40px; font-size: 1rem; }
            .stat-card-da .stat-value { font-size: 1.2rem; }
            .filter-row-da { grid-template-columns: 1fr; }
            .search-box-da input { padding: 12px 90px 12px 42px; font-size: 0.85rem; }
            .data-table-da { font-size: 0.72rem; }
            .data-table-da td, .data-table-da thead th { padding: 10px 8px; }
            .form-grid-da { grid-template-columns: 1fr; }
            .modal-overlay-da { padding: 0; }
            .modal-box-da { max-width: 100%; margin: 0; border-radius: 0; min-height: 100vh; max-height: none; }
            .modal-header-da { padding: 16px 18px; border-radius: 0; position: sticky; top: 0; z-index: 20; }
            .modal-body-da { padding: 18px 16px; max-height: none; flex: 1; }
            .modal-footer-da { padding: 14px 18px; flex-direction: column-reverse; border-radius: 0; position: sticky; bottom: 0; z-index: 20; }
            .btn-modal-da { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .stats-grid-da { grid-template-columns: 1fr; }
            .btn-add-activity { padding: 10px 16px; font-size: 0.78rem; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- ✅ TOP NAV - BRANCH ONLY (NO NAME, NO ROLE) -->
<!-- ================================================================ -->
<nav class="top-nav">
    <!-- Left: Search -->
    <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
        <div class="search-wrapper">
            <input type="text" placeholder="Search patients, appointments..." aria-label="Search">
            <button class="search-btn" type="button">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>
    
    <!-- Right: Datetime + Dark Mode + Branch + Avatar -->
    <div style="display: flex; align-items: center; gap: 12px;">
        <div class="datetime">
            <i class="fas fa-clock" style="color: var(--primary-light);"></i>
            <span id="topNavDateTime"><?= date('D, M d Y') ?> • <?= date('h:i:s A') ?></span>
        </div>
        
        <button class="dark-toggle-btn" onclick="toggleDarkMode()" title="Toggle Dark Mode" type="button">
            <i class="fas fa-moon" id="darkModeIcon"></i>
            <span id="darkModeText">Dark</span>
        </button>
        
        <!-- ✅ BRANCH ONLY (no name, no role) -->
        <span class="branch-badge">
            <i class="fas fa-store-alt"></i>
            <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <!-- Profile Avatar -->
        <?php if (!empty($profile_pic) && file_exists($_SERVER['DOCUMENT_ROOT'] . $profile_pic_url)): ?>
            <img src="<?= htmlspecialchars($profile_pic_url) ?>" alt="Profile" class="avatar" 
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div class="avatar-default" style="display:none;"><?= $default_letter ?></div>
        <?php else: ?>
            <div class="avatar-default"><?= $default_letter ?></div>
        <?php endif; ?>
    </div>
</nav>

<script>
// Live clock
function updateTopNavClock() {
    var el = document.getElementById('topNavDateTime');
    if (!el) return;
    var now = new Date();
    var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var day = dayNames[now.getDay()];
    var month = monthNames[now.getMonth()];
    var date = String(now.getDate()).padStart(2, '0');
    var year = now.getFullYear();
    var hours = now.getHours();
    var ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    var hh = String(hours).padStart(2, '0');
    var mm = String(now.getMinutes()).padStart(2, '0');
    var ss = String(now.getSeconds()).padStart(2, '0');
    el.textContent = day + ', ' + month + ' ' + date + ' ' + year + ' • ' + hh + ':' + mm + ':' + ss + ' ' + ampm;
}
setInterval(updateTopNavClock, 1000);
updateTopNavClock();

// Dark mode toggle
function toggleDarkMode() {
    var html = document.documentElement;
    var isDark = html.getAttribute('data-theme') === 'dark';
    var icon = document.getElementById('darkModeIcon');
    var text = document.getElementById('darkModeText');
    
    if (isDark) {
        html.removeAttribute('data-theme');
        localStorage.setItem('darkMode', 'false');
        document.cookie = 'dark_mode=false; path=/';
        if (icon) icon.className = 'fas fa-moon';
        if (text) text.textContent = 'Dark';
    } else {
        html.setAttribute('data-theme', 'dark');
        localStorage.setItem('darkMode', 'true');
        document.cookie = 'dark_mode=true; path=/';
        if (icon) icon.className = 'fas fa-sun';
        if (text) text.textContent = 'Light';
    }
}

// Initialize dark mode
(function() {
    var isDark = localStorage.getItem('darkMode') === 'true' 
              || document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
        document.documentElement.setAttribute('data-theme', 'dark');
        var icon = document.getElementById('darkModeIcon');
        var text = document.getElementById('darkModeText');
        if (icon) icon.className = 'fas fa-sun';
        if (text) text.textContent = 'Light';
    }
})();
</script>

<?php
// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/reception_sidebar.php';
?>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header-da">
        <div>
            <h1>
                <i class="fas fa-headset"></i>
                My Daily Activities
            </h1>
            <p class="subtitle">
                <span class="badge-info">
                    <i class="fas fa-list-check"></i> <?= number_format($all_time_activities) ?> total
                </span>
                <span class="badge-info" style="background:rgba(251,191,36,0.3);">
                    <i class="fas fa-calendar-day"></i> <?= number_format($today_activities) ?> today
                </span>
            </p>
        </div>
        <button type="button" class="btn-add-activity" onclick="openAddModal()">
            <i class="fas fa-plus-circle"></i> Add Activity
        </button>
    </div>

    <!-- Flash -->
    <?php if (!empty($flash_message)): ?>
        <div class="flash-da <?= $flash_type ?>">
            <i class="fas <?= $flash_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.2rem;"></i>
            <div><?= htmlspecialchars($flash_message) ?></div>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid-da">
        <div class="stat-card-da">
            <div class="stat-icon-da orange"><i class="fas fa-calendar-day"></i></div>
            <div>
                <p class="stat-value"><?= number_format($today_activities) ?></p>
                <p class="stat-label">Today</p>
            </div>
        </div>
        <div class="stat-card-da">
            <div class="stat-icon-da green"><i class="fas fa-calendar-week"></i></div>
            <div>
                <p class="stat-value"><?= number_format($this_week_activities) ?></p>
                <p class="stat-label">This Week</p>
            </div>
        </div>
        <div class="stat-card-da">
            <div class="stat-icon-da blue"><i class="fas fa-calendar-alt"></i></div>
            <div>
                <p class="stat-value"><?= number_format($this_month_activities) ?></p>
                <p class="stat-label">This Month</p>
            </div>
        </div>
        <div class="stat-card-da">
            <div class="stat-icon-da purple"><i class="fas fa-list-check"></i></div>
            <div>
                <p class="stat-value"><?= number_format($all_time_activities) ?></p>
                <p class="stat-label">All Time</p>
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-wrap-da">
        <div class="search-box-da">
            <i class="fas fa-search search-icon"></i>
            <input type="text" 
                   id="globalSearchInput" 
                   placeholder="🔍 Search your activities..."
                   autocomplete="off"
                   value="<?= htmlspecialchars($search_global) ?>">
            <button type="button" class="clear-btn" id="globalClearBtn">
                <i class="fas fa-times-circle"></i>
            </button>
            <span class="search-hint"><i class="fas fa-bolt"></i> LIVE</span>
        </div>
        <div class="search-result-info" id="globalResultInfo">
            Showing <strong><?= number_format(count($activities)) ?></strong> activit<?= count($activities) !== 1 ? 'ies' : 'y' ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card-da">
        <div class="filter-title-da">
            <i class="fas fa-filter"></i> Filters
        </div>

        <div class="filter-chips-da">
            <?php
            $filter_options = [
                'all'   => ['label' => 'All Time', 'icon' => 'fa-infinity'],
                'today' => ['label' => 'Today',    'icon' => 'fa-calendar-day'],
                '1d'    => ['label' => '1 Day',    'icon' => 'fa-calendar'],
                '1w'    => ['label' => '1 Week',   'icon' => 'fa-calendar-week'],
                '1m'    => ['label' => '1 Month',  'icon' => 'fa-calendar-alt'],
                '3m'    => ['label' => '3 Months', 'icon' => 'fa-calendar-alt'],
                '6m'    => ['label' => '6 Months', 'icon' => 'fa-calendar-alt'],
                '1y'    => ['label' => '1 Year',   'icon' => 'fa-calendar-check'],
                'custom'=> ['label' => 'Custom',   'icon' => 'fa-sliders-h'],
            ];
            foreach ($filter_options as $key => $opt):
                $active = ($filter_type === $key) ? 'active' : '';
            ?>
                <a href="?filter=<?= $key ?>&category=<?= urlencode($category_filter) ?>&search=<?= urlencode($search_global) ?>" 
                   class="filter-chip-da <?= $active ?>">
                    <i class="fas <?= $opt['icon'] ?>"></i> <?= $opt['label'] ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" id="filterForm">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter_type) ?>">

            <div class="filter-row-da">
                <?php if ($filter_type === 'custom'): ?>
                    <div class="filter-group-da">
                        <label><i class="fas fa-calendar-plus"></i> Date From</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="filter-group-da">
                        <label><i class="fas fa-calendar-minus"></i> Date To</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                <?php endif; ?>

                <div class="filter-group-da">
                    <label><i class="fas fa-clock"></i> Time From</label>
                    <input type="time" name="time_from" value="<?= htmlspecialchars($time_from) ?>">
                </div>

                <div class="filter-group-da">
                    <label><i class="fas fa-clock"></i> Time To</label>
                    <input type="time" name="time_to" value="<?= htmlspecialchars($time_to) ?>">
                </div>

                <div class="filter-group-da">
                    <label><i class="fas fa-tag"></i> Category</label>
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $category_filter === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group-da" style="display:flex;gap:8px;align-items:flex-end;">
                    <button type="submit" class="btn-da btn-primary-da" style="flex:1;">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <a href="daily_activities.php" class="btn-da btn-outline-da" style="flex:0 0 auto;">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Activities Table -->
    <div class="activities-card-da">
        <div class="card-header-da">
            <h3>
                <i class="fas fa-list-check"></i>
                My Activities
                <span class="count-badge">
                    <i class="fas fa-hashtag"></i> <?= number_format(count($activities)) ?>
                </span>
            </h3>
        </div>

        <?php if (count($activities) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table-da" id="activitiesTable">
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th style="width:180px;">Date & Time</th>
                            <th>Activity</th>
                            <th style="width:190px;">Category</th>
                            <th style="width:110px;">Status</th>
                            <th style="width:90px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($activities as $act): 
                            $start = $act['start_time'] ? date('g:i A', strtotime($act['start_time'])) : '—';
                            $end = $act['end_time'] ? date('g:i A', strtotime($act['end_time'])) : null;
                            $time_range = $start;
                            if ($end) $time_range .= ' - ' . $end;
                            $status_meta = statusMeta($act['status'] ?? 'completed');
                            $cat_key = $act['category'] ?? '';
                            $cat_icon = 'fa-tag';
                            $cat_color = '#0B5ED7';
                            if (isset($predefined_categories[$cat_key])) {
                                $cat_icon = $predefined_categories[$cat_key]['icon'];
                                $cat_color = $predefined_categories[$cat_key]['color'];
                            }
                            $searchable_text = strtolower(
                                ($act['title'] ?? '') . ' ' . 
                                ($act['description'] ?? '') . ' ' . 
                                ($act['category'] ?? '') . ' ' .
                                date('d M Y', strtotime($act['activity_date']))
                            );
                        ?>
                            <tr data-searchable="<?= htmlspecialchars($searchable_text) ?>"
                                data-activity-id="<?= (int)$act['id'] ?>">
                                <td style="text-align:center;font-weight:700;color:var(--page-text-secondary);"><?= $i++ ?></td>
                                <td>
                                    <div class="activity-time-block">
                                        <span class="date-line">
                                            <i class="fas fa-calendar"></i>
                                            <?= date('d M Y', strtotime($act['activity_date'])) ?>
                                        </span>
                                        <span class="time-line">
                                            <i class="fas fa-clock"></i>
                                            <?= $time_range ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="activity-title-da"><?= htmlspecialchars($act['title'] ?? 'N/A') ?></span>
                                    <?php if (!empty($act['description'])): ?>
                                        <span class="activity-desc-da"><?= htmlspecialchars($act['description']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($act['category'])): ?>
                                        <span class="category-pill-da">
                                            <i class="fas <?= $cat_icon ?>" style="color:<?= $cat_color ?>;"></i>
                                            <?= htmlspecialchars($act['category']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--page-text-secondary);font-size:0.72rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge-da <?= $status_meta['class'] ?>">
                                        <i class="fas <?= $status_meta['icon'] ?>"></i>
                                        <?= $status_meta['label'] ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" 
                                            class="btn-action-da edit" 
                                            onclick="openEditModal(<?= (int)$act['id'] ?>)"
                                            title="Edit Activity">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="noMatchMsg" style="display:none;text-align:center;padding:40px 20px;color:var(--page-text-secondary);">
                <i class="fas fa-search-minus" style="font-size:2.5rem;opacity:0.5;display:block;margin-bottom:12px;color:#0B5ED7;"></i>
                <p style="font-size:0.95rem;font-weight:700;margin:0 0 6px 0;color:var(--page-text-primary);">No activities match your search</p>
                <p style="font-size:0.78rem;margin:0;">Try a different keyword</p>
            </div>

        <?php else: ?>
            <div class="empty-state-da" style="margin:20px;">
                <i class="fas fa-inbox"></i>
                <h3>No Activities Found</h3>
                <p>You haven't recorded any activities yet</p>
                <button type="button" class="btn-da btn-primary-da" onclick="openAddModal()">
                    <i class="fas fa-plus-circle"></i> Add Your First Activity
                </button>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Reception Daily Activities
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>
</main>

<!-- ================================================================ -->
<!-- MODAL: ADD ACTIVITY -->
<!-- ================================================================ -->
<div class="modal-overlay-da" id="addModal">
    <div class="modal-box-da">
        <div class="modal-header-da">
            <h3><i class="fas fa-plus-circle"></i> Add Daily Activity</h3>
            <button type="button" class="modal-close-da" onclick="closeAddModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="addActivityForm">
            <input type="hidden" name="action" value="add_activity">
            <div class="modal-body-da">
                
                <div class="auto-info-box" style="margin-bottom:20px;">
                    <i class="fas fa-clock"></i>
                    <div>
                        <strong>📅 Date & Time auto-filled:</strong><br>
                        <?= date('d M Y') ?> at <?= date('g:i A') ?>
                        <span style="font-size:0.7rem;opacity:0.75;font-weight:500;display:block;margin-top:2px;">
                            <i class="fas fa-info-circle"></i> Server time — cannot be changed
                        </span>
                    </div>
                </div>

                <div class="form-grid-da">
                    <div class="form-group-da full">
                        <label><i class="fas fa-heading"></i> Activity Title <span class="required">*</span></label>
                        <input type="text" name="title" placeholder="e.g. Registered 12 new patients" required maxlength="255" id="add_title">
                    </div>

                    <div class="form-group-da full">
                        <label>
                            <i class="fas fa-tag"></i> Category
                            <span style="font-size:0.68rem;font-weight:500;color:var(--page-text-secondary);text-transform:none;letter-spacing:0;">
                                (Choose from list or type your own)
                            </span>
                        </label>
                        
                        <div class="category-input-wrapper">
                            <div class="category-mode-tabs">
                                <button type="button" class="category-mode-tab active" id="add_mode_dropdown_btn" onclick="switchCatMode('add', 'dropdown')">
                                    <i class="fas fa-list"></i> Choose from list
                                </button>
                                <button type="button" class="category-mode-tab" id="add_mode_manual_btn" onclick="switchCatMode('add', 'manual')">
                                    <i class="fas fa-pen"></i> Type your own
                                </button>
                            </div>
                            
                            <div class="category-mode-content active" id="add_dropdown_mode">
                                <div class="category-dropdown-wrapper">
                                    <span class="cat-icon-preview" id="addCatIcon">
                                        <i class="fas fa-tag"></i>
                                    </span>
                                    <select id="add_category_select" onchange="selectCategoryFromDropdown('add')">
                                        <option value="">-- Select Category --</option>
                                        <?php foreach ($predefined_categories as $cat_name => $cat_data): ?>
                                            <option value="<?= htmlspecialchars($cat_name) ?>" data-icon="<?= $cat_data['icon'] ?>" data-color="<?= $cat_data['color'] ?>">
                                                <?= htmlspecialchars($cat_name) ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <?php foreach ($categories as $cat) {
                                            if (!isset($predefined_categories[$cat])) {
                                                echo '<option value="' . htmlspecialchars($cat) . '" data-icon="fa-tag" data-color="#0B5ED7">' . htmlspecialchars($cat) . '</option>';
                                            }
                                        } ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="category-mode-content" id="add_manual_mode">
                                <div class="manual-input-wrapper">
                                    <i class="fas fa-pen manual-icon"></i>
                                    <input type="text" id="add_category_manual" placeholder="Type your custom category..." maxlength="100" autocomplete="off" oninput="syncManualCategory('add')">
                                </div>
                            </div>
                            
                            <input type="hidden" name="category" id="add_category_final" value="">
                        </div>
                    </div>

                    <div class="form-group-da">
                        <label><i class="fas fa-info-circle"></i> Status</label>
                        <select name="status" id="add_status">
                            <option value="completed" selected>✅ Completed</option>
                            <option value="in_progress">🔄 In Progress</option>
                            <option value="pending">⏳ Pending</option>
                            <option value="cancelled">❌ Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group-da">
                        <label><i class="fas fa-flag"></i> Priority</label>
                        <select name="priority" id="add_priority">
                            <option value="low">🟢 Low</option>
                            <option value="medium" selected>🟡 Medium</option>
                            <option value="high">🟠 High</option>
                            <option value="urgent">🔴 Urgent</option>
                        </select>
                    </div>

                    <div class="form-group-da full">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" placeholder="Detailed description..." maxlength="2000" id="add_description"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer-da">
                <button type="button" class="btn-modal-da cancel" onclick="closeAddModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-modal-da submit">
                    <i class="fas fa-save"></i> Save Activity
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- MODAL: EDIT ACTIVITY -->
<!-- ================================================================ -->
<div class="modal-overlay-da" id="editModal">
    <div class="modal-box-da">
        <div class="modal-header-da">
            <h3><i class="fas fa-edit"></i> Edit Activity</h3>
            <button type="button" class="modal-close-da" onclick="closeEditModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="editActivityForm">
            <input type="hidden" name="action" value="edit_activity">
            <input type="hidden" name="activity_id" id="edit_id">
            <div class="modal-body-da">
                
                <div class="auto-info-box" style="margin-bottom:20px;">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Note:</strong> Only Title, Category, Status, Priority & Description can be edited.
                    </div>
                </div>

                <div class="form-grid-da">
                    <div class="form-group-da full">
                        <label><i class="fas fa-heading"></i> Activity Title <span class="required">*</span></label>
                        <input type="text" name="title" required maxlength="255" id="edit_title">
                    </div>

                    <div class="form-group-da full">
                        <label>
                            <i class="fas fa-tag"></i> Category
                            <span style="font-size:0.68rem;font-weight:500;color:var(--page-text-secondary);text-transform:none;letter-spacing:0;">
                                (Choose from list or type your own)
                            </span>
                        </label>
                        
                        <div class="category-input-wrapper">
                            <div class="category-mode-tabs">
                                <button type="button" class="category-mode-tab active" id="edit_mode_dropdown_btn" onclick="switchCatMode('edit', 'dropdown')">
                                    <i class="fas fa-list"></i> Choose from list
                                </button>
                                <button type="button" class="category-mode-tab" id="edit_mode_manual_btn" onclick="switchCatMode('edit', 'manual')">
                                    <i class="fas fa-pen"></i> Type your own
                                </button>
                            </div>
                            
                            <div class="category-mode-content active" id="edit_dropdown_mode">
                                <div class="category-dropdown-wrapper">
                                    <span class="cat-icon-preview" id="editCatIcon">
                                        <i class="fas fa-tag"></i>
                                    </span>
                                    <select id="edit_category_select" onchange="selectCategoryFromDropdown('edit')">
                                        <option value="">-- Select Category --</option>
                                        <?php foreach ($predefined_categories as $cat_name => $cat_data): ?>
                                            <option value="<?= htmlspecialchars($cat_name) ?>" data-icon="<?= $cat_data['icon'] ?>" data-color="<?= $cat_data['color'] ?>">
                                                <?= htmlspecialchars($cat_name) ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <?php foreach ($categories as $cat) {
                                            if (!isset($predefined_categories[$cat])) {
                                                echo '<option value="' . htmlspecialchars($cat) . '" data-icon="fa-tag" data-color="#0B5ED7">' . htmlspecialchars($cat) . '</option>';
                                            }
                                        } ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="category-mode-content" id="edit_manual_mode">
                                <div class="manual-input-wrapper">
                                    <i class="fas fa-pen manual-icon"></i>
                                    <input type="text" id="edit_category_manual" placeholder="Type your custom category..." maxlength="100" autocomplete="off" oninput="syncManualCategory('edit')">
                                </div>
                            </div>
                            
                            <input type="hidden" name="category" id="edit_category_final" value="">
                        </div>
                    </div>

                    <div class="form-group-da">
                        <label><i class="fas fa-info-circle"></i> Status</label>
                        <select name="status" id="edit_status">
                            <option value="completed">✅ Completed</option>
                            <option value="in_progress">🔄 In Progress</option>
                            <option value="pending">⏳ Pending</option>
                            <option value="cancelled">❌ Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group-da">
                        <label><i class="fas fa-flag"></i> Priority</label>
                        <select name="priority" id="edit_priority">
                            <option value="low">🟢 Low</option>
                            <option value="medium">🟡 Medium</option>
                            <option value="high">🟠 High</option>
                            <option value="urgent">🔴 Urgent</option>
                        </select>
                    </div>

                    <div class="form-group-da full">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" maxlength="2000" id="edit_description"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer-da">
                <button type="button" class="btn-modal-da cancel" onclick="closeEditModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-modal-da submit">
                    <i class="fas fa-save"></i> Update Activity
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// CATEGORY MODE SWITCHING
function switchCatMode(prefix, mode) {
    var dropdownBtn = document.getElementById(prefix + '_mode_dropdown_btn');
    var manualBtn = document.getElementById(prefix + '_mode_manual_btn');
    var dropdownContent = document.getElementById(prefix + '_dropdown_mode');
    var manualContent = document.getElementById(prefix + '_manual_mode');
    
    if (mode === 'dropdown') {
        dropdownBtn.classList.add('active');
        manualBtn.classList.remove('active');
        dropdownContent.classList.add('active');
        manualContent.classList.remove('active');
        syncDropdownCategory(prefix);
    } else {
        dropdownBtn.classList.remove('active');
        manualBtn.classList.add('active');
        dropdownContent.classList.remove('active');
        manualContent.classList.add('active');
        syncManualCategory(prefix);
        setTimeout(function() {
            var input = document.getElementById(prefix + '_category_manual');
            if (input) input.focus();
        }, 150);
    }
}

function selectCategoryFromDropdown(prefix) {
    var select = document.getElementById(prefix + '_category_select');
    var iconBox = document.getElementById(prefix + 'CatIcon');
    var hidden = document.getElementById(prefix + '_category_final');
    if (!select) return;
    var selectedOption = select.options[select.selectedIndex];
    var iconClass = selectedOption ? (selectedOption.getAttribute('data-icon') || 'fa-tag') : 'fa-tag';
    if (iconBox) iconBox.innerHTML = '<i class="fas ' + iconClass + '"></i>';
    if (hidden) hidden.value = select.value;
}

function syncManualCategory(prefix) {
    var input = document.getElementById(prefix + '_category_manual');
    var hidden = document.getElementById(prefix + '_category_final');
    if (input && hidden) hidden.value = input.value.trim();
}

function syncDropdownCategory(prefix) {
    var select = document.getElementById(prefix + '_category_select');
    var hidden = document.getElementById(prefix + '_category_final');
    if (select && hidden) hidden.value = select.value;
}

function initCategoryField(prefix, existingValue) {
    existingValue = existingValue || '';
    var select = document.getElementById(prefix + '_category_select');
    var manualInput = document.getElementById(prefix + '_category_manual');
    var hidden = document.getElementById(prefix + '_category_final');
    var iconBox = document.getElementById(prefix + 'CatIcon');
    if (iconBox) iconBox.innerHTML = '<i class="fas fa-tag"></i>';
    
    var foundInDropdown = false;
    if (select && existingValue) {
        for (var i = 0; i < select.options.length; i++) {
            if (select.options[i].value === existingValue) {
                select.selectedIndex = i;
                foundInDropdown = true;
                var iconClass = select.options[i].getAttribute('data-icon') || 'fa-tag';
                if (iconBox) iconBox.innerHTML = '<i class="fas ' + iconClass + '"></i>';
                break;
            }
        }
    }
    
    if (foundInDropdown) {
        if (hidden) hidden.value = select.value;
        switchCatMode(prefix, 'dropdown');
    } else if (existingValue) {
        if (manualInput) manualInput.value = existingValue;
        if (hidden) hidden.value = existingValue;
        switchCatMode(prefix, 'manual');
    } else {
        if (select) select.selectedIndex = 0;
        if (manualInput) manualInput.value = '';
        if (hidden) hidden.value = '';
        switchCatMode(prefix, 'dropdown');
    }
}

// ACTIVITIES DATA
var ACTIVITIES_DATA = {
    <?php foreach ($activities as $act): ?>
    "<?= (int)$act['id'] ?>": {
        id: <?= (int)$act['id'] ?>,
        title: <?= json_encode($act['title'] ?? '') ?>,
        description: <?= json_encode($act['description'] ?? '') ?>,
        category: <?= json_encode($act['category'] ?? '') ?>,
        status: <?= json_encode($act['status'] ?? 'completed') ?>,
        priority: <?= json_encode($act['priority'] ?? 'medium') ?>
    },
    <?php endforeach; ?>
};

// ADD MODAL
function openAddModal() {
    document.getElementById('addActivityForm').reset();
    initCategoryField('add', '');
    document.getElementById('addModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    setTimeout(function() { document.getElementById('add_title').focus(); }, 200);
}
function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
    document.body.style.overflow = '';
}

// EDIT MODAL
function openEditModal(activityId) {
    var data = ACTIVITIES_DATA[activityId];
    if (!data) { alert('Activity not found.'); return; }
    
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_title').value = data.title;
    document.getElementById('edit_status').value = data.status;
    document.getElementById('edit_description').value = data.description;
    document.getElementById('edit_priority').value = data.priority;
    
    initCategoryField('edit', data.category);
    
    document.getElementById('editModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    setTimeout(function() { document.getElementById('edit_title').focus(); }, 200);
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('addModal').addEventListener('click', function(e) {
    if (e.target === this) closeAddModal();
});
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddModal();
        closeEditModal();
    }
});

// HIGHLIGHT HELPERS
function escapeRegex(str) { return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
function escapeHtml(str) { var div = document.createElement('div'); div.textContent = str; return div.innerHTML; }

// LIVE SEARCH
document.addEventListener('DOMContentLoaded', function() {
    var globalInput = document.getElementById('globalSearchInput');
    var clearBtn = document.getElementById('globalClearBtn');
    var resultInfo = document.getElementById('globalResultInfo');
    var noMatchMsg = document.getElementById('noMatchMsg');
    
    if (!globalInput) return;
    
    function applySearch() {
        var query = globalInput.value.trim().toLowerCase();
        var rows = document.querySelectorAll('#activitiesTable tbody tr');
        var matchCount = 0;
        
        if (query.length > 0) clearBtn.classList.add('visible');
        else clearBtn.classList.remove('visible');
        
        rows.forEach(function(row) {
            var searchable = row.getAttribute('data-searchable') || '';
            var titleEl = row.querySelector('.activity-title-da');
            var descEl = row.querySelector('.activity-desc-da');
            if (titleEl) titleEl.innerHTML = titleEl.textContent;
            if (descEl) descEl.innerHTML = descEl.textContent;
            
            if (query === '' || searchable.indexOf(query) !== -1) {
                row.classList.remove('hidden-by-search');
                matchCount++;
                
                if (query !== '') {
                    var regex = new RegExp('(' + escapeRegex(query) + ')', 'gi');
                    if (titleEl) titleEl.innerHTML = escapeHtml(titleEl.textContent).replace(regex, '<mark class="hl-da">$1</mark>');
                    if (descEl) descEl.innerHTML = escapeHtml(descEl.textContent).replace(regex, '<mark class="hl-da">$1</mark>');
                }
            } else {
                row.classList.add('hidden-by-search');
            }
        });
        
        if (query === '') {
            resultInfo.innerHTML = 'Showing <strong>' + matchCount + '</strong> activit' + (matchCount !== 1 ? 'ies' : 'y');
        } else {
            resultInfo.innerHTML = 'Found <strong>' + matchCount + '</strong> activit' + (matchCount !== 1 ? 'ies' : 'y') + ' matching "<em>' + escapeHtml(query) + '</em>"';
        }
        
        if (noMatchMsg) {
            noMatchMsg.style.display = (matchCount === 0 && query !== '') ? 'block' : 'none';
        }
    }
    
    var debounce;
    globalInput.addEventListener('input', function() {
        clearTimeout(debounce);
        debounce = setTimeout(applySearch, 150);
    });
    
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            globalInput.value = '';
            applySearch();
            globalInput.focus();
        });
    }
    
    if (globalInput.value.trim() !== '') applySearch();
    
    globalInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { globalInput.value = ''; applySearch(); }
    });
});

// Footer clock
setInterval(function() {
    var now = new Date();
    var t = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = t;
}, 1000);

console.log('%c📞 Reception - Daily Activities', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
</script>

</body>
</html>