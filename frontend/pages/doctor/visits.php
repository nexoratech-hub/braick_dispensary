<?php
// ================================================================
// FILE: frontend/pages/doctor/visits.php
// DOCTOR - VISITS LIST (FILTERED BY STATUS)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. SARAH MWAMBA (ID: 2) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 2;
    $_SESSION['full_name'] = 'Dr. Sarah Mwamba';
    $_SESSION['username'] = 'dr.sarah';
    $_SESSION['email'] = 'sarah@braick.com';
    $_SESSION['phone'] = '+255 700 000 001';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'Cardiology';
    $_SESSION['profile_pic'] = '';
}

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// FUNCTIONS
// ================================================================
function time_ago($timestamp) {
    if (empty($timestamp)) return 'N/A';
    $time = strtotime($timestamp);
    if ($time === false) return 'N/A';
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
    return date('M d, Y', $time);
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $index = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $index = ($index + ord($name[$i])) % count($colors);
    }
    return $colors[$index];
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET FILTERS
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'today';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// BUILD QUERY
// ================================================================
$sql = "
    SELECT v.*, 
           p.full_name as patient_name, 
           p.patient_id, 
           p.phone,
           p.gender,
           p.date_of_birth,
           u.full_name as doctor_name,
           u.specialty
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE v.doctor_id = ?
";

$params = [$doctor_id];

// Filter by status
if (!empty($status_filter)) {
    $sql .= " AND v.status = ?";
    $params[] = $status_filter;
}

// Filter: Today
if ($filter === 'today') {
    $sql .= " AND DATE(v.created_at) = CURDATE()";
}

// Filter: Pending (all pending statuses)
if ($filter === 'pending') {
    $sql .= " AND v.status IN ('pending', 'assigned', 'with_doctor')";
}

// Filter: Completed
if ($filter === 'completed') {
    $sql .= " AND v.status = 'completed'";
}

// Search
if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY v.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATUS COUNTS
// ================================================================
$status_counts = [];
$statuses = ['pending', 'assigned', 'with_doctor', 'completed', 'cancelled'];
foreach ($statuses as $status) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status = ?");
    $stmt->execute([$doctor_id, $status]);
    $status_counts[$status] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

// Today count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$doctor_id]);
$today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Pending count (all pending statuses)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status IN ('pending', 'assigned', 'with_doctor')");
$stmt->execute([$doctor_id]);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Completed count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE doctor_id = ? AND status = 'completed'");
$stmt->execute([$doctor_id]);
$completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// GET DOCTOR'S BRANCH NAME
// ================================================================
$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $doctor_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $doctor_branch_name = 'Branch';
}

// ================================================================
// INCLUDE DOCTOR HEADER & SIDEBAR
// ================================================================
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_header.php';
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_sidebar.php';
?>

