<?php
// ================================================================
// FILE: frontend/pages/doctor/my_patients.php
// DOCTOR - MY PATIENTS LIST
// FIXED: Single VIEW button only
// New Visit moved to patient_details page
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Doctor Only
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'dr.john';
    $_SESSION['is_online'] = 1;
}

// Include database and helpers
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'];
$selected_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// GET DOCTOR PROFILE PICTURE
// ================================================================
$profile_pic = '';
$stmt = $db->prepare("SELECT profile_pic FROM users WHERE id = ? AND role = 'doctor'");
$stmt->execute([$doctor_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user_data && !empty($user_data['profile_pic'])) {
    $profile_pic = $user_data['profile_pic'];
}
$_SESSION['profile_pic'] = $profile_pic;

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

// ================================================================
// GET DOCTOR'S PATIENTS
// ================================================================
$where_clause = " WHERE p.assigned_doctor_id = ?";
$params = [$doctor_id];

// Search filter
if (!empty($search)) {
    $where_clause .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ? OR p.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Status filter (active patients with visits)
if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $where_clause .= " AND EXISTS (SELECT 1 FROM visits WHERE patient_id = p.id)";
    } elseif ($status_filter === 'inactive') {
        $where_clause .= " AND NOT EXISTS (SELECT 1 FROM visits WHERE patient_id = p.id)";
    }
}

// ================================================================
// GET PATIENTS WITH PAGINATION
// ================================================================

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM patients p $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_patients / $per_page);

// Get patients for current page
$sql = "
    SELECT p.*, 
           b.name as branch_name,
           (SELECT COUNT(*) FROM visits WHERE patient_id = p.id) as total_visits,
           (SELECT COUNT(*) FROM visits WHERE patient_id = p.id AND status = 'completed') as completed_visits,
           (SELECT COUNT(*) FROM prescriptions WHERE patient_id = p.id) as total_prescriptions,
           (SELECT COUNT(*) FROM patient_bills WHERE patient_id = p.id AND status != 'cancelled') as total_bills,
           (SELECT MAX(created_at) FROM visits WHERE patient_id = p.id) as last_visit_date
    FROM patients p
    LEFT JOIN branches b ON p.branch_id = b.id
    $where_clause
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================

// Total patients assigned to this doctor
$stmt = $db->prepare("SELECT COUNT(*) as total FROM patients WHERE assigned_doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_assigned = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Active patients (with at least one visit)
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT p.id) as total 
    FROM patients p 
    INNER JOIN visits v ON p.id = v.patient_id 
    WHERE p.assigned_doctor_id = ?
");
$stmt->execute([$doctor_id]);
$active_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Patients with pending visits
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT p.id) as total 
    FROM patients p 
    INNER JOIN visits v ON p.id = v.patient_id 
    WHERE p.assigned_doctor_id = ? AND v.status IN ('pending', 'assigned', 'with_doctor')
