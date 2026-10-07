<?php
// ================================================================
// FILE: frontend/pages/admin/daily_activities.php
// SUPER ADMIN - DAILY ACTIVITIES (ONLY EMPLOYEES WITH ACTIVITIES)
// ✅ Shows ONLY employees WITH activities
// ✅ NO role display (just full name)
// ✅ Search bar per employee
// ✅ Actions per activity row: View, Edit, Delete
// ✅ View modal shows FULL activity details
// ✅ Edit modal allows admin to modify
// ✅ Delete with confirmation
// ================================================================

date_default_timezone_set('Africa/Dar_es_Salaam');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id          = $_SESSION['user_id'];
$user_full_name   = $_SESSION['full_name'] ?? 'Admin';
$user_role        = $_SESSION['role'] ?? 'admin';
$user_branch_id   = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic      = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$selected_branch_id = $_GET['branch'] ?? 'all';

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
        INDEX idx_activity_date (activity_date),
        INDEX idx_branch (branch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    error_log("daily_activities table check: " . $e->getMessage());
}

// ================================================================
// FLASH MESSAGES
// ================================================================
$flash_message = '';
$flash_type = '';

// ================================================================
// HANDLE ADD ACTIVITY
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_activity') {
    try {
        $act_date = $_POST['activity_date'] ?? date('Y-m-d');
        $act_title = trim($_POST['title'] ?? '');
        $act_desc = trim($_POST['description'] ?? '');
        $act_category = trim($_POST['category'] ?? '');
        $act_start = $_POST['start_time'] ?? null;
        $act_end = $_POST['end_time'] ?? null;
        $act_status = $_POST['status'] ?? 'completed';
        $act_priority = $_POST['priority'] ?? 'medium';
        $act_user_id = (int)($_POST['activity_user_id'] ?? $user_id);

        $act_duration = null;
        if (!empty($act_start) && !empty($act_end)) {
            $t1 = strtotime($act_start);
            $t2 = strtotime($act_end);
            if ($t2 > $t1) $act_duration = (int)round(($t2 - $t1) / 60);
        }

        if (empty($act_title)) throw new Exception("Activity title is required.");

        $stmt = $db->prepare("
            INSERT INTO daily_activities 
                (user_id, branch_id, activity_date, title, description, category,
                 start_time, end_time, duration_minutes, status, priority, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $act_user_id,
            $selected_branch_id !== 'all' && is_numeric($selected_branch_id) ? (int)$selected_branch_id : $user_branch_id,
            $act_date, $act_title, $act_desc, $act_category,
            $act_start ?: null, $act_end ?: null, $act_duration,
            $act_status, $act_priority
        ]);

        $flash_message = "✅ Activity added successfully!";
        $flash_type = 'success';
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

        $stmt = $db->prepare("
            UPDATE daily_activities 
            SET title = ?, description = ?, category = ?, 
                status = ?, priority = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $act_title, $act_desc, $act_category,
            $act_status, $act_priority, $act_id
        ]);

        $flash_message = "✅ Activity updated successfully!";
        $flash_type = 'success';
        
    } catch (Exception $e) {
        $flash_message = "❌ Error: " . $e->getMessage();
        $flash_type = 'error';
    }
}

// ================================================================
// HANDLE DELETE ACTIVITY
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_activity') {
    try {
        $act_id = (int)($_POST['activity_id'] ?? 0);
        if ($act_id <= 0) throw new Exception("Invalid activity.");

        $stmt = $db->prepare("DELETE FROM daily_activities WHERE id = ?");
        $stmt->execute([$act_id]);

        $flash_message = "✅ Activity deleted successfully!";
        $flash_type = 'success';
    } catch (Exception $e) {
        $flash_message = "❌ Error: " . $e->getMessage();
        $flash_type = 'error';
    }
}

// ================================================================
// FILTERS
// ================================================================
$filter_type     = $_GET['filter'] ?? 'all';
$date_from       = $_GET['date_from'] ?? date('Y-m-d');
$date_to         = $_GET['date_to'] ?? date('Y-m-d');
$time_from       = $_GET['time_from'] ?? '';
$time_to         = $_GET['time_to'] ?? '';
$employee_filter = (int)($_GET['employee_id'] ?? 0);
$search_global   = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');

function toAmPm($time24) {
    if (empty($time24)) return '';
    $ts = strtotime($time24);
    return $ts ? date('g:i A', $ts) : $time24;
}

// Build date range
$where_date = '';
$date_params = [];

switch ($filter_type) {
    case 'all': $where_date = "1=1"; break;
    case 'today': $where_date = "da.activity_date = CURDATE()"; break;
    case '1d': $where_date = "da.activity_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)"; break;
    case '1w': $where_date = "da.activity_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)"; break;
    case '1m': $where_date = "da.activity_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)"; break;
    case '3m': $where_date = "da.activity_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)"; break;
    case '6m': $where_date = "da.activity_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)"; break;
    case '1y': $where_date = "da.activity_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)"; break;
    case 'custom':
        $where_date = "da.activity_date BETWEEN ? AND ?";
        $date_params = [$date_from, $date_to];
        break;
    default: $where_date = "1=1";
}

// ================================================================
// GET ONLY EMPLOYEES WITH ACTIVITIES
// ================================================================
$where = [$where_date];
$params = $date_params;

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $where[] = "da.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

if ($employee_filter > 0) {
    $where[] = "da.user_id = ?";
    $params[] = $employee_filter;
}

if (!empty($time_from)) {
    $where[] = "da.start_time >= ?";
    $params[] = $time_from . ':00';
}
if (!empty($time_to)) {
    $where[] = "da.start_time <= ?";
    $params[] = $time_to . ':00';
}
if (!empty($category_filter)) {
    $where[] = "da.category = ?";
    $params[] = $category_filter;
}
if (!empty($search_global)) {
    $where[] = "(da.title LIKE ? OR da.description LIKE ? OR da.category LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search_global%";
    $params[] = "%$search_global%";
    $params[] = "%$search_global%";
    $params[] = "%$search_global%";
}

$where_clause = implode(" AND ", $where);

// ✅ IMPORTANT: INNER JOIN - only employees WITH activities
$sql = "
    SELECT 
        da.id, da.user_id, da.activity_date, da.title, da.description,
        da.category, da.start_time, da.end_time, da.duration_minutes,
        da.status, da.priority, da.created_at,
        u.full_name AS employee_name,
        u.profile_pic AS employee_pic,
        b.name AS branch_name
    FROM daily_activities da
    INNER JOIN users u ON da.user_id = u.id
    LEFT JOIN branches b ON da.branch_id = b.id
    WHERE $where_clause
    ORDER BY da.activity_date DESC, da.start_time DESC, da.id DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$all_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by employee
$grouped = [];
foreach ($all_activities as $act) {
    $emp_id = (int)$act['user_id'];
    if (!isset($grouped[$emp_id])) {
        $grouped[$emp_id] = [
            'employee_id'   => $emp_id,
            'employee_name' => $act['employee_name'],
            'employee_pic'  => $act['employee_pic'],
            'branch_name'   => $act['branch_name'],
            'activities'    => []
        ];
    }
    $grouped[$emp_id]['activities'][] = $act;
}

// Sort by activity count DESC
uasort($grouped, function($a, $b) {
    return count($b['activities']) - count($a['activities']);
});

// ================================================================
// STATS
// ================================================================
$total_activities = count($all_activities);
$total_employees_with_activities = count($grouped);