<style>
    /* ================================================================
       VISITS PAGE STYLES
       ================================================================ */
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 18px 20px;
        border: 2px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.3s ease;
        text-decoration: none;
        cursor: pointer;
    }
    
    .stat-card:hover {
        border-color: var(--primary);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    
    .stat-card .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        color: white;
        flex-shrink: 0;
    }
    
    .stat-card.blue .stat-icon { background: var(--primary); }
    .stat-card.yellow .stat-icon { background: #D97706; }
    .stat-card.green .stat-icon { background: #059669; }
    .stat-card.purple .stat-icon { background: #7C3AED; }
    .stat-card.red .stat-icon { background: #DC2626; }
    
    .stat-card .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }
    
    .stat-card .stat-label {
        font-size: 0.75rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .stat-card.active {
        border-color: var(--primary);
        background: var(--primary-bg);
    }
    
    [data-theme="dark"] .stat-card.active {
        background: #1E3A5F;
        border-color: #6EA8FE;
    }
    
    /* ===== FILTER CARD ===== */
    .filter-card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }
    
    .filter-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    
    .filter-form {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }
    
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        width: 100%;
    }
    
    .filter-search {
        display: flex;
        align-items: center;
        flex: 1;
        min-width: 200px;
        background: var(--bg-body);
        border: 2px solid var(--border-color);
        border-radius: 10px;
        transition: all 0.3s;
        padding: 0 12px;
    }
    
    .filter-search:focus-within {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    
    .filter-search .fa-search {
        color: var(--text-secondary);
        font-size: 0.85rem;
        opacity: 0.5;
    }
    
    .filter-input {
        border: none;
        background: transparent;
        padding: 10px 12px;
        width: 100%;
        font-size: 0.85rem;
        outline: none;
        color: var(--text-primary);
    }
    
    .filter-input::placeholder {
        color: var(--text-secondary);
        opacity: 0.5;
    }
    
    .filter-select {
        padding: 10px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        background: var(--bg-body);
        color: var(--text-primary);
        outline: none;
        cursor: pointer;
        transition: all 0.3s ease;
        min-width: 140px;
    }
    
    .filter-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    
    [data-theme="dark"] .filter-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .filter-search {
        background: #0F172A;
        border-color: #334155;
    }
    [data-theme="dark"] .filter-select {
        background: #0F172A;
        border-color: #334155;
        color: #F1F5F9;
    }
    
    /* ===== TABLE ===== */
    .table-card {
        background: var(--bg-card);
        border-radius: 14px;
        border: 2px solid var(--border-color);
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .table-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    
    [data-theme="dark"] .table-card {
        background: #1E293B;
        border-color: #334155;
    }
    
    .table-wrap {
        overflow-x: auto;
        padding: 0;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
        min-width: 700px;
    }
    
    .data-table thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #fff;
        background: var(--primary);
        border-bottom: 3px solid var(--primary-dark);
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .data-table tbody tr {
        transition: background 0.2s ease;
    }
    
    .data-table tbody tr:nth-child(even) {
        background: #F8FAFC;
    }
    .data-table tbody tr:nth-child(odd) {
        background: #fff;
    }
    .data-table tbody tr:hover {
        background: #D1FAE5;
    }
    
    [data-theme="dark"] .data-table tbody tr:nth-child(even) {
        background: #1E293B;
    }
    [data-theme="dark"] .data-table tbody tr:nth-child(odd) {
        background: #0F172A;
    }
    [data-theme="dark"] .data-table tbody tr:hover {
        background: #1A3A2A;
    }
    
    .data-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    /* ===== STATUS BADGES ===== */
    .status-badge {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 12px;
        border-radius: 12px;
        display: inline-block;
    }
    
    .status-badge.pending { background: #FEF3C7; color: #D97706; }
    .status-badge.assigned { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.with_doctor { background: #FEF3C7; color: #D97706; }
    .status-badge.completed { background: #D1FAE5; color: #059669; }
    .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
    
    [data-theme="dark"] .status-badge.pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.assigned { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge.with_doctor { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge.cancelled { background: #3A1A1A; color: #F87171; }
    
    /* ===== AVATAR ===== */
    .avatar-sm {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
    }
    
    /* ===== BADGES ===== */
    .badge {
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #fff;
        border: none;
    }
    
    .badge-success { background: #059669; }
    .badge-warning { background: #D97706; }
    .badge-info { background: #0B5ED7; }
    .badge-primary { background: #0B5ED7; }
    
    /* ===== BUTTONS ===== */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.7rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }
    
    .btn-blue {
        background: #0B5ED7;
        color: #fff;
    }
    .btn-blue:hover {
        background: #0A4CA8;
        transform: scale(1.05);
    }
    
    .btn-consult {
        background: #7C3AED;
        color: #fff;
    }
    .btn-consult:hover {
        background: #6D28D9;
        transform: scale(1.05);
    }
    
    .btn-green {
        background: #059669;
        color: #fff;
    }
    .btn-green:hover {
        background: #047857;
        transform: scale(1.05);
    }
    
    .btn-view {
        background: #0B5ED7;
        color: #fff;
    }
    .btn-view:hover {
        background: #0A4CA8;
        transform: scale(1.05);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: scale(1.05);
    }
    
    .btn-sm {
        padding: 4px 10px;
        font-size: 0.65rem;
        border-radius: 6px;
    }
    
    .action-buttons {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: nowrap;
        justify-content: center;
    }
    
    /* ===== EMPTY STATE ===== */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 3rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 12px;
    }
    
    .empty-state h4 {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
    }
    
    .empty-state p {
        font-size: 0.85rem;
    }
    
    /* ===== PAGE HEADER ===== */
    .page-header {
        border-bottom: 3px solid #0B5ED7;
        padding-bottom: 12px;
    }
    
    .page-header .page-title {
        color: #0B3D8A;
        font-size: 1.6rem;
        font-weight: 700;
    }
    
    [data-theme="dark"] .page-header .page-title {
        color: #6EA8FE;
    }
    
    .page-header .page-subtitle {
        color: var(--text-secondary);
        font-size: 0.85rem;
    }
    
    .branch-tag {
        background: #059669;
        color: #fff;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    /* ===== FOOTER ===== */
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .footer .footer-brand {
        color: #0B5ED7;
        font-weight: 600;
    }
    
    /* ===== UTILITIES ===== */
    .font-medium { font-weight: 500; }
    .font-mono { font-family: monospace; }
    .text-xs { font-size: 0.75rem; }
    .text-sm { font-size: 0.85rem; }
    .text-muted { color: var(--text-secondary); }
    .mt-2 { margin-top: 0.5rem; }
    .mb-3 { margin-bottom: 0.75rem; }
    .mb-5 { margin-bottom: 1.25rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-1 { margin-right: 0.25rem; }
    .mr-2 { margin-right: 0.5rem; }
    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .gap-2 { gap: 0.5rem; }
    .gap-3 { gap: 0.75rem; }
    .gap-4 { gap: 1rem; }
    
    /* ===== RESPONSIVE ===== */
    @media (max-width: 1024px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .data-table {
            font-size: 0.75rem;
            min-width: 600px;
        }
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .filter-group {
            flex-direction: column;
            align-items: stretch;
        }
        .filter-search {
            min-width: 100%;
        }
        .filter-select {
            width: 100%;
        }
        .filter-form .btn {
            width: 100%;
            justify-content: center;
        }
        .data-table {
            font-size: 0.7rem;
            min-width: 500px;
        }
        .data-table th,
        .data-table td {
            padding: 6px 10px;
        }
        .action-buttons {
            flex-wrap: wrap;
        }
        .action-buttons .btn {
            flex: 1;
            justify-content: center;
        }
        .btn-sm {
            padding: 3px 8px;
            font-size: 0.6rem;
        }
        .avatar-sm {
            width: 28px;
            height: 28px;
            font-size: 0.7rem;
        }
        .page-header .page-title {
            font-size: 1.2rem;
        }
        .stat-card .stat-number {
            font-size: 1.2rem;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .data-table {
            font-size: 0.65rem;
            min-width: 420px;
        }
        .data-table th,
        .data-table td {
            padding: 4px 8px;
        }
        .action-buttons {
            flex-direction: column;
            gap: 2px;
        }
        .action-buttons .btn {
            width: 100%;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clinic-medical mr-2" style="color: #0B5ED7;"></i> Visits
            </h1>
            <p class="page-subtitle">
                View all patient visits
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-list mr-1"></i> <?= count($visits) ?> visits
                </span>
            </p>
        </div>
        <div>
            <span class="text-sm text-gray-500 flex items-center">
                <i class="fas fa-user-md mr-1"></i> <?= htmlspecialchars($doctor_name) ?>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <a href="?filter=today" class="stat-card blue <?= $filter === 'today' ? 'active' : '' ?>">
            <div>
                <p class="stat-label">Today</p>
                <p class="stat-number"><?= $today_count ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
        </a>
        
        <a href="?filter=pending" class="stat-card yellow <?= $filter === 'pending' ? 'active' : '' ?>">
            <div>
                <p class="stat-label">Pending</p>
                <p class="stat-number"><?= $pending_count ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </a>
        
        <a href="?filter=completed" class="stat-card green <?= $filter === 'completed' ? 'active' : '' ?>">
            <div>
                <p class="stat-label">Completed</p>
                <p class="stat-number"><?= $completed_count ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </a>
        
        <a href="?filter=all" class="stat-card purple <?= $filter === 'all' ? 'active' : '' ?>">
            <div>
                <p class="stat-label">All Visits</p>
                <p class="stat-number"><?= count($visits) ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-list"></i></div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- SEARCH & FILTER -->
    <!-- ================================================================ -->
    <div class="filter-card">
        <form method="GET" class="filter-form">
            <input type="hidden" name="filter" value="<?= $filter ?>">
            
            <div class="filter-group">
                <div class="filter-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" class="filter-input" placeholder="Search by patient name, ID or phone..." value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="assigned" <?= $status_filter === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                    <option value="with_doctor" <?= $status_filter === 'with_doctor' ? 'selected' : '' ?>>With Doctor</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                
                <button type="submit" class="btn btn-blue">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if ($search || $status_filter): ?>
                    <a href="?filter=<?= $filter ?>" class="btn btn-outline">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- VISITS TABLE -->
    <!-- ================================================================ -->
    <div class="table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0; width:50px;">#</th>
                        <th>Patient</th>
                        <th>Patient ID</th>
                        <th>Phone</th>
                        <th>Doctor</th>
                        <th>Visit Type</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="border-radius: 0 8px 0 0; text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($visits) > 0): ?>
                        <?php foreach ($visits as $index => $visit): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="avatar-sm" style="background: <?= getUserColor($visit['patient_name']) ?>;">
                                            <?= strtoupper(substr($visit['patient_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-medium"><?= htmlspecialchars($visit['patient_name']) ?></div>
                                            <div class="text-xs text-muted"><?= htmlspecialchars($visit['gender'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="font-mono"><?= htmlspecialchars($visit['patient_id'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($visit['doctor_name']): ?>
                                        <div class="text-sm">Dr. <?= htmlspecialchars($visit['doctor_name']) ?></div>
                                        <div class="text-xs text-muted"><?= htmlspecialchars($visit['specialty'] ?? 'GP') ?></div>
                                    <?php else: ?>
                                        <span class="text-xs text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-xs capitalize"><?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?></span></td>
                                <td>
                                    <span class="status-badge <?= $visit['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $visit['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= isset($visit['created_at']) ? date('M d, Y h:i A', strtotime($visit['created_at'])) : 'N/A' ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="visit_details.php?id=<?= $visit['id'] ?>" class="btn btn-view btn-sm" title="View Visit">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="view_patient.php?id=<?= $visit['patient_id'] ?>" class="btn btn-blue btn-sm" title="View Patient">
                                            <i class="fas fa-user"></i>
                                        </a>
                                        <?php if ($visit['status'] !== 'completed' && $visit['status'] !== 'cancelled'): ?>
                                            <a href="consultation.php?visit_id=<?= $visit['id'] ?>" class="btn btn-consult btn-sm" title="Consult">
                                                <i class="fas fa-stethoscope"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-8 text-muted">
                                <i class="fas fa-clinic-medical text-3xl block mb-2"></i>
                                <?php if ($search): ?>
                                    No visits found matching "<strong><?= htmlspecialchars($search) ?></strong>"
                                <?php elseif ($filter === 'today'): ?>
                                    No visits for today
                                <?php elseif ($filter === 'pending'): ?>
                                    No pending visits
                                <?php elseif ($filter === 'completed'): ?>
                                    No completed visits
                                <?php else: ?>
                                    No visits recorded yet
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visits
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
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
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    console.log('%c🏥 Braick - Visits List', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Visits: <?= count($visits) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔄 Filter: <?= ucfirst($filter) ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>