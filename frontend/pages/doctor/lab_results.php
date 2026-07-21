<?php
// ================================================================
// FILE: frontend/pages/doctor/lab_results.php
// DOCTOR - LAB RESULTS (WITH REAL-TIME AUTO-UPDATE)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. JOHN MUSHI (ID: 5) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['doctor_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'dr.john';
    $_SESSION['specialty'] = 'General Medicine';
}

$doctor_id = $_SESSION['user_id'] ?? $_SESSION['doctor_id'] ?? 5;
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET FILTERS
// ================================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$patient_filter = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;

// ================================================================
// BUILD QUERY - Get lab results for this doctor's patients
// ================================================================
$query = "
    SELECT lt.*, 
           p.full_name as patient_name, p.patient_id, p.phone,
           u.full_name as doctor_name, u.specialty,
           v.visit_number,
           lab.full_name as lab_technician_name,
           TIMESTAMPDIFF(MINUTE, lt.created_at, NOW()) as waiting_time
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN users lab ON lt.lab_technician_id = lab.id
    WHERE lt.branch_id = ? AND lt.doctor_id = ?
";

$params = [$doctor_branch_id, $doctor_id];

// Status filter
if (!empty($status_filter)) {
    if ($status_filter === 'pending') {
        $query .= " AND (lt.status IS NULL OR lt.status = 'pending')";
    } else {
        $query .= " AND lt.status = ?";
        $params[] = $status_filter;
    }
} else {
    // Default: show all except cancelled
    $query .= " AND (lt.status IS NULL OR lt.status != 'cancelled')";
}

