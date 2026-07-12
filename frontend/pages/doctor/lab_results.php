<?php
// ================================================================
// FILE: frontend/pages/doctor/lab_results.php
// DOCTOR - LAB RESULTS (CLEAN CSS - NO EDIT BUTTON)
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
}

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
$db_path = 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("❌ Database file not found at: " . $db_path);
}
$db = Database::getInstance()->getConnection();

// ================================================================
// GET LAB TESTS FOR THIS DOCTOR
// ================================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT lt.*, 
               p.full_name as patient_name, 
               p.patient_id,
               p.phone as patient_phone,
               u.full_name as doctor_name, 
               u2.full_name as technician_name,
               v.visit_number
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN users u2 ON lt.lab_technician_id = u2.id
        WHERE lt.doctor_id = ?";

$params = [$doctor_id];

// Only add branch filter if column exists
if (columnExists($db, 'lab_tests', 'branch_id')) {
    $sql .= " AND lt.branch_id = ?";
    $params[] = $doctor_branch_id;
}

if (!empty($status_filter)) {
    $sql .= " AND lt.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ? OR v.visit_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY lt.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$total_tests = count($lab_tests);
$pending_tests = 0;
$completed_tests = 0;
$in_progress_tests = 0;
$cancelled_tests = 0;

foreach ($lab_tests as $test) {
    if ($test['status'] === 'completed') {
        $completed_tests++;
    } elseif ($test['status'] === 'in_progress') {
        $in_progress_tests++;
    } elseif ($test['status'] === 'cancelled') {
        $cancelled_tests++;
    } else {
        $pending_tests++;
    }
}

// ================================================================
// COLUMN EXISTS FUNCTION
// ================================================================
function columnExists($db, $table, $column) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM $table LIKE '$column'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

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
// FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'completed': return 'badge-success';
        case 'cancelled': return 'badge-danger';
        case 'in_progress': return 'badge-warning';
        case 'pending': return 'badge-warning';
        default: return 'badge-info';
    }
}