$today_activities = 0;
try {
    if ($selected_branch_id === 'all') {
        $stmt = $db->query("SELECT COUNT(*) FROM daily_activities WHERE activity_date = CURDATE()");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM daily_activities WHERE activity_date = CURDATE() AND branch_id = ?");
        $stmt->execute([(int)$selected_branch_id]);
    }
    $today_activities = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

$categories = [];
try {
    $stmt = $db->query("SELECT DISTINCT category FROM daily_activities WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$employees_dropdown = [];
try {
    if ($selected_branch_id === 'all') {
        $stmt = $db->query("
            SELECT DISTINCT u.id, u.full_name, u.role 
            FROM users u 
            INNER JOIN daily_activities da ON u.id = da.user_id 
            WHERE u.role != 'admin' 
            ORDER BY u.full_name
        ");
    } else {
        $stmt = $db->prepare("
            SELECT DISTINCT u.id, u.full_name, u.role 
            FROM users u 
            INNER JOIN daily_activities da ON u.id = da.user_id 
            WHERE u.role != 'admin' AND u.branch_id = ?
            ORDER BY u.full_name
        ");
        $stmt->execute([(int)$selected_branch_id]);
    }
    $employees_dropdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// HELPERS
// ================================================================
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
function priorityMeta($priority) {
    $map = [
        'low'    => ['label' => 'Low',    'color' => '#64748B'],
        'medium' => ['label' => 'Medium', 'color' => '#D97706'],
        'high'   => ['label' => 'High',   'color' => '#EA580C'],
        'urgent' => ['label' => 'Urgent', 'color' => '#DC2626'],
    ];
    return $map[$priority] ?? $map['medium'];
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<style>
    :root {
        --page-primary: #0B5ED7;
        --page-primary-dark: #0A4CA8;
        --page-primary-bg: #E8F0FE;
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-border: #E2E8F0;
        --page-hover: #F8FAFC;
    }
    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-border: #334155;
        --page-hover: #0F172A;
        --page-primary-bg: #1E3A5F;
    }

    body, .main-content { background: var(--page-bg-body); }
    html[data-theme="dark"] body,
    html[data-theme="dark"] .main-content { background: #0F172A !important; }

    /* PAGE HEADER */
    .page-header-da {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4FB0 50%, #083D8A 100%);
        border-radius: 20px; padding: 26px 32px; margin-bottom: 22px;
        display: flex; flex-wrap: wrap; justify-content: space-between;
        align-items: center; gap: 16px; color: white;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.35), 0 4px 12px rgba(11, 94, 215, 0.2);
        position: relative; overflow: hidden;
    }
    .page-header-da::before {
        content: ''; position: absolute; top: -50%; right: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%; pointer-events: none;
    }
    .page-header-da h1 {
        font-size: 1.55rem; font-weight: 800; margin: 0 0 8px 0;
        display: flex; align-items: center; gap: 12px;
        position: relative; z-index: 2;
    }
    .page-header-da h1 i {
        width: 46px; height: 46px; background: rgba(255,255,255,0.2);
        border-radius: 12px; display: flex; align-items: center; justify-content: center;
        font-size: 1.25rem; backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }
    .page-header-da .subtitle {
        font-size: 0.88rem; color: rgba(255,255,255,0.9);
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        position: relative; z-index: 2;
    }
    .page-header-da .badge-info {
        background: rgba(255,255,255,0.15); padding: 4px 14px;
        border-radius: 20px; font-size: 0.72rem; font-weight: 600;
        backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.25);
        display: inline-flex; align-items: center; gap: 5px;
    }
    .btn-add-activity {
        background: linear-gradient(135deg, #10B981, #059669);
        color: white; padding: 12px 24px; border-radius: 12px;
        font-weight: 700; font-size: 0.88rem; border: none; cursor: pointer;
        display: inline-flex; align-items: center; gap: 10px;
        transition: all 0.3s ease; text-decoration: none;
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        position: relative; z-index: 2; white-space: nowrap;
    }
    .btn-add-activity:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(16, 185, 129, 0.55);
        color: white;
    }

    /* Flash */
    .flash-da {
        padding: 14px 20px; border-radius: 12px; margin-bottom: 18px;
        font-weight: 600; font-size: 0.88rem;
        display: flex; align-items: center; gap: 12px;
        animation: slideDown 0.4s ease;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .flash-da.success { background: #D1FAE5; color: #059669; border: 2px solid #059669; }
    .flash-da.error { background: #FEE2E2; color: #DC2626; border: 2px solid #DC2626; }
    html[data-theme="dark"] .flash-da.success { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .flash-da.error { background: #3A1A1A; color: #F87171; }

    /* STATS */
    .stats-grid-da {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 14px; margin-bottom: 20px;
    }
    .stat-card-da {
        background: var(--page-bg-card); border-radius: 14px;
        padding: 18px 20px; border: 2px solid var(--page-border);
        display: flex; align-items: center; gap: 14px;
        transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .stat-card-da:hover {
        transform: translateY(-3px); border-color: var(--page-primary);
        box-shadow: 0 10px 28px rgba(11, 94, 215, 0.12);
    }
    .stat-icon-da {
        width: 50px; height: 50px; border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; flex-shrink: 0; color: white;
    }
    .stat-icon-da.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .stat-icon-da.green { background: linear-gradient(135deg, #059669, #047857); }
    .stat-icon-da.orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-icon-da.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .stat-card-da .stat-value {
        font-size: 1.5rem; font-weight: 800; color: var(--page-text-primary);
        margin: 0; line-height: 1.1; font-family: 'Courier New', monospace;
    }
    .stat-card-da .stat-label {
        font-size: 0.68rem; color: var(--page-text-secondary);
        text-transform: uppercase; font-weight: 700;
        letter-spacing: 0.05em; margin: 4px 0 0 0;
    }

    /* GLOBAL SEARCH */
    .global-search-wrap {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 16px; padding: 20px 24px; margin-bottom: 20px;
        box-shadow: 0 6px 24px rgba(11, 94, 215, 0.3);
        position: relative; overflow: hidden;
    }
    .global-search-wrap::before {
        content: ''; position: absolute; top: -50%; right: -5%;
        width: 300px; height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        border-radius: 50%;
    }
    .global-search-box {
        position: relative; display: flex; align-items: center; z-index: 2;
    }
    .global-search-box i.search-icon {
        position: absolute; left: 18px; color: #0B5ED7;
        font-size: 1.05rem; pointer-events: none; z-index: 3;
    }
    .global-search-box input {
        width: 100%; padding: 15px 130px 15px 50px;
        border: none; border-radius: 12px; font-size: 0.95rem; font-weight: 600;
        background: white; color: #1E293B; outline: none; font-family: inherit;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    [data-theme="dark"] .global-search-box input { background: #1E293B; color: #F1F5F9; }
    .global-search-box input::placeholder { color: #94A3B8; font-weight: 500; }
    .global-search-box .search-hint {
        position: absolute; right: 18px; font-size: 0.68rem; font-weight: 700;
        color: #64748B; background: #F1F5F9; padding: 4px 12px;
        border-radius: 12px; pointer-events: none; z-index: 3;
    }
    .global-search-box .clear-btn {
        position: absolute; right: 100px; background: transparent; border: none;
        color: #DC2626; font-size: 0.95rem; cursor: pointer;
        padding: 6px 10px; border-radius: 8px; display: none; z-index: 4;
    }
    .global-search-box .clear-btn.visible { display: block; }
    .search-results-count {
        text-align: center; margin-top: 12px; font-size: 0.78rem;
        color: rgba(255,255,255,0.9); font-weight: 600;
        position: relative; z-index: 2;
    }
    .search-results-count strong { color: white; font-weight: 800; }

    /* FILTERS */
    .filter-card-da {
        background: var(--page-bg-card); border-radius: 14px;
        padding: 18px 22px; border: 2px solid var(--page-border);
        margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .filter-title-da {
        font-size: 0.78rem; font-weight: 700; color: var(--page-text-primary);
        margin-bottom: 12px; display: flex; align-items: center; gap: 8px;
        text-transform: uppercase; letter-spacing: 0.05em;
    }
    .filter-title-da i { color: var(--page-primary); }
    .filter-chips-da { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
    .filter-chip-da {
        padding: 7px 16px; border-radius: 20px; font-size: 0.74rem; font-weight: 600;
        background: var(--page-bg-body); color: var(--page-text-secondary);
        border: 2px solid var(--page-border); cursor: pointer;
        transition: all 0.3s ease; text-decoration: none;
        display: inline-flex; align-items: center; gap: 6px; font-family: inherit;
    }
    .filter-chip-da:hover {
        border-color: var(--page-primary); color: var(--page-primary);
        transform: translateY(-2px);
    }
    .filter-chip-da.active {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white; border-color: transparent;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    .filter-row-da {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px; align-items: end;
    }
    .filter-group-da label {
        font-size: 0.65rem; font-weight: 700; color: var(--page-text-secondary);
        text-transform: uppercase; margin-bottom: 5px; display: block;
        letter-spacing: 0.04em;
    }
    .filter-group-da input,
    .filter-group-da select {
        width: 100%; padding: 9px 12px; border: 2px solid var(--page-border);
        border-radius: 10px; font-size: 0.8rem;
        background: var(--page-bg-card); color: var(--page-text-primary);
        outline: none; font-family: inherit; transition: all 0.3s; font-weight: 500;
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
        padding: 9px 20px; border-radius: 10px; font-weight: 600;
        font-size: 0.8rem; border: none; cursor: pointer;
        transition: all 0.3s; display: inline-flex;
        align-items: center; justify-content: center; gap: 8px;
        text-decoration: none; font-family: inherit; min-height: 40px;
    }
    .btn-da:hover { transform: translateY(-2px); }
    .btn-primary-da {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
    }
    .btn-outline-da {
        background: transparent; color: var(--page-text-secondary);
        border: 2px solid var(--page-border);
    }
    .btn-outline-da:hover {
        border-color: var(--page-primary); color: var(--page-primary);
        background: var(--page-primary-bg);
    }

    /* EMPLOYEE CARDS */
    .employee-card-da {
        border-radius: 16px; border: 2px solid var(--page-border);
        margin-bottom: 14px; overflow: hidden;
        transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        background: var(--page-bg-card);
    }
    .employee-card-da:hover {
        border-color: var(--page-primary);
        box-shadow: 0 8px 28px rgba(11, 94, 215, 0.15);
    }
    .employee-card-da.open {
        border-color: var(--page-primary);
        box-shadow: 0 12px 36px rgba(11, 94, 215, 0.2);
    }

    /* HEADER - BLUE THEME */
    .employee-header-da {
        padding: 14px 20px;
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4FB0 50%, #083D8A 100%) !important;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        position: relative;
    }

    .employee-info-da {
        display: flex; align-items: center; gap: 14px;
        min-width: 0; flex: 1;
    }

    .employee-avatar-da {
        width: 46px; height: 46px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem; font-weight: 800; color: white; flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        border: 3px solid rgba(255,255,255,0.4);
        cursor: pointer;
        overflow: hidden;
        position: relative;
    }
    .employee-avatar-da img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .employee-avatar-da .avatar-initials {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        font-weight: 800;
        color: white;
        text-transform: uppercase;
    }

    .employee-name-da {
        font-size: 1rem; font-weight: 800;
        color: #FFFFFF;
        margin: 0;
        display: flex; align-items: center;
        gap: 8px; flex-wrap: wrap;
    }
    .employee-meta-da {
        font-size: 0.72rem;
        color: rgba(255,255,255,0.85);
        margin: 4px 0 0 0;
        display: flex; align-items: center;
        gap: 12px; flex-wrap: wrap;
        font-weight: 500;
    }
    .employee-meta-da span { display: inline-flex; align-items: center; gap: 4px; }
    .employee-meta-da span i { color: #93C5FD; }

    /* TOP BADGE */
    .top-badge {
        font-size: 0.62rem;
        padding: 2px 10px;
        border-radius: 10px;
        background: #FEF3C7;
        color: #92400E;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    /* INLINE SEARCH */
    .header-search-wrap {
        flex: 1;
        min-width: 220px;
        max-width: 420px;
        position: relative;
        display: flex;
        align-items: center;
    }
    .header-search-wrap i.hs-icon {
        position: absolute;
        left: 12px;
        font-size: 0.82rem;
        color: #93C5FD;
        pointer-events: none;
        z-index: 2;
    }
    .header-search-wrap input.header-search-input {
        width: 100%;
        padding: 9px 36px 9px 34px;
        border: 2px solid rgba(255,255,255,0.25);
        border-radius: 10px;
        font-size: 0.78rem;
        font-weight: 600;
        background: rgba(255,255,255,0.15);
        color: white;
        outline: none;
        font-family: inherit;
        transition: all 0.3s;
    }
    .header-search-wrap input.header-search-input::placeholder {
        color: rgba(255,255,255,0.7);
    }
    .header-search-wrap input.header-search-input:focus {
        background: rgba(255,255,255,0.25);
        border-color: white;
        box-shadow: 0 0 0 3px rgba(255,255,255,0.2);
    }
    .header-search-wrap .header-clear-btn {
        position: absolute;
        right: 6px;
        background: transparent;
        border: none;
        color: white;
        font-size: 0.8rem;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 6px;
        display: none;
        z-index: 2;
    }
    .header-search-wrap .header-clear-btn:hover { background: rgba(255,255,255,0.2); }
    .header-search-wrap .header-clear-btn.visible { display: block; }

    .employee-actions-da {
        display: flex; align-items: center; gap: 10px; flex-shrink: 0;
    }
    .activity-count-badge-da {
        display: flex; align-items: center; gap: 6px;
        padding: 7px 16px;
        background: rgba(255,255,255,0.2);
        border: 1.5px solid rgba(255,255,255,0.35);
        color: white; border-radius: 20px;
        font-weight: 800; font-size: 0.78rem;
        font-family: 'Courier New', monospace;
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .activity-count-badge-da:hover {
        transform: scale(1.05);
        background: rgba(255,255,255,0.3);
    }
    .toggle-arrow-da {
        width: 36px; height: 36px; border-radius: 50%;
        background: rgba(255,255,255,0.15);
        border: 2px solid rgba(255,255,255,0.3);
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 0.9rem;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        flex-shrink: 0;
        cursor: pointer;
    }
    .employee-card-da.open .toggle-arrow-da {
        transform: rotate(180deg);
        background: white;
        color: #0B5ED7;
        border-color: white;
    }

    /* Body */
    .employee-body-da {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        background: var(--page-bg-card);
        border-top: 1px solid var(--page-border);
    }
    .employee-card-da.open .employee-body-da {
        max-height: 5000px;
        transition: max-height 1s ease-in-out;
    }

    /* Table */
    .activities-table-da {
        width: 100%; border-collapse: collapse; font-size: 0.82rem;
    }
    .activities-table-da thead th {
        background: var(--page-bg-body);
        color: var(--page-text-secondary);
        font-weight: 800; padding: 12px 16px;
        font-size: 0.64rem; text-transform: uppercase;
        letter-spacing: 0.05em; text-align: left;
        border-bottom: 2px solid var(--page-border);
        white-space: nowrap;
    }
    [data-theme="dark"] .activities-table-da thead th { background: #0F172A; }
    .activities-table-da td {
        padding: 13px 16px;
        border-bottom: 1px solid var(--page-border);
        color: var(--page-text-primary);
        vertical-align: top;
    }
    .activities-table-da tbody tr:last-child td { border-bottom: none; }
    .activities-table-da tbody tr { transition: background 0.2s ease; }
    .activities-table-da tbody tr:hover td { background: var(--page-hover); }
    .activities-table-da tbody tr.hidden-by-search { display: none; }

    .activity-time-block {
        display: flex; flex-direction: column; gap: 3px;
        font-family: 'Courier New', monospace; font-size: 0.78rem;
    }
    .activity-time-block .date-line {
        font-weight: 800; color: var(--page-text-primary);
        display: flex; align-items: center; gap: 5px;
    }
    .activity-time-block .date-line i { color: #0B5ED7; font-size: 0.72rem; }
    .activity-time-block .time-line {
        font-weight: 700; color: #0B5ED7;
        display: flex; align-items: center; gap: 5px;
    }
    .activity-time-block .time-line i { color: #0B5ED7; font-size: 0.68rem; }
    .activity-time-block .duration-line {
        font-size: 0.68rem; color: var(--page-text-secondary);
        font-weight: 600; display: flex; align-items: center; gap: 4px;
    }

    .activity-title-da {
        font-weight: 700; color: var(--page-text-primary);
        font-size: 0.85rem; display: block; margin-bottom: 3px;
    }
    .activity-desc-da {
        font-size: 0.72rem; color: var(--page-text-secondary);
        display: block; line-height: 1.5;
    }

    .category-pill-da {
        font-size: 0.62rem; padding: 3px 10px; border-radius: 10px;
        background: var(--page-primary-bg); color: var(--page-primary);
        font-weight: 700; display: inline-block;
        text-transform: uppercase; letter-spacing: 0.03em;
    }
    [data-theme="dark"] .category-pill-da {
        background: #1E3A5F; color: #6EA8FE;
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

    /* ============================================================ */
    /* ✅ ACTION BUTTONS (VIEW, EDIT, DELETE) */
    /* ============================================================ */
    .actions-cell-da {
        display: flex;
        gap: 6px;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
    }
    .btn-row-action {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.78rem;
        border: 1.5px solid transparent;
        cursor: pointer;
        transition: all 0.25s ease;
        text-decoration: none;
        flex-shrink: 0;
    }
    .btn-row-action:hover {
        transform: translateY(-2px) scale(1.08);
    }
    .btn-row-action.view {
        background: rgba(11, 94, 215, 0.1);
        color: #0B5ED7;
        border-color: rgba(11, 94, 215, 0.3);
    }
    .btn-row-action.view:hover {
        background: #0B5ED7;
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.4);
    }
    .btn-row-action.edit {
        background: rgba(217, 119, 6, 0.1);
        color: #D97706;
        border-color: rgba(217, 119, 6, 0.3);
    }
    .btn-row-action.edit:hover {
        background: #D97706;
        color: white;
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.4);
    }
    .btn-row-action.delete {
        background: rgba(220, 38, 38, 0.1);
        color: #DC2626;
        border-color: rgba(220, 38, 38, 0.3);
    }
    .btn-row-action.delete:hover {
        background: #DC2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
    }

    /* HIGHLIGHT */
    mark.hl-da {
        background: #FEF08A; color: #854D0E;
        padding: 1px 3px; border-radius: 3px; font-weight: 900;
    }
    [data-theme="dark"] mark.hl-da {
        background: #854D0E; color: #FEF08A;
    }

    .empty-state-inner-da {
        text-align: center; padding: 40px 20px;
        color: var(--page-text-secondary);
    }
    .empty-state-inner-da i {
        font-size: 2.5rem; opacity: 0.5;
        display: block; margin-bottom: 12px; color: #0B5ED7;
    }
    .empty-state-inner-da .empty-title {
        font-size: 0.95rem; font-weight: 700; margin: 0 0 6px 0;
    }
    .empty-state-inner-da .empty-sub {
        font-size: 0.78rem; margin: 0; opacity: 0.8;
    }

    /* ============================================================ */
    /* ✅ MODALS (Add, View, Edit, Delete) */
    /* ============================================================ */
    .modal-overlay-da {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        width: 100vw; height: 100vh;
        background: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 99999;
        display: none;
        padding: 20px;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
    }
    .modal-overlay-da.active { display: block; }

    .modal-box-da {
        background: var(--page-bg-card);
        border-radius: 20px;
        max-width: 640px;
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
    .modal-box-da.view-modal {
        border-top-color: #0B5ED7;
    }
    .modal-box-da.edit-modal {
        border-top-color: #D97706;
    }
    .modal-box-da.delete-modal {
        border-top-color: #DC2626;
        max-width: 480px;
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
    .modal-box-da.edit-modal .modal-header-da {
        background: linear-gradient(135deg, #D97706, #B45309);
    }
    .modal-box-da.delete-modal .modal-header-da {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
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
    .modal-body-da::-webkit-scrollbar-track { background: var(--page-bg-body); border-radius: 10px; }
    .modal-body-da::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #0B5ED7, #0A4CA8);
        border-radius: 10px;
        border: 2px solid var(--page-bg-body);
    }

    .modal-footer-da {
        padding: 18px 28px;
        border-top: 2px solid var(--page-border);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        background: var(--page-bg-body);
        flex-wrap: wrap;
        flex-shrink: 0;
        box-shadow: 0 -4px 12px rgba(0,0,0,0.06);
        border-radius: 0 0 20px 20px;
    }
    [data-theme="dark"] .modal-footer-da { background: #0F172A; }

    /* VIEW MODAL - DETAILS */
    .view-detail-row {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px dashed var(--page-border);
    }
    .view-detail-row:last-child { border-bottom: none; }
    .view-detail-label {
        min-width: 130px;
        font-size: 0.72rem;
        font-weight: 800;
        color: var(--page-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .view-detail-label i {
        color: #0B5ED7;
        font-size: 0.8rem;
    }
    .view-detail-value {
        flex: 1;
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--page-text-primary);
        line-height: 1.6;
        word-break: break-word;
    }
    .view-detail-value.empty {
        color: var(--page-text-secondary);
        font-style: italic;
        font-weight: 400;
        opacity: 0.7;
    }
    .view-detail-value.highlight-box {
        background: var(--page-primary-bg);
        padding: 10px 14px;
        border-radius: 10px;
        font-weight: 700;
        color: #0B5ED7;
    }
    [data-theme="dark"] .view-detail-value.highlight-box {
        background: #1E3A5F;
        color: #6EA8FE;
    }

    /* FORM */
    .form-grid-da {
        display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
    }
    .form-grid-da .full { grid-column: 1 / -1; }
    .form-group-da label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--page-text-primary);
        margin-bottom: 6px;
    }
    .form-group-da label i { color: #0B5ED7; font-size: 0.8rem; }
    .form-group-da label .required { color: #DC2626; }
    .form-group-da input,
    .form-group-da select,
    .form-group-da textarea {
        width: 100%; padding: 10px 14px;
        border: 2px solid var(--page-border); border-radius: 10px;
        font-size: 0.85rem; background: var(--page-bg-card);
        color: var(--page-text-primary); outline: none;
        font-family: inherit; transition: all 0.3s;
    }
    .form-group-da input:focus,
    .form-group-da select:focus,
    .form-group-da textarea:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
    }
    [data-theme="dark"] .form-group-da input,
    [data-theme="dark"] .form-group-da select,
    [data-theme="dark"] .form-group-da textarea {
        background: #0F172A; color: #F1F5F9;
    }
    [data-theme="dark"] .form-group-da select option {
        background: #1E293B; color: #F1F5F9;
    }
    .form-group-da textarea { min-height: 90px; resize: vertical; }

    .btn-modal-da {
        padding: 11px 24px; border-radius: 10px;
        font-weight: 700; font-size: 0.85rem;
        border: none; cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex; align-items: center; gap: 8px;
        font-family: inherit;
    }
    .btn-modal-da.cancel {
        background: var(--page-bg-card); color: var(--page-text-secondary);
        border: 2px solid var(--page-border);
    }
    .btn-modal-da.cancel:hover { background: var(--page-hover); }
    .btn-modal-da.submit {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white; box-shadow: 0 4px 14px rgba(11, 94, 215, 0.35);
    }
    .btn-modal-da.submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(11, 94, 215, 0.5);
    }
    .btn-modal-da.update {
        background: linear-gradient(135deg, #D97706, #B45309);
        color: white; box-shadow: 0 4px 14px rgba(217, 119, 6, 0.35);
    }
    .btn-modal-da.update:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(217, 119, 6, 0.5);
    }
    .btn-modal-da.danger {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white; box-shadow: 0 4px 14px rgba(220, 38, 38, 0.35);
    }
    .btn-modal-da.danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(220, 38, 38, 0.5);
    }

    /* DELETE MODAL CONTENT */
    .delete-content {
        text-align: center;
        padding: 10px 20px;
    }
    .delete-icon-wrap {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #FEE2E2, #FECACA);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 18px;
        border: 3px solid #DC2626;
        box-shadow: 0 8px 24px rgba(220, 38, 38, 0.25);
    }
    .delete-icon-wrap i {
        font-size: 2.2rem;
        color: #DC2626;
    }
    .delete-title {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--page-text-primary);
        margin: 0 0 10px 0;
    }
    .delete-subtitle {
        font-size: 0.85rem;
        color: var(--page-text-secondary);
        margin: 0 0 18px 0;
        line-height: 1.5;
    }
    .delete-preview {
        background: var(--page-bg-body);
        border-radius: 12px;
        padding: 14px 18px;
        text-align: left;
        margin-bottom: 20px;
        border: 2px solid var(--page-border);
    }
    [data-theme="dark"] .delete-preview { background: #0F172A; }
    .delete-preview .preview-label {
        font-size: 0.65rem;
        font-weight: 800;
        color: var(--page-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0 0 4px 0;
    }
    .delete-preview .preview-value {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--page-text-primary);
        margin: 0 0 10px 0;
        word-break: break-word;
    }
    .delete-preview .preview-value:last-child { margin-bottom: 0; }

    /* Responsive */
    @media (max-width: 1024px) {
        .header-search-wrap { max-width: 100%; }
    }
    @media (max-width: 768px) {
        .page-header-da { padding: 18px 20px; }
        .page-header-da h1 { font-size: 1.2rem; }
        .page-header-da h1 i { width: 38px; height: 38px; font-size: 1rem; }
        .stats-grid-da { grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-card-da { padding: 12px 14px; }
        .stat-icon-da { width: 40px; height: 40px; font-size: 1rem; }
        .stat-card-da .stat-value { font-size: 1.2rem; }
        .filter-row-da { grid-template-columns: 1fr; }
        .employee-header-da { padding: 12px 14px; flex-direction: column; align-items: stretch; }
        .employee-avatar-da { width: 40px; height: 40px; font-size: 1rem; }
        .employee-name-da { font-size: 0.88rem; }
        .activity-count-badge-da { padding: 5px 12px; font-size: 0.72rem; }
        .toggle-arrow-da { width: 30px; height: 30px; font-size: 0.78rem; }
        .header-search-wrap { min-width: 100%; max-width: 100%; order: 3; }
        .employee-actions-da { order: 2; }
        .global-search-box input { padding: 12px 90px 12px 42px; font-size: 0.85rem; }
        .activities-table-da { font-size: 0.72rem; }
        .activities-table-da td, .activities-table-da thead th { padding: 10px 8px; }
        .form-grid-da { grid-template-columns: 1fr; }

        .modal-overlay-da { padding: 0; }
        .modal-box-da { 
            max-width: 100%; margin: 0; border-radius: 0;
            min-height: 100vh; max-height: none;
        }
        .modal-header-da { 
            padding: 16px 18px; border-radius: 0;
            position: sticky; top: 0; z-index: 20;
        }
        .modal-header-da h3 { font-size: 1rem; }
        .modal-body-da { padding: 18px 16px; max-height: none; flex: 1; }
        .modal-footer-da { 
            padding: 14px 18px;
            flex-direction: column-reverse;
            border-radius: 0;
            position: sticky; bottom: 0; z-index: 20;
        }
        .btn-modal-da { width: 100%; justify-content: center; }
        .view-detail-label { min-width: 100%; margin-bottom: 4px; }
        .view-detail-row { flex-direction: column; gap: 4px; }
    }
    @media (max-width: 480px) {
        .stats-grid-da { grid-template-columns: 1fr; }
        .btn-add-activity { padding: 10px 16px; font-size: 0.78rem; }
    }
</style>

<main class="main-content">

    <!-- Page Header -->
    <div class="page-header-da">
        <div>
            <h1><i class="fas fa-tasks"></i> Daily Activities</h1>
            <p class="subtitle">
                <i class="fas fa-users"></i>
                <strong><?= number_format($total_employees_with_activities) ?></strong> employees with activities
                <span class="badge-info"><i class="fas fa-list-check"></i> <?= number_format($total_activities) ?> total activities</span>
                <span class="badge-info" style="background:rgba(52,211,153,0.3);"><i class="fas fa-calendar-day"></i> <?= number_format($today_activities) ?> today</span>
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
            <div class="stat-icon-da blue"><i class="fas fa-list-check"></i></div>
            <div><p class="stat-value"><?= number_format($total_activities) ?></p><p class="stat-label">Total Activities</p></div>
        </div>
        <div class="stat-card-da">
            <div class="stat-icon-da green"><i class="fas fa-user-check"></i></div>
            <div><p class="stat-value"><?= number_format($total_employees_with_activities) ?></p><p class="stat-label">Active Employees</p></div>
        </div>
        <div class="stat-card-da">
            <div class="stat-icon-da orange"><i class="fas fa-calendar-day"></i></div>
            <div><p class="stat-value"><?= number_format($today_activities) ?></p><p class="stat-label">Today's Activities</p></div>
        </div>
        <div class="stat-card-da">
            <div class="stat-icon-da purple"><i class="fas fa-percent"></i></div>
            <div>
                <p class="stat-value">
                    <?= $total_employees_with_activities > 0 
                        ? number_format(($today_activities / max(1, $total_activities)) * 100, 1) 
                        : '0' ?>%
                </p>
                <p class="stat-label">Today's Share</p>
            </div>
        </div>
    </div>

    <!-- Global Search -->
    <div class="global-search-wrap">
        <div class="global-search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="globalSearchInput" placeholder="Search employee name OR activity title..." autocomplete="off" value="<?= htmlspecialchars($search_global) ?>">
            <button type="button" class="clear-btn" id="globalClearBtn"><i class="fas fa-times-circle"></i></button>
            <span class="search-hint"><i class="fas fa-bolt"></i> LIVE</span>
        </div>
        <div class="search-results-count" id="globalResultCount">
            Showing <strong><?= number_format(count($grouped)) ?></strong> employees
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card-da">
        <div class="filter-title-da"><i class="fas fa-filter"></i> Filters</div>

        <div class="filter-chips-da">
            <?php
            $filter_options = [
                'all' => ['label' => 'All Time', 'icon' => 'fa-infinity'],
                'today' => ['label' => 'Today', 'icon' => 'fa-calendar-day'],
                '1d' => ['label' => '1 Day', 'icon' => 'fa-calendar'],
                '1w' => ['label' => '1 Week', 'icon' => 'fa-calendar-week'],
                '1m' => ['label' => '1 Month', 'icon' => 'fa-calendar-alt'],
                '3m' => ['label' => '3 Months', 'icon' => 'fa-calendar-alt'],
                '6m' => ['label' => '6 Months', 'icon' => 'fa-calendar-alt'],
                '1y' => ['label' => '1 Year', 'icon' => 'fa-calendar-check'],
                'custom' => ['label' => 'Custom', 'icon' => 'fa-sliders-h'],
            ];
            foreach ($filter_options as $key => $opt):
                $active = ($filter_type === $key) ? 'active' : '';
            ?>
                <a href="?branch=<?= urlencode($selected_branch_id) ?>&filter=<?= $key ?>&employee_id=<?= $employee_filter ?>&category=<?= urlencode($category_filter) ?>&search=<?= urlencode($search_global) ?>" class="filter-chip-da <?= $active ?>">
                    <i class="fas <?= $opt['icon'] ?>"></i> <?= $opt['label'] ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" id="filterForm">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
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
                    <label><i class="fas fa-clock"></i> Time From <?php if (!empty($time_from)): ?><span style="color:#0B5ED7;font-weight:800;">(<?= toAmPm($time_from) ?>)</span><?php endif; ?></label>
                    <input type="time" name="time_from" value="<?= htmlspecialchars($time_from) ?>">
                </div>

                <div class="filter-group-da">
                    <label><i class="fas fa-clock"></i> Time To <?php if (!empty($time_to)): ?><span style="color:#0B5ED7;font-weight:800;">(<?= toAmPm($time_to) ?>)</span><?php endif; ?></label>
                    <input type="time" name="time_to" value="<?= htmlspecialchars($time_to) ?>">
                </div>

                <div class="filter-group-da">
                    <label><i class="fas fa-user"></i> Employee</label>
                    <select name="employee_id">
                        <option value="0">All Employees</option>
                        <?php foreach ($employees_dropdown as $emp): ?>
                            <option value="<?= (int)$emp['id'] ?>" <?= $employee_filter === (int)$emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group-da">
                    <label><i class="fas fa-tag"></i> Category</label>
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $category_filter === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group-da" style="display:flex;gap:8px;align-items:flex-end;">
                    <button type="submit" class="btn-da btn-primary-da" style="flex:1;"><i class="fas fa-filter"></i> Apply</button>
                    <a href="?branch=<?= urlencode($selected_branch_id) ?>" class="btn-da btn-outline-da" style="flex:0 0 auto;"><i class="fas fa-undo"></i></a>
                </div>
            </div>

            <?php if (!empty($time_from) || !empty($time_to)): ?>
                <div style="margin-top:12px;padding:10px 16px;background:var(--page-primary-bg);border-radius:10px;font-size:0.78rem;color:var(--page-primary);font-weight:700;display:inline-flex;align-items:center;gap:8px;">
                    <i class="fas fa-clock"></i> Active time filter: <?= !empty($time_from) ? toAmPm($time_from) : 'Start' ?> → <?= !empty($time_to) ? toAmPm($time_to) : 'End' ?>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- EMPLOYEE LIST - ONLY WITH ACTIVITIES -->
    <div id="employeeList">
        <?php if (count($grouped) > 0): ?>
            <?php 
            $rank = 1;
            foreach ($grouped as $emp_id => $emp_data): 
                $emp_activities = $emp_data['activities'];
                $count = count($emp_activities);
                $initial = strtoupper(substr($emp_data['employee_name'], 0, 1));
                
                $emp_pic = $emp_data['employee_pic'] ?? '';
                $emp_pic_url = !empty($emp_pic) 
                    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $emp_pic 
                    : '';
                $has_pic = !empty($emp_pic_url) && file_exists($_SERVER['DOCUMENT_ROOT'] . $emp_pic_url);
            ?>
                <div class="employee-card-da open" 
                     data-employee-id="<?= $emp_id ?>"
                     data-employee-name="<?= htmlspecialchars(strtolower($emp_data['employee_name'])) ?>"
                     data-activity-count="<?= $count ?>">

                    <div class="employee-header-da">
                        
                        <div class="employee-info-da">
                            <!-- AVATAR -->
                            <div class="employee-avatar-da" 
                                 style="background:linear-gradient(135deg, #1E40AF, #0A2540);" 
                                 onclick="toggleEmployeeCard(this)">
                                <?php if ($has_pic): ?>
                                    <img src="<?= htmlspecialchars($emp_pic_url) ?>" 
                                         alt="<?= htmlspecialchars($emp_data['employee_name']) ?>"
                                         onerror="this.style.display='none'; var next=this.nextElementSibling; if(next) next.style.display='flex';">
                                    <span class="avatar-initials" style="display:none;"><?= $initial ?></span>
                                <?php else: ?>
                                    <span class="avatar-initials"><?= $initial ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="min-width:0;">
                                <p class="employee-name-da">
                                    <?= htmlspecialchars($emp_data['employee_name']) ?>
                                    <?php if ($rank === 1): ?>
                                        <span class="top-badge">🏆 TOP</span>
                                    <?php endif; ?>
                                </p>
                                <p class="employee-meta-da">
                                    <span><i class="fas fa-store-alt"></i> <?= htmlspecialchars($emp_data['branch_name'] ?? 'N/A') ?></span>
                                    <span><i class="fas fa-hashtag"></i> Rank #<?= $rank ?></span>
                                </p>
                            </div>
                        </div>

                        <!-- INLINE SEARCH -->
                        <div class="header-search-wrap" onclick="event.stopPropagation();">
                            <i class="fas fa-search hs-icon"></i>
                            <input type="text" 
                                   class="header-search-input"
                                   placeholder="🔍 Search <?= htmlspecialchars($emp_data['employee_name']) ?>'s activities..."
                                   data-employee-id="<?= $emp_id ?>"
                                   oninput="liveSearchHeader(this)"
                                   autocomplete="off">
                            <button type="button" class="header-clear-btn" onclick="clearHeaderSearch(this)" title="Clear">
                                <i class="fas fa-times-circle"></i>
                            </button>
                        </div>

                        <div class="employee-actions-da">
                            <div class="activity-count-badge-da" onclick="toggleEmployeeCard(this)">
                                <i class="fas fa-list-check"></i>
                                <?= $count ?> activit<?= $count !== 1 ? 'ies' : 'y' ?>
                            </div>
                            <div class="toggle-arrow-da" onclick="toggleEmployeeCard(this)">
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="employee-body-da">
                        <div style="overflow-x:auto;">
                            <table class="activities-table-da">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">#</th>
                                        <th style="width:170px;">Date & Time</th>
                                        <th>Activity</th>
                                        <th style="width:130px;">Category</th>
                                        <th style="width:110px;">Status</th>
                                        <th style="width:130px;text-align:center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="activities-tbody">
                                    <?php $i = 1; foreach ($emp_activities as $act): 
                                        $start = $act['start_time'] ? date('g:i A', strtotime($act['start_time'])) : '—';
                                        $end = $act['end_time'] ? date('g:i A', strtotime($act['end_time'])) : null;
                                        $time_range = $start;
                                        if ($end) $time_range .= ' - ' . $end;
                                        $status_meta = statusMeta($act['status'] ?? 'completed');
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
                                                    <span class="date-line"><i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($act['activity_date'])) ?></span>
                                                    <span class="time-line"><i class="fas fa-clock"></i> <?= $time_range ?></span>
                                                    <?php if (!empty($act['duration_minutes'])): ?>
                                                        <span class="duration-line"><i class="fas fa-hourglass-half"></i> <?= formatDuration($act['duration_minutes']) ?></span>
                                                    <?php endif; ?>
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
                                                    <span class="category-pill-da"><?= htmlspecialchars($act['category']) ?></span>
                                                <?php else: ?>
                                                    <span style="color:var(--page-text-secondary);font-size:0.72rem;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge-da <?= $status_meta['class'] ?>">
                                                    <i class="fas <?= $status_meta['icon'] ?>"></i> <?= $status_meta['label'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="actions-cell-da">
                                                    <button type="button" class="btn-row-action view" 
                                                            onclick='openViewModal(<?= json_encode($act, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                            title="View Activity">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn-row-action edit" 
                                                            onclick='openEditModal(<?= json_encode($act, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                            title="Edit Activity">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn-row-action delete" 
                                                            onclick='openDeleteModal(<?= json_encode($act, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                            title="Delete Activity">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="empty-state-inner-da" style="display:none;">
                            <i class="fas fa-search-minus"></i>
                            <p class="empty-title">No activities match your search</p>
                            <p class="empty-sub">Try a different keyword</p>
                        </div>
                    </div>
                </div>
            <?php 
                $rank++;
            endforeach; 
            ?>
        <?php else: ?>
            <div style="text-align:center;padding:60px 20px;background:var(--page-bg-card);border-radius:16px;border:2px solid var(--page-border);">
                <i class="fas fa-inbox" style="font-size:3.5rem;color:var(--page-border);margin-bottom:16px;display:block;"></i>
                <h3 style="font-size:1.1rem;color:var(--page-text-primary);margin:0 0 8px 0;font-weight:700;">No Activities Found</h3>
                <p style="color:var(--page-text-secondary);font-size:0.85rem;margin:0 0 20px 0;">
                    No employees have recorded activities for the selected filters.
                </p>
                <button type="button" class="btn-da btn-primary-da" onclick="openAddModal()">
                    <i class="fas fa-plus-circle"></i> Add First Activity
                </button>
            </div>
        <?php endif; ?>
    </div>

</main>

<!-- ================================================================ -->
<!-- MODAL: ADD ACTIVITY -->
<!-- ================================================================ -->
<div class="modal-overlay-da" id="addModal">
    <div class="modal-box-da">
        <div class="modal-header-da">
            <h3><i class="fas fa-plus-circle"></i> Add Daily Activity</h3>
            <button type="button" class="modal-close-da" onclick="closeAddModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="addActivityForm">
            <input type="hidden" name="action" value="add_activity">
            <div class="modal-body-da">
                <div class="form-grid-da">
                    <div class="form-group-da full">
                        <label><i class="fas fa-user"></i> Employee <span class="required">*</span></label>
                        <select name="activity_user_id" required>
                            <?php foreach ($employees_dropdown as $emp): ?>
                                <option value="<?= (int)$emp['id'] ?>" <?= (int)$emp['id'] === (int)$user_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['full_name']) ?><?php if ((int)$emp['id'] === (int)$user_id): ?> — 👤 YOU<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-da">
                        <label><i class="fas fa-calendar"></i> Date <span class="required">*</span></label>
                        <input type="date" name="activity_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group-da">
                        <label><i class="fas fa-tag"></i> Category</label>
                        <input type="text" name="category" list="catList" placeholder="e.g. Consultation" autocomplete="off">
                        <datalist id="catList">
                            <?php foreach ($categories as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group-da full">
                        <label><i class="fas fa-heading"></i> Title <span class="required">*</span></label>
                        <input type="text" name="title" placeholder="e.g. Consulted 5 patients with malaria" required maxlength="255">
                    </div>
                    <div class="form-group-da full">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" placeholder="Detailed description..." maxlength="2000"></textarea>
                    </div>
                    <div class="form-group-da">
                        <label><i class="fas fa-clock"></i> Start Time</label>
                        <input type="time" name="start_time" id="startTime" onchange="updateDuration()">
                    </div>
                    <div class="form-group-da">
                        <label><i class="fas fa-clock"></i> End Time</label>
                        <input type="time" name="end_time" id="endTime" onchange="updateDuration()">
                    </div>
                    <div class="form-group-da full" id="durationBox" style="display:none;">
                        <div style="padding:10px 14px;background:var(--page-primary-bg);border-radius:10px;font-size:0.82rem;color:var(--page-primary);font-weight:700;">
                            <i class="fas fa-hourglass-half"></i> Duration: <span id="durationText">—</span>
                        </div>
                    </div>
                    <div class="form-group-da">
                        <label><i class="fas fa-info-circle"></i> Status</label>
                        <select name="status">
                            <option value="completed">✅ Completed</option>
                            <option value="in_progress">🔄 In Progress</option>
                            <option value="pending">⏳ Pending</option>
                            <option value="cancelled">❌ Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group-da">
                        <label><i class="fas fa-flag"></i> Priority</label>
                        <select name="priority">
                            <option value="low">🟢 Low</option>
                            <option value="medium" selected>🟡 Medium</option>
                            <option value="high">🟠 High</option>
                            <option value="urgent">🔴 Urgent</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer-da">
                <button type="button" class="btn-modal-da cancel" onclick="closeAddModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-modal-da submit"><i class="fas fa-save"></i> Save Activity</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- MODAL: VIEW ACTIVITY -->
<!-- ================================================================ -->
<div class="modal-overlay-da" id="viewModal">
    <div class="modal-box-da view-modal">
        <div class="modal-header-da">
            <h3><i class="fas fa-eye"></i> View Activity</h3>
            <button type="button" class="modal-close-da" onclick="closeViewModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body-da">
            <div class="view-detail-row">
                <div class="view-detail-label"><i class="fas fa-heading"></i> Title</div>
                <div class="view-detail-value" id="view_title">—</div>
            </div>
            <div class="view-detail-row">
                <div class="view-detail-label"><i class="fas fa-align-left"></i> Description</div>
                <div class="view-detail-value" id="view_description">—</div>
            </div>
            <div class="view-detail-row">
                <div class="view-detail-label"><i class="fas fa-tag"></i> Category</div>
                <div class="view-detail-value" id="view_category">—</div>
            </div>
            <div class="view-detail-row">
                <div class="view-detail-label"><i class="fas fa-calendar"></i> Date</div>
                <div class="view-detail-value highlight-box" id="view_date">—</div>
            </div>
            <div class="view-detail-row">
                <div class="view-detail-label"><i class="fas fa-clock"></i> Time</div>
                <div class="view-detail-value" id="view_time">—</div>
            </div>
            <div class="view-detail-row">
                <div class="view-detail-label"><i class="fas fa-hourglass-half"></i> Duration</div>
                <div class="view-detail-value" id="view_duration">—</div>
            </div>
            <div class="view-detail-row">
                <div class="view-detail-label"><i class="fas fa-info-circle"></i> Status</div>
                <div class="view-detail-value" id="view_status">—</div>
            </div>
            <div class="view-detail-row">
                <div class="view-detail-label"><i class="fas fa-flag"></i> Priority</div>
                <div class="view-detail-value" id="view_priority">—</div>
            </div>
            <div class="view-detail-row">
                <div class="view-detail-label"><i class="fas fa-calendar-plus"></i> Created</div>
                <div class="view-detail-value" id="view_created_at">—</div>
            </div>
        </div>
        <div class="modal-footer-da">
            <button type="button" class="btn-modal-da cancel" onclick="closeViewModal()"><i class="fas fa-times"></i> Close</button>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- MODAL: EDIT ACTIVITY -->
<!-- ================================================================ -->
<div class="modal-overlay-da" id="editModal">
    <div class="modal-box-da edit-modal">
        <div class="modal-header-da">
            <h3><i class="fas fa-edit"></i> Edit Activity</h3>
            <button type="button" class="modal-close-da" onclick="closeEditModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="editActivityForm">
            <input type="hidden" name="action" value="edit_activity">
            <input type="hidden" name="activity_id" id="edit_id">
            <div class="modal-body-da">
                <div class="form-grid-da">
                    <div class="form-group-da full">
                        <label><i class="fas fa-heading"></i> Title <span class="required">*</span></label>
                        <input type="text" name="title" required maxlength="255" id="edit_title">
                    </div>
                    <div class="form-group-da full">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" maxlength="2000" id="edit_description"></textarea>
                    </div>
                    <div class="form-group-da">
                        <label><i class="fas fa-tag"></i> Category</label>
                        <input type="text" name="category" list="catListEdit" id="edit_category" autocomplete="off">
                        <datalist id="catListEdit">
                            <?php foreach ($categories as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?php endforeach; ?>
                        </datalist>
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
                </div>
            </div>
            <div class="modal-footer-da">
                <button type="button" class="btn-modal-da cancel" onclick="closeEditModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-modal-da update"><i class="fas fa-save"></i> Update Activity</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- MODAL: DELETE ACTIVITY -->
<!-- ================================================================ -->
<div class="modal-overlay-da" id="deleteModal">
    <div class="modal-box-da delete-modal">
        <div class="modal-header-da">
            <h3><i class="fas fa-trash"></i> Delete Activity</h3>
            <button type="button" class="modal-close-da" onclick="closeDeleteModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="deleteActivityForm">
            <input type="hidden" name="action" value="delete_activity">
            <input type="hidden" name="activity_id" id="delete_id">
            <div class="modal-body-da">
                <div class="delete-content">
                    <div class="delete-icon-wrap">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4 class="delete-title">Delete this activity?</h4>
                    <p class="delete-subtitle">
                        This action <strong>cannot be undone</strong>.<br>
                        The activity will be permanently removed.
                    </p>
                    <div class="delete-preview">
                        <p class="preview-label">Title</p>
                        <p class="preview-value" id="delete_preview_title">—</p>
                        <p class="preview-label">Date</p>
                        <p class="preview-value" id="delete_preview_date">—</p>
                        <p class="preview-label">Category</p>
                        <p class="preview-value" id="delete_preview_category">—</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer-da">
                <button type="button" class="btn-modal-da cancel" onclick="closeDeleteModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-modal-da danger"><i class="fas fa-trash"></i> Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
// ================================================================
// DARK MODE BG
// ================================================================
(function() {
    function enforceDarkModeBackground() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var body = document.body;
        var mainContent = document.querySelector('.main-content');
        if (isDark) {
            if (body) body.style.background = '#0F172A';
            if (mainContent) mainContent.style.background = '#0F172A';
        } else {
            if (body) body.style.background = '#F1F5F9';
            if (mainContent) mainContent.style.background = '#F1F5F9';
        }
    }
    enforceDarkModeBackground();
    document.addEventListener('darkModeChanged', function() { setTimeout(enforceDarkModeBackground, 50); });
    new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            if (m.attributeName === 'data-theme') enforceDarkModeBackground();
        });
    }).observe(document.documentElement, { attributes: true });
})();

// ================================================================
// MODAL CONTROLS
// ================================================================
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
    document.body.style.overflow = '';
}

function openViewModal(activity) {
    document.getElementById('view_title').textContent = activity.title || '—';
    document.getElementById('view_description').textContent = activity.description || '—';
    document.getElementById('view_description').className = activity.description ? 'view-detail-value' : 'view-detail-value empty';
    document.getElementById('view_category').textContent = activity.category || '—';
    document.getElementById('view_category').className = activity.category ? 'view-detail-value' : 'view-detail-value empty';
    document.getElementById('view_date').textContent = activity.activity_date ? formatDate(activity.activity_date) : '—';
    
    var timeStr = '—';
    if (activity.start_time) {
        timeStr = formatTime(activity.start_time);
        if (activity.end_time) timeStr += ' - ' + formatTime(activity.end_time);
    }
    document.getElementById('view_time').textContent = timeStr;
    
    document.getElementById('view_duration').textContent = formatDurationJS(activity.duration_minutes);
    document.getElementById('view_status').textContent = (activity.status || 'completed').toUpperCase();
    document.getElementById('view_priority').textContent = (activity.priority || 'medium').toUpperCase();
    document.getElementById('view_created_at').textContent = activity.created_at ? formatDateTime(activity.created_at) : '—';
    
    document.getElementById('viewModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
    document.body.style.overflow = '';
}

function openEditModal(activity) {
    document.getElementById('edit_id').value = activity.id;
    document.getElementById('edit_title').value = activity.title || '';
    document.getElementById('edit_description').value = activity.description || '';
    document.getElementById('edit_category').value = activity.category || '';
    document.getElementById('edit_status').value = activity.status || 'completed';
    document.getElementById('edit_priority').value = activity.priority || 'medium';
    
    document.getElementById('editModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
    document.body.style.overflow = '';
}

function openDeleteModal(activity) {
    document.getElementById('delete_id').value = activity.id;
    document.getElementById('delete_preview_title').textContent = activity.title || '—';
    document.getElementById('delete_preview_date').textContent = activity.activity_date ? formatDate(activity.activity_date) : '—';
    document.getElementById('delete_preview_category').textContent = activity.category || 'No category';
    
    document.getElementById('deleteModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close on overlay click
['addModal', 'viewModal', 'editModal', 'deleteModal'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

// ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddModal();
        closeViewModal();
        closeEditModal();
        closeDeleteModal();
    }
});

// ================================================================
// HELPERS
// ================================================================
function formatDate(dateStr) {
    if (!dateStr) return '—';
    var d = new Date(dateStr + 'T00:00:00');
    if (isNaN(d.getTime())) return dateStr;
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var day = String(d.getDate()).padStart(2, '0');
    return day + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
}

function formatTime(timeStr) {
    if (!timeStr) return '—';
    var parts = timeStr.split(':');
    if (parts.length < 2) return timeStr;
    var h = parseInt(parts[0]);
    var m = parts[1];
    var ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return h + ':' + m + ' ' + ampm;
}

function formatDateTime(dtStr) {
    if (!dtStr) return '—';
    var d = new Date(dtStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return dtStr;
    return formatDate(dtStr.split(' ')[0]) + ' ' + formatTime(dtStr.split(' ')[1]);
}

function formatDurationJS(minutes) {
    if (!minutes || minutes <= 0) return '—';
    var h = Math.floor(minutes / 60);
    var m = minutes % 60;
    var parts = [];
    if (h > 0) parts.push(h + 'h');
    if (m > 0) parts.push(m + 'm');
    return parts.join(' ') || '—';
}

function updateDuration() {
    var start = document.getElementById('startTime').value;
    var end = document.getElementById('endTime').value;
    var box = document.getElementById('durationBox');
    var text = document.getElementById('durationText');
    if (start && end) {
        var t1 = new Date('2000-01-01T' + start);
        var t2 = new Date('2000-01-01T' + end);
        var diff = (t2 - t1) / 60000;
        if (diff > 0) {
            text.textContent = formatDurationJS(Math.round(diff));
            box.style.display = 'block';
        } else { box.style.display = 'none'; }
    } else { box.style.display = 'none'; }
}

// Toggle employee card
function toggleEmployeeCard(el) {
    var card = el.closest('.employee-card-da');
    if (card) card.classList.toggle('open');
}

// ================================================================
// LIVE SEARCH - Per Employee
// ================================================================
function escapeRegex(str) { return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
function escapeHtml(str) { var div = document.createElement('div'); div.textContent = str; return div.innerHTML; }

function liveSearchHeader(inputEl) {
    var card = inputEl.closest('.employee-card-da');
    if (!card) return;
    
    var query = inputEl.value.trim().toLowerCase();
    var tbody = card.querySelector('.activities-tbody');
    var emptyMsg = card.querySelector('.empty-state-inner-da');
    var clearBtn = inputEl.parentElement.querySelector('.header-clear-btn');
    
    if (!tbody) return;
    
    if (query.length > 0) {
        clearBtn.classList.add('visible');
        card.classList.add('open');
    } else {
        clearBtn.classList.remove('visible');
    }
    
    var rows = tbody.querySelectorAll('tr');
    var matchCount = 0;
    
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
    
    if (emptyMsg) {
        emptyMsg.style.display = (matchCount === 0 && query !== '') ? 'block' : 'none';
    }
}

function clearHeaderSearch(btn) {
    var input = btn.parentElement.querySelector('.header-search-input');
    input.value = '';
    liveSearchHeader(input);
    input.focus();
}

// ================================================================
// GLOBAL SEARCH
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    var globalInput = document.getElementById('globalSearchInput');
    var clearBtn = document.getElementById('globalClearBtn');
    var resultCount = document.getElementById('globalResultCount');
    if (!globalInput) return;
    
    function applyGlobalSearch() {
        var query = globalInput.value.trim().toLowerCase();
        var cards = document.querySelectorAll('.employee-card-da');
        var visibleCount = 0;
        
        if (query.length > 0) clearBtn.classList.add('visible');
        else clearBtn.classList.remove('visible');
        
        cards.forEach(function(card) {
            var empName = card.getAttribute('data-employee-name') || '';
            var nameMatches = empName.indexOf(query) !== -1;
            var activityMatch = false;
            
            if (query.length > 0) {
                var rows = card.querySelectorAll('tbody tr');
                rows.forEach(function(row) {
                    var searchable = row.getAttribute('data-searchable') || '';
                    if (searchable.indexOf(query) !== -1) activityMatch = true;
                });
            }
            
            if (query === '' || nameMatches || activityMatch) {
                card.style.display = '';
                visibleCount++;
                if (query.length > 0 && activityMatch && !nameMatches) card.classList.add('open');
            } else {
                card.style.display = 'none';
            }
        });
        
        if (query === '') {
            resultCount.innerHTML = 'Showing <strong>' + visibleCount + '</strong> employees';
        } else {
            resultCount.innerHTML = 'Found <strong>' + visibleCount + '</strong> employee' + (visibleCount !== 1 ? 's' : '') + ' matching "<em>' + escapeHtml(query) + '</em>"';
        }
    }
    
    var debounce;
    globalInput.addEventListener('input', function() {
        clearTimeout(debounce);
        debounce = setTimeout(applyGlobalSearch, 150);
    });
    
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            globalInput.value = '';
            applyGlobalSearch();
            globalInput.focus();
        });
    }
    
    if (globalInput.value.trim() !== '') applyGlobalSearch();
    
    globalInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { globalInput.value = ''; applyGlobalSearch(); }
    });
});

console.log('%c📋 Braick - Daily Activities (ONLY Employees WITH Activities)', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ ONLY employees WITH activities shown', 'font-size:13px;color:#059669;font-weight:bold;');
console.log('%c✅ No role display (just full name)', 'font-size:13px;color:#059669;');
console.log('%c✅ Search bar per employee', 'font-size:13px;color:#059669;');
console.log('%c✅ Actions: View, Edit, Delete per row', 'font-size:13px;color:#059669;');
console.log('%c✅ View modal shows FULL activity details', 'font-size:13px;color:#059669;');
</script>

</body>
</html>