// Search
if (!empty($search)) {
    $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Date filter
if (!empty($date_filter)) {
    $query .= " AND DATE(lt.created_at) = ?";
    $params[] = $date_filter;
}

// Patient filter
if ($patient_filter > 0) {
    $query .= " AND p.id = ?";
    $params[] = $patient_filter;
}

$query .= " ORDER BY lt.status DESC, lt.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================

// Pending tests (status NULL or 'pending')
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND doctor_id = ? AND (status IS NULL OR status = 'pending')
");
$stmt->execute([$doctor_branch_id, $doctor_id]);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// In Progress tests
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND doctor_id = ? AND status = 'in_progress'
");
$stmt->execute([$doctor_branch_id, $doctor_id]);
$in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Completed tests
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND doctor_id = ? AND status = 'completed'
");
$stmt->execute([$doctor_branch_id, $doctor_id]);
$completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Completed today
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND doctor_id = ? AND status = 'completed' AND DATE(completed_at) = ?
");
$stmt->execute([$doctor_branch_id, $doctor_id, $today]);
$completed_today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total tests
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND doctor_id = ?
");
$stmt->execute([$doctor_branch_id, $doctor_id]);
$total_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// GET PATIENTS LIST FOR FILTER
// ================================================================
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.full_name, p.patient_id
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    WHERE lt.branch_id = ? AND lt.doctor_id = ?
    ORDER BY p.full_name
");
$stmt->execute([$doctor_branch_id, $doctor_id]);
$patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<style>
    /* ================================================================
       LAB RESULTS STYLES
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 12px 16px;
        border: 2px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    .stat-card .number {
        font-size: 1.4rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .stat-card .number.pending { color: #D97706; }
    .stat-card .number.in-progress { color: #0B5ED7; }
    .stat-card .number.completed { color: #059669; }
    .stat-card .number.total { color: #7C3AED; }
    .stat-card .label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .filter-btn {
        padding: 4px 12px;
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
        border-color: var(--primary);
        color: var(--primary);
    }
    .filter-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    
    .test-row td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }
    .test-row:hover td {
        background: var(--table-hover);
    }
    .test-row.completed {
        background: rgba(5, 150, 105, 0.03);
    }
    .test-row.in_progress {
        background: rgba(11, 94, 215, 0.03);
    }
    .test-row.pending {
        background: rgba(217, 119, 6, 0.03);
    }
    
    .status-badge {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 12px;
        border-radius: 12px;
    }
    .status-badge.pending { background: #FEF3C7; color: #D97706; }
    .status-badge.in_progress { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.completed { background: #D1FAE5; color: #059669; }
    .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
    
    [data-theme="dark"] .status-badge.pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.in_progress { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge.completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge.cancelled { background: #3A1A1A; color: #F87171; }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.7rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    .btn-blue { background: #0B5ED7; color: white; }
    .btn-blue:hover { background: #0A4CA8; transform: scale(1.05); }
    .btn-green { background: #059669; color: white; }
    .btn-green:hover { background: #047857; transform: scale(1.05); }
    .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
    .btn-outline:hover { background: var(--bg-body); border-color: #0B5ED7; color: #0B5ED7; }
    .btn-sm { padding: 3px 8px; font-size: 0.65rem; border-radius: 4px; }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
        min-width: 800px;
    }
    .data-table thead th {
        text-align: left;
        padding: 8px 12px;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: #0B5ED7;
        border-bottom: 3px solid #0A4CA8;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .data-table td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .table-wrap {
        overflow-x: auto;
        max-height: 500px;
        overflow-y: auto;
    }
    .table-wrap::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }
    .table-wrap::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
    .table-wrap::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
    }
    .empty-state i {
        font-size: 3rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 10px;
    }
    
    .update-badge {
        font-size: 0.65rem;
        color: var(--text-secondary);
        background: var(--bg-body);
        padding: 2px 12px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .result-cell {
        max-width: 200px;
        white-space: pre-wrap;
        word-wrap: break-word;
        font-family: monospace;
        font-size: 0.75rem;
        background: var(--bg-body);
        padding: 4px 8px;
        border-radius: 4px;
    }
    .result-cell.empty {
        color: var(--text-secondary);
        font-style: italic;
        font-family: inherit;
    }
    
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    
    .form-control {
        padding: 4px 10px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.8rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
    }
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .quick-stat {
        font-size: 0.7rem;
        padding: 2px 10px;
        border-radius: 12px;
        background: var(--bg-body);
        color: var(--text-secondary);
    }
    .quick-stat .num { font-weight: 600; color: var(--primary); }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .data-table { font-size: 0.7rem; min-width: 650px; }
        .filter-group { flex-direction: column; align-items: stretch; }
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
            <input type="text" id="searchInput" placeholder="Search lab results..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($doctor_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($doctor_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-flask mr-2" style="color: var(--primary);"></i> Lab Results
                <span class="role-badge ml-2">DOCTOR</span>
                <span class="update-badge ml-2" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                View all laboratory test results for your patients
                <span class="ml-2 inline-flex bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs border border-yellow-200">
                    <i class="fas fa-clock mr-1"></i> <?= $pending_count ?> Pending
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-spinner mr-1"></i> <?= $in_progress_count ?> In Progress
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-check-circle mr-1"></i> <?= $completed_count ?> Completed
                </span>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <p class="number pending" id="statPending"><?= $pending_count ?></p>
            <p class="label">⏳ Pending</p>
        </div>
        <div class="stat-card">
            <p class="number in-progress" id="statInProgress"><?= $in_progress_count ?></p>
            <p class="label">🔬 In Progress</p>
        </div>
        <div class="stat-card">
            <p class="number completed" id="statCompleted"><?= $completed_count ?></p>
            <p class="label">✅ Completed</p>
        </div>
        <div class="stat-card">
            <p class="number completed" id="statCompletedToday" style="color: #34D399;"><?= $completed_today_count ?></p>
            <p class="label">📅 Completed Today</p>
        </div>
        <div class="stat-card">
            <p class="number total" id="statTotal"><?= $total_count ?></p>
            <p class="label">📊 Total Tests</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="filter-group">
            <span class="text-sm font-medium text-gray-600 mr-2">Status:</span>
            <a href="lab_results.php" class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">All</a>
            <a href="lab_results.php?status=pending" class="filter-btn <?= $status_filter === 'pending' ? 'active' : '' ?>">⏳ Pending</a>
            <a href="lab_results.php?status=in_progress" class="filter-btn <?= $status_filter === 'in_progress' ? 'active' : '' ?>">🔬 In Progress</a>
            <a href="lab_results.php?status=completed" class="filter-btn <?= $status_filter === 'completed' ? 'active' : '' ?>">✅ Completed</a>
            
            <span class="text-sm font-medium text-gray-600 ml-4 mr-2">Date:</span>
            <input type="date" id="dateFilter" value="<?= $date_filter ?>"
                   onchange="window.location.href='lab_results.php?date='+this.value+'&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&patient=<?= $patient_filter ?>'"
                   class="form-control" style="width:auto;">
            
            <?php if (!empty($patients_list)): ?>
                <span class="text-sm font-medium text-gray-600 ml-4 mr-2">Patient:</span>
                <select id="patientFilter" class="form-control" style="width:auto;min-width:120px;"
                        onchange="window.location.href='lab_results.php?patient='+this.value+'&status=<?= $status_filter ?>&date=<?= $date_filter ?>&search=<?= urlencode($search) ?>'">
                    <option value="0">All Patients</option>
                    <?php foreach ($patients_list as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $patient_filter == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['patient_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            
            <?php if (!empty($search) || !empty($status_filter) || !empty($date_filter) || $patient_filter > 0): ?>
                <a href="lab_results.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RESULTS TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Lab Results
                <span class="text-sm font-normal text-gray-400" id="resultCount">(<?= count($lab_tests) ?>)</span>
            </h3>
            <div class="quick-stats">
                <span class="quick-stat">With Results: <span class="num" id="withResultsCount">0</span></span>
                <span class="text-sm text-gray-400">Scroll to view all</span>
            </div>
        </div>
        
        <div class="table-wrap">
            <table class="data-table" id="resultTable">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Test Name</th>
                        <th>Patient</th>
                        <th>Visit #</th>
                        <th>Status</th>
                        <th>Result</th>
                        <th>Lab Tech</th>
                        <th>Date</th>
                        <th style="border-radius: 0 8px 0 0;">Actions</th>
                    </tr>
                </thead>
                <tbody id="resultTableBody">
                    <?php if (count($lab_tests) > 0): ?>
                        <?php $i = 1; 
                        $with_results = 0;
                        foreach ($lab_tests as $test): 
                            $status = $test['status'] ?? 'pending';
                            $has_result = !empty($test['results']);
                            if ($has_result) $with_results++;
                            $row_class = $status;
                        ?>
                            <tr class="test-row <?= $row_class ?>" data-id="<?= $test['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($test['test_name']) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($test['patient_name']) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($test['patient_id'] ?? 'N/A') ?></div>
                                </td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= $status ?>">
                                        <?php if ($status === 'pending'): ?>
                                            ⏳ Pending
                                        <?php elseif ($status === 'in_progress'): ?>
                                            🔬 In Progress
                                        <?php elseif ($status === 'completed'): ?>
                                            ✅ Completed
                                        <?php else: ?>
                                            <?= ucfirst($status) ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($has_result): ?>
                                        <div class="result-cell"><?= nl2br(htmlspecialchars($test['results'])) ?></div>
                                    <?php else: ?>
                                        <div class="result-cell empty">No result yet</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($test['lab_technician_name']): ?>
                                        <span class="text-xs"><?= htmlspecialchars($test['lab_technician_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-xs">
                                    <?= date('M d, Y', strtotime($test['created_at'])) ?>
                                    <?php if ($test['completed_at']): ?>
                                        <br><span class="text-green-600">✓ <?= date('h:i A', strtotime($test['completed_at'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="view_lab_result.php?id=<?= $test['id'] ?>" class="btn btn-blue btn-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($has_result): ?>
                                            <a href="view_lab_result.php?id=<?= $test['id'] ?>&print=1" class="btn btn-outline btn-sm" title="Print" style="border-color:#059669;color:#059669;">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-flask" style="font-size: 3rem;"></i>
                                    <p>No lab results found</p>
                                    <p class="text-sm mt-1">Tests you have requested will appear here once processed</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Card Footer -->
        <div class="card-footer">
            <span class="text-sm text-gray-500">
                <i class="fas fa-flask mr-1"></i> 
                Showing <strong id="recordCount"><?= count($lab_tests) ?></strong> result(s)
            </span>
            <span class="text-sm text-gray-500">
                <i class="fas fa-check-circle mr-1"></i> 
                With Results: <strong id="withResultsCountFooter"><?= $with_results ?? 0 ?></strong>
            </span>
            <span class="text-sm text-gray-500">
                <i class="fas fa-clock mr-1"></i> 
                <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5">
        <a href="request_lab_test.php" class="card text-center hover:border-blue-500 transition">
            <i class="fas fa-plus-circle text-blue-600 text-2xl block mb-2"></i>
            <span class="text-sm font-medium">Request New Lab Test</span>
            <p class="text-xs text-gray-400">Order tests for patients</p>
        </a>
        <a href="my_patients.php" class="card text-center hover:border-green-500 transition">
            <i class="fas fa-users text-green-600 text-2xl block mb-2"></i>
            <span class="text-sm font-medium">My Patients</span>
            <p class="text-xs text-gray-400">View all your patients</p>
        </a>
        <a href="dashboard.php" class="card text-center hover:border-purple-500 transition">
            <i class="fas fa-chart-bar text-purple-600 text-2xl block mb-2"></i>
            <span class="text-sm font-medium">Dashboard</span>
            <p class="text-xs text-gray-400">Return to dashboard</p>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Lab Results
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
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
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
        var status = '<?= $status_filter ?>';
        var date = '<?= $date_filter ?>';
        var patient = '<?= $patient_filter ?>';
        window.location.href = 'lab_results.php?search=' + encodeURIComponent(query) + '&status=' + status + '&date=' + date + '&patient=' + patient;
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
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

    // ================================================================
    // AUTO-UPDATE (3 SECONDS)
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var lastHash = null;
    
    function fetchAndUpdate() {
        if (isUpdating) return;
        isUpdating = true;
        
        var status = '<?= $status_filter ?>';
        var date = '<?= $date_filter ?>';
        var search = '<?= urlencode($search) ?>';
        var patient = '<?= $patient_filter ?>';
        var url = 'get_lab_results.php?status=' + status + '&date=' + date + '&search=' + search + '&patient=' + patient + '&t=' + new Date().getTime();
        
        fetch(url)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    // Check if data has changed
                    if (lastHash !== data.hash) {
                        lastHash = data.hash;
                        updateTable(data);
                        
                        // Update stats
                        document.getElementById('statPending').textContent = data.pending_count || 0;
                        document.getElementById('statInProgress').textContent = data.in_progress_count || 0;
                        document.getElementById('statCompleted').textContent = data.completed_count || 0;
                        document.getElementById('statCompletedToday').textContent = data.completed_today_count || 0;
                        document.getElementById('statTotal').textContent = data.total_count || 0;
                        
                        // Update counts
                        document.getElementById('resultCount').textContent = '(' + (data.total || 0) + ')';
                        document.getElementById('recordCount').textContent = data.total || 0;
                        document.getElementById('withResultsCount').textContent = data.with_results || 0;
                        document.getElementById('withResultsCountFooter').textContent = data.with_results || 0;
                        
                        // Update timestamp
                        var now = new Date();
                        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
                        document.getElementById('updateBadge').innerHTML = '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + timeStr;
                    }
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Error fetching lab results:', error);
                document.getElementById('updateBadge').innerHTML = '<i class="fas fa-exclamation-circle" style="color:#EF4444;"></i> Error';
                isUpdating = false;
            });
    }
    
    function updateTable(data) {
        var tbody = document.getElementById('resultTableBody');
        var tests = data.tests || [];
        
        if (tests.length > 0) {
            var html = '';
            var i = 1;
            tests.forEach(function(test) {
                var status = test.status || 'pending';
                var hasResult = test.results && test.results.trim() !== '';
                var rowClass = status;
                var statusLabel = status === 'pending' ? '⏳ Pending' : 
                                 status === 'in_progress' ? '🔬 In Progress' : 
                                 status === 'completed' ? '✅ Completed' : 
                                 status || 'Pending';
                var resultDisplay = hasResult ? 
                    `<div class="result-cell">${escapeHtml(test.results).replace(/\n/g, '<br>')}</div>` : 
                    `<div class="result-cell empty">No result yet</div>`;
                var labTech = test.lab_technician_name || '<span class="text-xs text-gray-400">Not assigned</span>';
                var dateDisplay = test.created_at ? formatDate(test.created_at) : 'N/A';
                if (test.completed_at) {
                    dateDisplay += `<br><span class="text-green-600">✓ ${formatTime(test.completed_at)}</span>`;
                }
                
                html += `
                    <tr class="test-row ${rowClass}" data-id="${test.id}">
                        <td>${i++}</td>
                        <td>
                            <div class="font-medium text-sm">${escapeHtml(test.test_name)}</div>
                            <div class="text-xs text-gray-400">${escapeHtml(test.test_type || 'N/A')}</div>
                        </td>
                        <td>
                            <div class="font-medium text-sm">${escapeHtml(test.patient_name)}</div>
                            <div class="text-xs text-gray-400">${escapeHtml(test.patient_id || 'N/A')}</div>
                        </td>
                        <td class="font-mono text-xs">${escapeHtml(test.visit_number || 'N/A')}</td>
                        <td>
                            <span class="status-badge ${status}">
                                ${statusLabel}
                            </span>
                        </td>
                        <td>${resultDisplay}</td>
                        <td>${labTech}</td>
                        <td class="text-xs">${dateDisplay}</td>
                        <td>
                            <div class="flex gap-1">
                                <a href="view_lab_result.php?id=${test.id}" class="btn btn-blue btn-sm" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                ${hasResult ? `
                                    <a href="view_lab_result.php?id=${test.id}&print=1" class="btn btn-outline btn-sm" title="Print" style="border-color:#059669;color:#059669;">
                                        <i class="fas fa-print"></i>
                                    </a>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <i class="fas fa-flask" style="font-size: 3rem;"></i>
                            <p>No lab results found</p>
                            <p class="text-sm mt-1">Tests you have requested will appear here once processed</p>
                        </div>
                    </td>
                </tr>
            `;
        }
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(datetime) {
        if (!datetime) return 'N/A';
        var d = new Date(datetime);
        if (isNaN(d.getTime())) return 'N/A';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }
    
    function formatTime(datetime) {
        if (!datetime) return 'N/A';
        var d = new Date(datetime);
        if (isNaN(d.getTime())) return 'N/A';
        return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        fetchAndUpdate();
        updateInterval = setInterval(fetchAndUpdate, 3000);
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
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });
    
    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
            searchInput?.select();
        }
    });

    // ================================================================
    // START AUTO-UPDATE
    // ================================================================
    startAutoUpdate();

    // ================================================================
    // CONSOLE
    // ================================================================
    console.log('%c🧪 Braick - Lab Results (Auto-Update)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👨‍⚕️ Doctor: <?= htmlspecialchars($doctor_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Pending: <?= $pending_count ?> | In Progress: <?= $in_progress_count ?> | Completed: <?= $completed_count ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔄 Auto-update every 3 seconds', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>