// ================================================================
// VARIABLES FOR SIDEBAR
// ================================================================
$selected_branch_id = $doctor_branch_id;
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;
$pending_lab_tests = $pending_tests;
$pending_prescriptions = 0;

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_header.php';
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_sidebar.php';
?>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask mr-2" style="color: #0B5ED7;"></i> Lab Results
            </h1>
            <p class="page-subtitle">
                View all laboratory test results
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-flask mr-1"></i> <?= $total_tests ?> tests
                </span>
                <span class="ml-2 inline-flex bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-xs border border-orange-200">
                    <i class="fas fa-clock mr-1"></i> <?= $pending_tests + $in_progress_tests ?> pending
                </span>
            </p>
        </div>
        <div>
            <span class="text-sm text-gray-500">
                <i class="fas fa-user-md mr-1"></i>
                <?= htmlspecialchars($doctor_name) ?>
            </span>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div>
                <p class="stat-label">Total Tests</p>
                <p class="stat-number"><?= $total_tests ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-flask"></i></div>
        </div>
        <div class="stat-card orange">
            <div>
                <p class="stat-label">Pending</p>
                <p class="stat-number"><?= $pending_tests ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
        <div class="stat-card purple">
            <div>
                <p class="stat-label">In Progress</p>
                <p class="stat-number"><?= $in_progress_tests ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-spinner"></i></div>
        </div>
        <div class="stat-card green">
            <div>
                <p class="stat-label">Completed</p>
                <p class="stat-number"><?= $completed_tests ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="card mb-6">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <div class="filter-search">
                    <i class="fas fa-search text-muted"></i>
                    <input type="text" name="search" class="filter-input" placeholder="Search by patient, test or visit..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <button type="submit" class="btn btn-blue btn-sm">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if ($search || $status_filter): ?>
                    <a href="lab_results.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Lab Tests Table -->
    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Test Name</th>
                        <th>Patient</th>
                        <th>Visit #</th>
                        <th>Test Date</th>
                        <th>Technician</th>
                        <th>Status</th>
                        <th style="border-radius: 0 8px 0 0; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($lab_tests) > 0): ?>
                        <?php foreach ($lab_tests as $index => $test): ?>
                            <tr data-status="<?= $test['status'] ?? 'pending' ?>">
                                <td><?= $index + 1 ?></td>
                                <td class="font-medium"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td>
                                    <div class="font-medium"><?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($test['patient_id'] ?? 'N/A') ?></div>
                                </td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></td>
                                <td><?= !empty($test['test_date']) ? date('M d, Y', strtotime($test['test_date'])) : 'N/A' ?></td>
                                <td><?= htmlspecialchars($test['technician_name'] ?? 'Not assigned') ?></td>
                                <td>
                                    <span class="badge <?= getStatusBadgeClass($test['status']) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_test.php?id=<?= $test['id'] ?>" class="btn btn-view btn-sm" title="View Results">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-muted">
                                <i class="fas fa-flask text-3xl block mb-2"></i>
                                <?php if ($search): ?>
                                    No tests found matching "<strong><?= htmlspecialchars($search) ?></strong>"
                                <?php else: ?>
                                    No lab tests found for <strong><?= htmlspecialchars($doctor_name) ?></strong>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Lab Results
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
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       STATS GRID
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
    .stat-card.orange .stat-icon { background: #D97706; }
    .stat-card.purple .stat-icon { background: #7C3AED; }
    .stat-card.green .stat-icon { background: #059669; }
    
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
    
    /* ================================================================
       CARD
       ================================================================ */
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .mb-6 { margin-bottom: 1.5rem; }
    
    /* ================================================================
       FILTER FORM
       ================================================================ */
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
        background: var(--bg-card);
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
        color: var(--text-muted);
        font-size: 0.85rem;
    }
    
    .filter-input {
        border: none;
        background: transparent;
        padding: 8px 12px;
        width: 100%;
        font-size: 0.85rem;
        outline: none;
        color: var(--text-primary);
    }
    
    .filter-input::placeholder {
        color: var(--text-muted);
    }
    
    .filter-select {
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-card);
        color: var(--text-primary);
        font-size: 0.85rem;
        outline: none;
        transition: all 0.3s;
        cursor: pointer;
        min-width: 140px;
    }
    
    .filter-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    
    /* ================================================================
       TABLE
       ================================================================ */
    .table-wrap {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    
    .data-table thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #fff;
        background: var(--primary);
        border-bottom: 3px solid var(--primary-dark);
        white-space: nowrap;
    }
    
    .data-table tbody tr:nth-child(even) {
        background: var(--primary-bg);
    }
    
    .data-table tbody tr:nth-child(odd) {
        background: var(--bg-card);
    }
    
    .data-table tbody tr:hover {
        background: #D1FAE5;
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
    
    /* ================================================================
       BADGES
       ================================================================ */
    .badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #fff;
        border: none;
    }
    
    .badge-success { background: #059669; }
    .badge-danger { background: #EF4444; }
    .badge-info { background: var(--primary); }
    .badge-warning { background: #D97706; }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.78rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-blue {
        background: var(--primary);
        color: #fff;
    }
    .btn-blue:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
    }
    
    .btn-view {
        background: var(--primary);
        color: #fff;
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: none;
        cursor: pointer;
    }
    .btn-view:hover {
        background: var(--primary-dark);
        transform: scale(1.05);
    }
    
    .btn-sm {
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    
    .action-buttons {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: nowrap;
        justify-content: center;
    }
    
    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header {
        border-bottom: 3px solid var(--primary);
        padding-bottom: 12px;
    }
    
    .page-header .page-title {
        color: var(--primary-dark);
        font-size: 1.8rem;
        font-weight: 700;
    }
    
    [data-theme="dark"] .page-header .page-title {
        color: var(--primary-light);
    }
    
    .page-header .page-subtitle {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .branch-tag {
        background: #059669;
        color: white;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .footer .footer-brand {
        color: var(--primary);
        font-weight: 600;
    }
    
    /* ================================================================
       UTILITIES
       ================================================================ */
    .text-xs { font-size: 0.75rem; }
    .text-sm { font-size: 0.875rem; }
    .text-muted { color: var(--text-muted); }
    .font-mono { font-family: monospace; }
    .font-medium { font-weight: 500; }
    .font-semibold { font-weight: 600; }
    .text-center { text-align: center; }
    .py-8 { padding-top: 2rem; padding-bottom: 2rem; }
    .text-3xl { font-size: 1.875rem; }
    .block { display: block; }
    .mb-2 { margin-bottom: 0.5rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-1 { margin-right: 0.25rem; }
    .mr-2 { margin-right: 0.5rem; }
    .gap-2 { gap: 0.5rem; }
    .gap-3 { gap: 0.75rem; }
    .gap-4 { gap: 1rem; }
    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .w-full { width: 100%; }
    .min-w-\[140px\] { min-width: 140px; }
    .min-w-\[200px\] { min-width: 200px; }
    
    /* ================================================================
       DARK MODE
       ================================================================ */
    [data-theme="dark"] .stat-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .stat-card .stat-number {
        color: #F1F5F9;
    }
    [data-theme="dark"] .stat-card .stat-label {
        color: #94A3B8;
    }
    [data-theme="dark"] .card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .data-table tbody tr:nth-child(even) {
        background: #1E293B;
    }
    [data-theme="dark"] .data-table tbody tr:nth-child(odd) {
        background: #1E293B;
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .card {
            padding: 14px 16px;
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
            min-width: 100%;
        }
        .stat-card {
            padding: 14px 16px;
        }
        .stat-card .stat-number {
            font-size: 1.2rem;
        }
        .action-buttons {
            flex-wrap: wrap;
            justify-content: center;
        }
        .data-table {
            font-size: 0.75rem;
        }
        .data-table th,
        .data-table td {
            padding: 6px 10px;
        }
        .btn-sm {
            padding: 3px 8px;
            font-size: 0.6rem;
        }
        .page-header .page-title {
            font-size: 1.2rem;
        }
        .filter-form .btn {
            width: 100%;
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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
    
    console.log('%c🧪 Lab Results - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Tests: <?= $total_tests ?>', 'font-size:12px; color:#059669;');
    console.log('%c⏳ Pending: <?= $pending_tests ?>', 'font-size:12px; color:#D97706;');
    console.log('%c✅ Completed: <?= $completed_tests ?>', 'font-size:12px; color:#059669;');
    console.log('%c🔬 In Progress: <?= $in_progress_tests ?>', 'font-size:12px; color:#7C3AED;');
    console.log('%c🔒 Doctor: View Only - No Edit Button', 'font-size:12px; color:#EF4444;');
</script>

</body>
</html>