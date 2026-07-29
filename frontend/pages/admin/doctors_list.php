<?php
// ================================================================
// FILE: frontend/pages/admin/doctors_list.php
// DOCTORS LIST - VIEW ALL DOCTORS
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Admin Only
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database and helpers
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// VARIABLES
// ================================================================
$message = '';
$message_type = '';
$per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$selected_branch_id = $_GET['branch'] ?? 'all';

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// HANDLE TOGGLE DOCTOR STATUS (Online/Offline)
// ================================================================
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $doctor_id = (int)$_GET['toggle'];
    
    // Get current status
    $stmt = $db->prepare("SELECT full_name, is_online FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doctor) {
        $new_status = $doctor['is_online'] == 1 ? 0 : 1;
        $action_text = $new_status == 1 ? 'online' : 'offline';
        
        // Update doctor status
        $stmt = $db->prepare("UPDATE users SET is_online = ?, last_online = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $doctor_id]);
        
        // Also update doctor_status table if exists
        try {
            $stmt = $db->prepare("
                INSERT INTO doctor_status (doctor_id, is_online, updated_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE is_online = ?, updated_at = NOW()
            ");
            $stmt->execute([$doctor_id, $new_status, $new_status]);
        } catch (Exception $e) {
            // Table might not exist, ignore
        }
        
        // Log activity
        logActivity($db, $_SESSION['user_id'], 'doctor_status_changed', "Dr. {$doctor['full_name']} changed status to: $action_text");
        
        $message = "Dr. {$doctor['full_name']} is now " . ($new_status == 1 ? '🟢 ONLINE' : '🔴 OFFLINE');
        $message_type = 'success';
        
        // Redirect to remove toggle parameter
        header("Location: doctors_list.php?page=$page" . ($search ? "&search=" . urlencode($search) : "") . ($status_filter ? "&status=" . urlencode($status_filter) : "") . "&branch=" . $selected_branch_id);
        exit();
    }
}

// ================================================================
// BUILD QUERY WITH FILTERS
// ================================================================
$where_clause = " WHERE role = 'doctor'";
$params = [];

// Search filter
if (!empty($search)) {
    $where_clause .= " AND (full_name LIKE ? OR specialty LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Status filter (online/offline)
if (!empty($status_filter)) {
    if ($status_filter === 'online') {
        $where_clause .= " AND is_online = 1";
    } elseif ($status_filter === 'offline') {
        $where_clause .= " AND (is_online = 0 OR is_online IS NULL)";
    }
}

// Branch filter
if ($selected_branch_id !== 'all') {
    $where_clause .= " AND branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// ================================================================
// GET DOCTORS WITH PAGINATION
// ================================================================

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_doctors / $per_page);

// Get doctors for current page
$sql = "
    SELECT u.*, b.name as branch_name,
           (SELECT COUNT(*) FROM visits WHERE doctor_id = u.id) as total_visits,
           (SELECT COUNT(*) FROM prescriptions WHERE doctor_id = u.id) as total_prescriptions,
           (SELECT COUNT(*) FROM patients WHERE assigned_doctor_id = u.id) as total_patients
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    $where_clause
    ORDER BY u.full_name ASC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================

// Total doctors
$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor'");
$total_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Online doctors
$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND is_online = 1");
$online_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Offline doctors
$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND (is_online = 0 OR is_online IS NULL)");
$offline_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Doctors with patients assigned
$stmt = $db->query("
    SELECT COUNT(DISTINCT assigned_doctor_id) as total 
    FROM patients 
    WHERE assigned_doctor_id IS NOT NULL
");
$with_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       ADDITIONAL STYLES
       ================================================================ */
    
    /* Stat Cards */
    .stat-card-mini {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .stat-card-mini:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        border-color: #0B5ED7;
    }
    
    .stat-card-mini .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0B5ED7;
    }
    
    .stat-card-mini .stat-number.green { color: #059669; }
    .stat-card-mini .stat-number.orange { color: #F59E0B; }
    .stat-card-mini .stat-number.red { color: #EF4444; }
    .stat-card-mini .stat-number.purple { color: #7B2FBE; }
    
    .stat-card-mini .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .stat-card-mini .stat-icon {
        font-size: 1.5rem;
        margin-bottom: 4px;
    }
    
    [data-theme="dark"] .stat-card-mini {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .stat-card-mini:hover {
        border-color: #0B5ED7;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number {
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number.green {
        color: #34D399;
    }
    
    /* Table Header - Blue Theme */
    .table-blue thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        font-size: 0.65rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        padding: 10px 14px !important;
        border-bottom: 3px solid #0A4CA8 !important;
        white-space: nowrap !important;
        position: sticky;
        top: 0;
        z-index: 5;
    }
    
    .table-blue thead th:first-child {
        border-radius: 8px 0 0 0 !important;
    }
    
    .table-blue thead th:last-child {
        border-radius: 0 8px 0 0 !important;
    }
    
    .table-blue tbody td {
        padding: 8px 14px !important;
        border-bottom: 1px solid #E2E8F0 !important;
        color: #1E293B !important;
        vertical-align: middle !important;
        font-size: 0.82rem;
    }
    
    .table-blue tbody tr:hover td {
        background: #E8F0FE !important;
    }
    
    [data-theme="dark"] .table-blue tbody td {
        color: #F1F5F9 !important;
        border-bottom-color: #334155 !important;
    }
    
    [data-theme="dark"] .table-blue tbody tr:hover td {
        background: #1A3A5F !important;
    }
    
    /* Status Badges */
    .status-badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .status-badge.online {
        background: #D1FAE5;
        color: #059669;
    }
    
    .status-badge.offline {
        background: #FEE2E2;
        color: #EF4444;
    }
    
    [data-theme="dark"] .status-badge.online {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .status-badge.offline {
        background: #3A1A1A;
        color: #F87171;
    }
    
    /* Filter Buttons */
    .filter-btn {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        border: 2px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }
    
    .filter-btn:hover {
        border-color: #0B5ED7;
        color: #0B5ED7;
        background: #E8F0FE;
    }
    
    .filter-btn.active {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    .filter-btn.active:hover {
        background: #0A4CA8;
        border-color: #0A4CA8;
    }
    
    [data-theme="dark"] .filter-btn:hover {
        background: #1E3A5F;
        border-color: #0B5ED7;
        color: #6EA8FE;
    }
    
    .filter-btn i { margin-right: 4px; }
    
    /* Filter Section */
    .filter-section {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 14px 18px;
        border: 1px solid var(--border-color);
        margin-bottom: 18px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    
    .filter-section .filter-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-secondary);
        margin-right: 4px;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    
    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.6rem;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        border: none;
        cursor: pointer;
    }
    
    .btn-action i { font-size: 0.65rem; }
    
    .btn-view {
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    .btn-view:hover {
        background: #0B5ED7;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.3);
    }
    
    .btn-edit {
        background: #FEF3C7;
        color: #D97706;
    }
    
    .btn-edit:hover {
        background: #D97706;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(217, 119, 6, 0.3);
    }
    
    .btn-toggle-online {
        background: #D1FAE5;
        color: #059669;
    }
    
    .btn-toggle-online:hover {
        background: #059669;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
    }
    
    .btn-toggle-offline {
        background: #FEE2E2;
        color: #EF4444;
    }
    
    .btn-toggle-offline:hover {
        background: #EF4444;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }
    
    [data-theme="dark"] .btn-view {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    [data-theme="dark"] .btn-view:hover {
        background: #0B5ED7;
        color: white;
    }
    
    [data-theme="dark"] .btn-edit {
        background: #3A2A1A;
        color: #FBBF24;
    }
    [data-theme="dark"] .btn-edit:hover {
        background: #D97706;
        color: white;
    }
    
    [data-theme="dark"] .btn-toggle-online {
        background: #1A3A2A;
        color: #34D399;
    }
    [data-theme="dark"] .btn-toggle-online:hover {
        background: #059669;
        color: white;
    }
    
    [data-theme="dark"] .btn-toggle-offline {
        background: #3A1A1A;
        color: #F87171;
    }
    [data-theme="dark"] .btn-toggle-offline:hover {
        background: #EF4444;
        color: white;
    }
    
    /* Card */
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s;
    }
    
    .card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.05);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .title-blue { color: #0B5ED7; }
    
    /* Pagination */
    .pagination {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    
    .pagination .page-link {
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        text-decoration: none;
        font-size: 0.8rem;
        transition: all 0.3s;
        background: var(--bg-card);
    }
    
    .pagination .page-link:hover {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    .pagination .page-link.active {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    .pagination .page-link.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    [data-theme="dark"] .pagination .page-link {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .pagination .page-link:hover {
        background: #0B5ED7;
        border-color: #0B5ED7;
    }
    
    /* Search input */
    .search-input-white {
        background: #FFFFFF !important;
        color: #1E293B !important;
        border: 2px solid #E2E8F0 !important;
        transition: all 0.3s ease !important;
    }
    
    .search-input-white:focus {
        border-color: #0B5ED7 !important;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15) !important;
    }
    
    [data-theme="dark"] .search-input-white {
        background: #1E293B !important;
        color: #F1F5F9 !important;
        border-color: #334155 !important;
    }
    
    [data-theme="dark"] .search-input-white:focus {
        border-color: #6EA8FE !important;
    }
    
    /* Doctor Avatar */
    .doctor-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        color: white;
        flex-shrink: 0;
    }
    
    .doctor-avatar.blue { background: linear-gradient(135deg, #0B5ED7, #1A73E8); }
    .doctor-avatar.green { background: linear-gradient(135deg, #059669, #0AA84F); }
    .doctor-avatar.purple { background: linear-gradient(135deg, #7B2FBE, #9B4DCA); }
    .doctor-avatar.orange { background: linear-gradient(135deg, #F59E0B, #FBBF24); }
    .doctor-avatar.red { background: linear-gradient(135deg, #EF4444, #F87171); }
    .doctor-avatar.pink { background: linear-gradient(135deg, #EC4899, #F472B6); }
    .doctor-avatar.teal { background: linear-gradient(135deg, #0D9488, #14B8A6); }
    
    .doctor-avatar .online-indicator {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid var(--bg-card);
    }
    
    .doctor-avatar .online-indicator.online { background: #059669; }
    .doctor-avatar .online-indicator.offline { background: #EF4444; }
    
    .doctor-avatar-wrapper {
        position: relative;
        display: inline-block;
    }
    
    /* Responsive */
    @media (max-width: 640px) {
        .stat-card-mini .stat-number {
            font-size: 1.4rem;
        }
        .filter-section {
            flex-direction: column;
            align-items: stretch;
        }
        .filter-section .filter-label {
            margin-bottom: 4px;
        }
        .table-blue tbody td {
            font-size: 0.7rem;
            padding: 6px 10px !important;
        }
        .btn {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
        .doctor-avatar {
            width: 28px;
            height: 28px;
            font-size: 0.7rem;
        }
    }
</style>

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
            <form method="GET" action="" class="flex-1 flex">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Search doctors..." 
                       class="flex-1 px-3 py-2 bg-transparent border-none outline-none text-sm" 
                       style="color: var(--text-primary);">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
            </form>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches_list as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
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
                <i class="fas fa-user-md mr-2" style="color: #0B5ED7;"></i> Doctors
            </h1>
            <p class="page-subtitle">
                Manage all doctors in the system
                <span class="branch-tag ml-2" style="background: #0B5ED7;">
                    <i class="fas fa-user-md"></i> <?= $total_all ?> Total
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-circle mr-1" style="color: #059669;"></i> <?= $online_count ?> Online
                </span>
                <span class="ml-2 inline-flex bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs border border-red-200">
                    <i class="fas fa-circle mr-1" style="color: #EF4444;"></i> <?= $offline_count ?> Offline
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="add_doctor.php?branch=<?= $selected_branch_id ?>" class="btn btn-primary btn-sm" style="background: #0B5ED7;">
                <i class="fas fa-plus"></i> Add Doctor
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        
        <div class="stat-card-mini">
            <div class="stat-icon">👨‍⚕️</div>
            <p class="stat-number"><?= $total_all ?></p>
            <p class="stat-label">Total Doctors</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">🟢</div>
            <p class="stat-number green"><?= $online_count ?></p>
            <p class="stat-label">Online</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">🔴</div>
            <p class="stat-number red"><?= $offline_count ?></p>
            <p class="stat-label">Offline</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">👤</div>
            <p class="stat-number purple"><?= $with_patients ?></p>
            <p class="stat-label">With Patients</p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section">
        <span class="filter-label"><i class="fas fa-filter"></i> Status:</span>
        
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => '', 'page' => 1])) ?>" 
           class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">
            <i class="fas fa-globe"></i> All
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'online', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'online' ? 'active' : '' ?>">
            <i class="fas fa-circle" style="color: #059669;"></i> Online
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'offline', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'offline' ? 'active' : '' ?>">
            <i class="fas fa-circle" style="color: #EF4444;"></i> Offline
        </a>
        
        <?php if (!empty($search) || !empty($status_filter)): ?>
            <a href="doctors_list.php?branch=<?= $selected_branch_id ?>" class="filter-btn" style="border-color: #EF4444; color: #EF4444;">
                <i class="fas fa-times"></i> Clear Filters
            </a>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- DOCTORS LIST -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i>
                Doctors List
                <span class="text-sm font-normal text-gray-400">(<?= $total_doctors ?> doctors)</span>
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="min-width: 180px;">Doctor</th>
                        <th style="min-width: 120px;">Specialty</th>
                        <th style="min-width: 120px;">Branch</th>
                        <th style="min-width: 80px;">Patients</th>
                        <th style="min-width: 80px;">Visits</th>
                        <th style="min-width: 100px;">Status</th>
                        <th style="min-width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($doctors) > 0): ?>
                        <?php $i = $offset + 1; foreach ($doctors as $doctor): ?>
                            <?php 
                                // Get avatar color based on name
                                $colors = ['blue', 'green', 'purple', 'orange', 'red', 'pink', 'teal'];
                                $color_index = abs(crc32($doctor['full_name'])) % count($colors);
                                $avatar_color = $colors[$color_index];
                                $initials = implode('', array_map(function($name) {
                                    return strtoupper($name[0]);
                                }, explode(' ', trim($doctor['full_name']))));
                                
                                $is_online = $doctor['is_online'] == 1;
                            ?>
                            <tr>
                                <td class="font-bold text-blue-600 dark:text-blue-400"><?= $i++ ?></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="doctor-avatar-wrapper">
                                            <div class="doctor-avatar <?= $avatar_color ?>">
                                                <?= substr($initials, 0, 2) ?>
                                                <span class="online-indicator <?= $is_online ? 'online' : 'offline' ?>"></span>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-sm"><?= htmlspecialchars($doctor['full_name']) ?></p>
                                            <p class="text-xs text-gray-400"><?= htmlspecialchars($doctor['email']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= htmlspecialchars($doctor['specialty'] ?? 'General') ?></span>
                                </td>
                                <td><?= htmlspecialchars($doctor['branch_name'] ?? 'N/A') ?></td>
                                <td class="text-center">
                                    <span class="badge badge-blue"><?= $doctor['total_patients'] ?? 0 ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-green"><?= $doctor['total_visits'] ?? 0 ?></span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $is_online ? 'online' : 'offline' ?>">
                                        <i class="fas fa-circle text-[8px]"></i>
                                        <?= $is_online ? 'Online' : 'Offline' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- View -->
                                        <a href="doctor_details.php?id=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action btn-view" title="View Doctor">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <!-- Edit -->
                                        <a href="edit_doctor.php?id=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action btn-edit" title="Edit Doctor">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        
                                        <!-- Toggle Online/Offline -->
                                        <?php if ($is_online): ?>
                                            <a href="?toggle=<?= $doctor['id'] ?>&page=<?= $page ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" 
                                               class="btn-action btn-toggle-offline" 
                                               onclick="return confirm('Are you sure you want to set Dr. <?= htmlspecialchars($doctor['full_name']) ?> to OFFLINE?')" 
                                               title="Set Offline">
                                                <i class="fas fa-power-off"></i> Offline
                                            </a>
                                        <?php else: ?>
                                            <a href="?toggle=<?= $doctor['id'] ?>&page=<?= $page ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" 
                                               class="btn-action btn-toggle-online" 
                                               onclick="return confirm('Are you sure you want to set Dr. <?= htmlspecialchars($doctor['full_name']) ?> to ONLINE?')" 
                                               title="Set Online">
                                                <i class="fas fa-power-off"></i> Online
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400">
                                <i class="fas fa-user-md text-4xl block mb-3" style="color: #0B5ED7;"></i>
                                <p class="text-lg font-medium" style="color: #1E293B; dark:text-white;">
                                    <?= !empty($search) || !empty($status_filter) ? 'No doctors found matching your filters' : 'No doctors found' ?>
                                </p>
                                <p class="text-sm">
                                    <?= !empty($search) || !empty($status_filter) ? 'Try changing your search or filter criteria' : 'Click "Add Doctor" to create one' ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ================================================================ -->
        <!-- PAGINATION -->
        <!-- ================================================================ -->
        <?php if ($total_pages > 1): ?>
            <div class="flex flex-wrap justify-between items-center gap-3 mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Showing <?= $offset + 1 ?> - <?= min($offset + $per_page, $total_doctors) ?> of <?= $total_doctors ?> doctors
                </div>
                
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    <?php endif; ?>
                    
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="?page=<?= $p ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" 
                           class="page-link <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
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
            Doctors Management
            <span class="text-gray-300 mx-2">|</span>
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
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

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
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
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

    console.log('%c🏥 Braick Dispensary - Doctors Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👨‍⚕️ Total Doctors: <?= $total_all ?>', 'font-size:13px; color:#059669;');
    console.log('%c🟢 Online: <?= $online_count ?> | 🔴 Offline: <?= $offline_count ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Showing: <?= count($doctors) ?> doctors', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>