");
$stmt->execute([$doctor_id]);
$pending_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total visits for this doctor
$stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/doctor_header.php';
include_once '../../components/doctor_sidebar.php';
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
    .stat-card-mini .stat-number.purple { color: #7B2FBE; }
    .stat-card-mini .stat-number.red { color: #EF4444; }
    
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
    
    /* Badge styles */
    .badge {
        padding: 3px 12px !important;
        border-radius: 20px !important;
        font-size: 0.6rem !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        border: none !important;
    }
    
    .badge-success {
        background: #D1FAE5 !important;
        color: #059669 !important;
    }
    
    .badge-warning {
        background: #FEF3C7 !important;
        color: #D97706 !important;
    }
    
    .badge-danger {
        background: #FEE2E2 !important;
        color: #EF4444 !important;
    }
    
    .badge-info {
        background: #E8F0FE !important;
        color: #0B5ED7 !important;
    }
    
    .badge-secondary {
        background: #E2E8F0 !important;
        color: #64748B !important;
    }
    
    .badge-blue {
        background: #E8F0FE !important;
        color: #0B5ED7 !important;
    }
    
    .badge-green {
        background: #D1FAE5 !important;
        color: #059669 !important;
    }
    
    [data-theme="dark"] .badge-success {
        background: #1A3A2A !important;
        color: #34D399 !important;
    }
    
    [data-theme="dark"] .badge-warning {
        background: #3A2A1A !important;
        color: #FBBF24 !important;
    }
    
    [data-theme="dark"] .badge-danger {
        background: #3A1A1A !important;
        color: #F87171 !important;
    }
    
    [data-theme="dark"] .badge-info {
        background: #1E3A5F !important;
        color: #6EA8FE !important;
    }
    
    [data-theme="dark"] .badge-secondary {
        background: #2D3748 !important;
        color: #94A3B8 !important;
    }
    
    [data-theme="dark"] .badge-blue {
        background: #1E3A5F !important;
        color: #6EA8FE !important;
    }
    
    [data-theme="dark"] .badge-green {
        background: #1A3A2A !important;
        color: #34D399 !important;
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
    
    /* Action Buttons - SINGLE VIEW BUTTON */
    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 16px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        border: none;
        cursor: pointer;
    }
    
    .btn-action i { font-size: 0.8rem; }
    
    .btn-view {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
    }
    
    .btn-view:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.4);
        color: white;
    }
    
    [data-theme="dark"] .btn-view {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
    }
    
    [data-theme="dark"] .btn-view:hover {
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
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
    
    /* Table Header with Search */
    .table-header-wrapper {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .table-header-wrapper .search-box {
        position: relative;
        flex: 1;
        min-width: 200px;
        max-width: 350px;
    }
    
    .table-header-wrapper .search-box input {
        width: 100%;
        padding: 8px 16px 8px 38px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        background: #FFFFFF !important;
        color: #1E293B !important;
        outline: none;
    }
    
    .table-header-wrapper .search-box input:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
    }
    
    [data-theme="dark"] .table-header-wrapper .search-box input {
        background: #1E293B !important;
        color: #F1F5F9 !important;
        border-color: #334155 !important;
    }
    
    [data-theme="dark"] .table-header-wrapper .search-box input:focus {
        border-color: #6EA8FE;
        box-shadow: 0 0 0 3px rgba(110, 168, 254, 0.15);
    }
    
    .table-header-wrapper .search-box .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .table-header-wrapper .search-info {
        font-size: 0.8rem;
        color: var(--text-secondary);
        white-space: nowrap;
    }
    
    .table-header-wrapper .search-info strong {
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .table-header-wrapper .search-info strong {
        color: #6EA8FE;
    }
    
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
        .table-header-wrapper {
            flex-direction: column;
            align-items: stretch;
        }
        .table-header-wrapper .search-box {
            max-width: 100%;
        }
        .btn-action {
            padding: 4px 12px;
            font-size: 0.65rem;
        }
    }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    
    <!-- Left Side -->
    <div class="flex items-center gap-3 flex-1 min-w-0">
        <button id="sidebarToggle" class="sidebar-toggle-btn" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <a href="dashboard.php" class="flex items-center gap-2 shrink-0" style="color:var(--text-primary);">
            <i class="fas fa-home text-primary"></i>
            <span class="font-semibold text-sm hidden sm:inline">Dashboard</span>
        </a>
    </div>
    
    <!-- Search Bar -->
    <div class="search-wrapper">
        <i class="fas fa-search text-gray-400 ml-3"></i>
        <input type="text" id="searchInput" placeholder="Search patients by name, ID or phone...">
        <button id="searchBtn" class="search-btn">
            <i class="fas fa-search mr-1"></i><span>Search</span>
        </button>
    </div>
    
    <!-- Right Side -->
    <div class="flex items-center gap-3 shrink-0">
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="statusToggle" class="status-toggle <?= $is_online ? '' : 'offline' ?>" title="Toggle Online Status">
            <span class="status-dot <?= $is_online ? 'online' : 'offline' ?>" id="statusDot"></span>
            <span class="status-text" id="statusText"><?= $is_online ? 'Online' : 'Offline' ?></span>
            <span class="status-spinner"></span>
        </button>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn" id="notifBtn" title="Notifications">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot" id="notifDot" style="display: none;"></span>
        </button>
        
        <a href="profile.php" class="avatar-link" title="Profile">
            <?php 
                $show_initial = true;
                $initial = strtoupper(substr($doctor_name, 0, 1));
                $avatar_url = '';
                
                if (!empty($profile_pic)) {
                    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic;
                    if (file_exists($file_path)) {
                        $avatar_url = '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic;
                        $show_initial = false;
                    }
                }
            ?>
            <?php if ($show_initial): ?>
                <div class="avatar-placeholder avatar-color-<?= (abs(crc32($doctor_name)) % 7) + 1 ?>">
                    <?= $initial ?>
                </div>
            <?php else: ?>
                <img src="<?= $avatar_url ?>" alt="Profile" class="avatar-img">
            <?php endif; ?>
            <span class="status-ring <?= $is_online ? '' : 'offline' ?>" id="avatarStatusRing"></span>
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
                <i class="fas fa-users mr-2" style="color: #0B5ED7;"></i> My Patients
            </h1>
            <p class="page-subtitle">
                View and manage your assigned patients
                <span class="branch-tag ml-2" style="background: #0B5ED7;">
                    <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user-injured mr-1"></i> <?= $total_assigned ?> Total Patients
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        
        <div class="stat-card-mini">
            <div class="stat-icon">👤</div>
            <p class="stat-number"><?= $total_assigned ?></p>
            <p class="stat-label">Total Patients</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">🟢</div>
            <p class="stat-number green"><?= $active_patients ?></p>
            <p class="stat-label">Active Patients</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">⏳</div>
            <p class="stat-number orange"><?= $pending_visits ?></p>
            <p class="stat-label">Pending Visits</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">📋</div>
            <p class="stat-number purple"><?= $total_visits ?></p>
            <p class="stat-label">Total Visits</p>
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
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'active', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'active' ? 'active' : '' ?>">
            <i class="fas fa-check-circle" style="color: #059669;"></i> Active
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'inactive', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'inactive' ? 'active' : '' ?>">
            <i class="fas fa-clock" style="color: #F59E0B;"></i> Inactive
        </a>
        
        <?php if (!empty($search) || !empty($status_filter)): ?>
            <a href="my_patients.php" class="filter-btn" style="border-color: #EF4444; color: #EF4444;">
                <i class="fas fa-times"></i> Clear Filters
            </a>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENTS LIST WITH SEARCH IN TABLE HEADER -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i>
                Patients List
                <span class="text-sm font-normal text-gray-400">(<?= $total_patients ?> patients)</span>
            </h3>
        </div>
        
        <!-- Table Header with Search -->
        <div class="table-header-wrapper">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="tableSearch" placeholder="Filter patients in table..." onkeyup="filterTable()">
            </div>
            <div class="search-info">
                Showing <strong id="visibleCount"><?= count($patients) ?></strong> of <strong id="totalCount"><?= $total_patients ?></strong> patients
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full" id="patientsTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="min-width: 120px;">Patient ID</th>
                        <th style="min-width: 150px;">Patient Name</th>
                        <th style="min-width: 120px;">Phone</th>
                        <th style="min-width: 80px;">Visits</th>
                        <th style="min-width: 80px;">Prescriptions</th>
                        <th style="min-width: 110px;">Last Visit</th>
                        <th style="min-width: 120px;">Action</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if (count($patients) > 0): ?>
                        <?php $i = $offset + 1; foreach ($patients as $patient): ?>
                            <tr>
                                <td class="font-bold text-blue-600 dark:text-blue-400"><?= $i++ ?></td>
                                <td class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">
                                    <?= htmlspecialchars($patient['patient_id']) ?>
                                </td>
                                <td class="font-semibold"><?= htmlspecialchars($patient['full_name']) ?></td>
                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                <td class="text-center">
                                    <span class="badge badge-info"><?= $patient['total_visits'] ?? 0 ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-green"><?= $patient['total_prescriptions'] ?? 0 ?></span>
                                </td>
                                <td class="text-xs">
                                    <?= $patient['last_visit_date'] ? date('M d, Y', strtotime($patient['last_visit_date'])) : 'Never' ?>
                                </td>
                                <td>
                                    <!-- SINGLE VIEW BUTTON -->
                                    <a href="patient_details.php?id=<?= $patient['id'] ?>" 
                                       class="btn-action btn-view" title="View Patient Details">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400">
                                <i class="fas fa-user-injured text-4xl block mb-3" style="color: #0B5ED7;"></i>
                                <p class="text-lg font-medium" style="color: #1E293B; dark:text-white;">
                                    <?= !empty($search) || !empty($status_filter) ? 'No patients found matching your filters' : 'No patients assigned to you' ?>
                                </p>
                                <p class="text-sm">
                                    <?= !empty($search) || !empty($status_filter) ? 'Try changing your search or filter criteria' : 'Patients will appear here once assigned to you' ?>
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
                    Showing <?= $offset + 1 ?> - <?= min($offset + $per_page, $total_patients) ?> of <?= $total_patients ?> patients
                </div>
                
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    <?php endif; ?>
                    
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="?page=<?= $p ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" 
                           class="page-link <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" class="page-link">
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
            My Patients
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
    // TABLE SEARCH FILTER (Real-time)
    // ================================================================
    function filterTable() {
        var input = document.getElementById('tableSearch');
        var filter = input.value.toLowerCase();
        var table = document.getElementById('patientsTable');
        var rows = table.getElementsByTagName('tr');
        var visibleCount = 0;
        
        for (var i = 1; i < rows.length; i++) {
            var row = rows[i];
            var text = row.textContent.toLowerCase();
            if (text.includes(filter)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        }
        
        document.getElementById('visibleCount').textContent = visibleCount;
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

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    console.log('%c🏥 Braick Dispensary - My Patients', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👨‍⚕️ Doctor: Dr. <?= htmlspecialchars($doctor_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Total Patients: <?= $total_assigned ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🟢 Active: <?= $active_patients ?> | ⏳ Pending Visits: <?= $pending_visits ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔍 Real-time table search filter enabled', 'font-size:13px; color:#7B2FBE;');
    console.log('%c✅ Single VIEW button - New Visit moved to patient_details page', 'font-size:13px; color:#059669;');
</script>

</body>
</html>