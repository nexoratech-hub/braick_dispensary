<?php
// ================================================================
// FILE: frontend/pages/admin/employee_activities.php
// SUPER ADMIN - EMPLOYEE ACTIVITIES
// VIEW ALL ACTIVITIES FOR A SPECIFIC EMPLOYEE
// WITH RECENT ACTIVITIES SECTION
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET EMPLOYEE ID
// ================================================================
$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($employee_id <= 0) {
    header('Location: employees.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// FETCH EMPLOYEE DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.full_name,
        u.username,
        u.email,
        u.role,
        u.branch_id,
        u.status,
        u.profile_pic,
        b.name as branch_name
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.id = ? AND u.role != 'admin'
");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employees.php?branch=' . $selected_branch_id . '&error=notfound');
    exit;
}

// ================================================================
// GET FILTERS
// ================================================================
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search_filter = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// BUILD ACTIVITIES QUERY
// ================================================================
$conditions = ["user_id = ?"];
$params = [$employee_id];

if (!empty($action_filter)) {
    $conditions[] = "action LIKE ?";
    $params[] = "%$action_filter%";
}

if (!empty($date_filter)) {
    $conditions[] = "DATE(created_at) = ?";
    $params[] = $date_filter;
}

if (!empty($search_filter)) {
    $conditions[] = "(action LIKE ? OR details LIKE ?)";
    $params[] = "%$search_filter%";
    $params[] = "%$search_filter%";
}

$where_clause = implode(" AND ", $conditions);

// ================================================================
// GET TOTAL ACTIVITIES COUNT
// ================================================================
$count_stmt = $db->prepare("SELECT COUNT(*) as total FROM activity_logs WHERE $where_clause");
$count_stmt->execute($params);
$total_activities = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// GET ACTIVITIES WITH PAGINATION
// ================================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$total_pages = $total_activities > 0 ? ceil($total_activities / $per_page) : 1;

$stmt = $db->prepare("
    SELECT 
        id,
        action,
        details,
        ip_address,
        user_agent,
        created_at
    FROM activity_logs
    WHERE $where_clause
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT ACTIVITIES (Last 5 - for summary)
// ================================================================
$recent_stmt = $db->prepare("
    SELECT 
        action,
        details,
        created_at
    FROM activity_logs
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$recent_stmt->execute([$employee_id]);
$recent_activities = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ACTIVITY STATISTICS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        action,
        COUNT(*) as count
    FROM activity_logs
    WHERE user_id = ?
    GROUP BY action
    ORDER BY count DESC
    LIMIT 10
");
$stmt->execute([$employee_id]);
$action_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET DAILY ACTIVITY CHART DATA
// ================================================================
$stmt = $db->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM activity_logs
    WHERE user_id = ?
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([$employee_id]);
$daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$chart_labels = [];
$chart_values = [];
foreach ($daily_data as $data) {
    $chart_labels[] = date('M d', strtotime($data['date']));
    $chart_values[] = (int)$data['count'];
}

// ================================================================
// GET UNIQUE ACTIONS FOR FILTER
// ================================================================
$stmt = $db->prepare("
    SELECT DISTINCT action 
    FROM activity_logs 
    WHERE user_id = ?
    ORDER BY action ASC
");
$stmt->execute([$employee_id]);
$unique_actions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active'");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// ROLE LABEL
// ================================================================
$role_labels = [
    'doctor' => 'Doctor',
    'reception' => 'Receptionist',
    'pharmacy' => 'Pharmacist',
    'laboratory' => 'Lab Technician',
    'cashier' => 'Cashier'
];
$role_display = $role_labels[$employee['role']] ?? ucfirst($employee['role']);

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once '../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - SHARED HEADER -->
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
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clock mr-2"></i> Employee Activities
            </h1>
            <p class="page-subtitle">
                View all activities for 
                <strong><?= htmlspecialchars($employee['full_name']) ?></strong>
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($employee['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="ml-2 date-badge">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="view_employee.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-view btn-sm">
                <i class="fas fa-user"></i> View Employee
            </a>
            <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- EMPLOYEE SUMMARY -->
    <!-- ================================================================ -->
    <div class="employee-summary mb-5">
        <div class="summary-card">
            <div class="summary-icon blue">
                <i class="fas fa-user"></i>
            </div>
            <div>
                <p class="summary-label">Employee</p>
                <p class="summary-value"><?= htmlspecialchars($employee['full_name']) ?></p>
                <p class="summary-sub">@<?= htmlspecialchars($employee['username']) ?></p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon green">
                <i class="fas fa-briefcase"></i>
            </div>
            <div>
                <p class="summary-label">Role</p>
                <p class="summary-value"><?= $role_display ?></p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon purple">
                <i class="fas fa-envelope"></i>
            </div>
            <div>
                <p class="summary-label">Email</p>
                <p class="summary-value"><?= htmlspecialchars($employee['email']) ?></p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon orange">
                <i class="fas fa-list"></i>
            </div>
            <div>
                <p class="summary-label">Total Activities</p>
                <p class="summary-value"><?= number_format($total_activities) ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT ACTIVITIES (Quick View) -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-clock title-blue mr-2"></i> Recent Activities
                <span class="text-xs text-gray-400 font-normal">(Last 5 activities)</span>
            </h3>
        </div>
        <?php if (count($recent_activities) > 0): ?>
            <div class="space-y-2">
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="flex items-start gap-3 p-3 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition border border-transparent hover:border-blue-200 dark:hover:border-blue-800">
                        <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5 text-white">
                            <i class="fas fa-circle text-[6px]"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-sm text-gray-800 dark:text-gray-200">
                                <?= htmlspecialchars($activity['action'] ?? 'Action') ?>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <?= htmlspecialchars($activity['details'] ?? '') ?>
                            </p>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">
                                <i class="fas fa-clock mr-1"></i>
                                <?= isset($activity['created_at']) ? date('M d, Y h:i:s A', strtotime($activity['created_at'])) : 'Just now' ?>
                            </p>
                        </div>
                        <span class="text-xs text-gray-400">
                            <?= isset($activity['created_at']) ? time_ago($activity['created_at']) : 'Just now' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center text-gray-400 text-sm py-5">
                <i class="fas fa-inbox text-3xl block mb-3"></i>
                <p class="text-base font-medium">No recent activities found</p>
                <p class="text-xs mt-1">This employee has not performed any activities yet</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- ACTIVITY STATS & CHART -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <!-- Action Statistics -->
        <div class="card lg:col-span-1">
            <h3 class="card-title mb-3">
                <i class="fas fa-chart-pie title-blue mr-2"></i> Top Actions
            </h3>
            <?php if (count($action_stats) > 0): ?>
                <div class="space-y-2">
                    <?php foreach ($action_stats as $stat): ?>
                        <div class="stat-bar">
                            <div class="stat-bar-label"><?= htmlspecialchars($stat['action']) ?></div>
                            <div class="stat-bar-track">
                                <div class="stat-bar-fill" style="width: <?= ($stat['count'] / max(array_column($action_stats, 'count'))) * 100 ?>%;">
                                    <?= $stat['count'] ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center text-gray-400 text-sm py-5">
                    <i class="fas fa-inbox text-2xl block mb-2"></i>
                    No activities found
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Daily Activity Chart -->
        <div class="card lg:col-span-2">
            <h3 class="card-title mb-3">
                <i class="fas fa-chart-line title-green mr-2"></i> Daily Activity (Last 30 Days)
            </h3>
            <?php if (count($chart_labels) > 0): ?>
                <canvas id="activityChart" height="150"></canvas>
            <?php else: ?>
                <div class="text-center text-gray-400 text-sm py-5">
                    <i class="fas fa-chart-line text-2xl block mb-2"></i>
                    No activity data available for chart
                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section mb-5">
        <form method="GET" action="" class="filter-form">
            <input type="hidden" name="id" value="<?= $employee['id'] ?>">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            
            <div class="filter-row">
                <div class="filter-group">
                    <label>Action Type</label>
                    <select name="action" class="filter-select">
                        <option value="">All Actions</option>
                        <?php foreach ($unique_actions as $action): ?>
                            <option value="<?= htmlspecialchars($action) ?>" <?= $action_filter === $action ? 'selected' : '' ?>>
                                <?= htmlspecialchars($action) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Date</label>
                    <input type="date" name="date" class="filter-input" value="<?= $date_filter ?>">
                </div>
                
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" class="filter-input" placeholder="Search activities..." value="<?= htmlspecialchars($search_filter) ?>">
                </div>
                
                <div class="filter-group filter-actions">
                    <button type="submit" class="btn btn-blue btn-sm">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <a href="employee_activities.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- ACTIVITIES TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Activity Log
                <span class="text-xs text-gray-400 font-normal">(<?= number_format($total_activities) ?> records)</span>
            </h3>
            <span class="text-xs text-gray-400">
                Page <?= $page ?> of <?= $total_pages ?>
            </span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th style="width: 120px;">IP Address</th>
                        <th style="width: 160px;">Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($activities) > 0): ?>
                        <?php $i = $offset + 1; foreach ($activities as $activity): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="action-badge">
                                        <?= htmlspecialchars($activity['action']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($activity['details'] ?? '') ?></td>
                                <td class="text-xs font-mono"><?= htmlspecialchars($activity['ip_address'] ?? 'N/A') ?></td>
                                <td class="text-xs">
                                    <?= date('M d, Y', strtotime($activity['created_at'])) ?>
                                    <br>
                                    <span class="text-gray-400"><?= date('h:i:s A', strtotime($activity['created_at'])) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-gray-400 text-sm py-5">
                                <i class="fas fa-inbox text-2xl block mb-2"></i>
                                No activities found for this employee
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <?php if ($page > 1): ?>
                    <a href="?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>&page=<?= $page - 1 ?>&action=<?= urlencode($action_filter) ?>&date=<?= urlencode($date_filter) ?>&search=<?= urlencode($search_filter) ?>" 
                       class="pagination-btn">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <div class="pagination-pages">
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>&page=<?= $p ?>&action=<?= urlencode($action_filter) ?>&date=<?= urlencode($date_filter) ?>&search=<?= urlencode($search_filter) ?>" 
                           class="pagination-page <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>&page=<?= $page + 1 ?>&action=<?= urlencode($action_filter) ?>&date=<?= urlencode($date_filter) ?>&search=<?= urlencode($search_filter) ?>" 
                       class="pagination-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Employee Activities
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<style>
    /* ================================================================
       CUSTOM STYLES
       ================================================================ */
    
    /* Employee Summary */
    .employee-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }
    
    .summary-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.3s ease;
    }
    
    .summary-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    }
    
    .summary-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    
    .summary-icon.blue { background: #EFF6FF; color: #0B5ED7; }
    .summary-icon.green { background: #ECFDF5; color: #059669; }
    .summary-icon.purple { background: #F5F3FF; color: #7B2FBE; }
    .summary-icon.orange { background: #FFFBEB; color: #F59E0B; }
    
    [data-theme="dark"] .summary-icon.blue { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .summary-icon.green { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .summary-icon.purple { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .summary-icon.orange { background: #3D2E0A; color: #FBBF24; }
    
    .summary-label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin: 0;
    }
    
    .summary-value {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 2px 0;
    }
    
    .summary-sub {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }
    
    /* Filter Section */
    .filter-section {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid var(--border-color);
    }
    
    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-end;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
        min-width: 150px;
    }
    
    .filter-group label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .filter-select,
    .filter-input {
        padding: 6px 10px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        background: var(--bg-body);
        color: var(--text-primary);
        font-size: 0.8rem;
        outline: none;
        transition: all 0.3s;
        width: 100%;
    }
    
    .filter-select:focus,
    .filter-input:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .filter-actions {
        flex: 0 0 auto;
        flex-direction: row;
        align-items: center;
        gap: 6px;
        min-width: auto;
    }
    
    .filter-actions .btn {
        white-space: nowrap;
    }
    
    /* Action Badge */
    .action-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 500;
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .action-badge {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    /* Stat Bars */
    .stat-bar {
        margin-bottom: 8px;
    }
    
    .stat-bar:last-child {
        margin-bottom: 0;
    }
    
    .stat-bar-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        margin-bottom: 2px;
        font-weight: 500;
    }
    
    .stat-bar-track {
        background: var(--bg-body);
        border-radius: 6px;
        overflow: hidden;
        height: 22px;
        position: relative;
    }
    
    .stat-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #0B5ED7, #1A73E8);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 8px;
        font-size: 0.6rem;
        font-weight: 600;
        color: white;
        transition: width 0.5s ease;
        min-width: 24px;
    }
    
    [data-theme="dark"] .stat-bar-fill {
        background: linear-gradient(90deg, #0A4CA8, #0B5ED7);
    }
    
    /* Pagination */
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0 4px 0;
        border-top: 1px solid var(--border-color);
        margin-top: 12px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .pagination-btn {
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 500;
        background: var(--bg-body);
        color: var(--text-secondary);
        text-decoration: none;
        border: 1px solid var(--border-color);
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .pagination-btn:hover {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    .pagination-pages {
        display: flex;
        gap: 4px;
    }
    
    .pagination-page {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--text-secondary);
        text-decoration: none;
        border: 1px solid transparent;
        transition: all 0.3s;
    }
    
    .pagination-page:hover {
        background: var(--bg-body);
        border-color: var(--border-color);
    }
    
    .pagination-page.active {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    /* Table */
    .data-table thead th {
        background: #0B5ED7 !important;
        color: white !important;
        font-weight: 600;
        padding: 10px 12px;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        border-bottom: none !important;
    }
    
    .data-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .data-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .data-table td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        font-size: 0.8rem;
        vertical-align: middle;
    }
    
    .data-table tbody tr:hover td {
        background: var(--table-hover);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .employee-summary {
            grid-template-columns: repeat(2, 1fr);
        }
        .filter-row {
            flex-direction: column;
            gap: 8px;
        }
        .filter-group {
            min-width: 100%;
        }
        .filter-actions {
            flex-direction: row;
            width: 100%;
        }
        .filter-actions .btn {
            flex: 1;
        }
        .pagination-container {
            flex-direction: column;
            gap: 8px;
        }
        .data-table {
            font-size: 0.7rem;
        }
        .data-table td,
        .data-table th {
            padding: 6px 8px;
        }
    }
    
    @media (max-width: 480px) {
        .employee-summary {
            grid-template-columns: 1fr;
        }
    }
</style>

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
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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

    // ================================================================
    // ACTIVITY CHART
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('activityChart')?.getContext('2d');
        if (ctx && typeof Chart !== 'undefined') {
            var labels = <?= json_encode($chart_labels) ?>;
            var values = <?= json_encode($chart_values) ?>;
            
            if (labels.length > 0) {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Activities',
                            data: values,
                            backgroundColor: 'rgba(11, 94, 215, 0.7)',
                            borderColor: '#0B5ED7',
                            borderWidth: 2,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.raw + ' activities';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    font: { size: 10 }
                                },
                                grid: { color: 'rgba(0,0,0,0.05)' }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 10 } }
                            }
                        }
                    }
                });
            }
        }
    });

    console.log('%c🕐 Braick Dispensary - Employee Activities', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Employee: <?= htmlspecialchars($employee['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Activities: <?= number_format($total_activities) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📄 Page: <?= $page ?> of <?= $total_pages ?>', 'font-size:13px; color:#7B2FBE;');
</script>

</body>
</html>