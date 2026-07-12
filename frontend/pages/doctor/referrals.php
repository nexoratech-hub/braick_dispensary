<?php
// ================================================================
// FILE: frontend/pages/doctor/referrals.php
// DOCTOR - REFERRALS MANAGEMENT (FIXED QUERY)
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
// GET REFERRALS FOR THIS DOCTOR - FIXED: Use visit_id to get patient
// ================================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all'; // all, sent, received
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "
    SELECT 
        r.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone as patient_phone,
        u_from.full_name as from_doctor_name,
        u_to.full_name as to_doctor_name,
        u_to.specialty as to_doctor_specialty,
        v.visit_number,
        v.diagnosis as visit_diagnosis
    FROM referrals r
    LEFT JOIN visits v ON r.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u_from ON r.from_doctor_id = u_from.id
    LEFT JOIN users u_to ON r.to_doctor_id = u_to.id
    WHERE 1=1
";

$params = [];

if ($type_filter === 'sent') {
    $sql .= " AND r.from_doctor_id = ?";
    $params[] = $doctor_id;
} elseif ($type_filter === 'received') {
    $sql .= " AND r.to_doctor_id = ?";
    $params[] = $doctor_id;
} else {
    // Show both sent and received
    $sql .= " AND (r.from_doctor_id = ? OR r.to_doctor_id = ?)";
    $params[] = $doctor_id;
    $params[] = $doctor_id;
}

if (!empty($status_filter)) {
    $sql .= " AND r.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR u_to.full_name LIKE ? OR v.visit_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$total_referrals = count($referrals);
$pending_count = 0;
$accepted_count = 0;
$completed_count = 0;
$rejected_count = 0;

foreach ($referrals as $ref) {
    switch ($ref['status']) {
        case 'pending': $pending_count++; break;
        case 'accepted': $accepted_count++; break;
        case 'completed': $completed_count++; break;
        case 'rejected': $rejected_count++; break;
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
        case 'accepted': return 'badge-success';
        case 'completed': return 'badge-info';
        case 'rejected': return 'badge-danger';
        case 'pending': return 'badge-warning';
        default: return 'badge-warning';
    }
}

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

// ================================================================
// VARIABLES FOR SIDEBAR
// ================================================================
$selected_branch_id = $doctor_branch_id;
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;
$pending_lab_tests = 0;
$pending_prescriptions = 0;

// ================================================================
// INCLUDE HEADER & SIDEBAR
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
                <i class="fas fa-ambulance mr-2" style="color: #0B5ED7;"></i> Referrals
            </h1>
            <p class="page-subtitle">
                Manage patient referrals
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-list mr-1"></i> <?= $total_referrals ?> referrals
                </span>
                <?php if ($pending_count > 0): ?>
                    <span class="ml-2 inline-flex bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs border border-yellow-200">
                        <i class="fas fa-clock mr-1"></i> <?= $pending_count ?> pending
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <a href="refer_patient.php" class="btn btn-blue btn-sm">
                <i class="fas fa-plus"></i> New Referral
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div>
                <p class="stat-label">Total Referrals</p>
                <p class="stat-number"><?= $total_referrals ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-ambulance"></i></div>
        </div>
        <div class="stat-card yellow">
            <div>
                <p class="stat-label">Pending</p>
                <p class="stat-number"><?= $pending_count ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
        <div class="stat-card green">
            <div>
                <p class="stat-label">Accepted</p>
                <p class="stat-number"><?= $accepted_count ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </div>
        <div class="stat-card purple">
            <div>
                <p class="stat-label">Completed</p>
                <p class="stat-number"><?= $completed_count ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="card mb-6">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <div class="filter-search">
                    <i class="fas fa-search text-muted"></i>
                    <input type="text" name="search" class="filter-input" placeholder="Search by patient, visit, doctor..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="type" class="filter-select">
                    <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>All Referrals</option>
                    <option value="sent" <?= $type_filter === 'sent' ? 'selected' : '' ?>>Sent by Me</option>
                    <option value="received" <?= $type_filter === 'received' ? 'selected' : '' ?>>Received by Me</option>
                </select>
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="accepted" <?= $status_filter === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
                <button type="submit" class="btn btn-blue btn-sm">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if ($search || $status_filter || $type_filter !== 'all'): ?>
                    <a href="referrals.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Referrals Table -->
    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Patient</th>
                        <th>Visit</th>
                        <th>From Doctor</th>
                        <th>To Doctor</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="border-radius: 0 8px 0 0; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($referrals) > 0): ?>
                        <?php foreach ($referrals as $index => $ref): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div class="font-medium"><?= htmlspecialchars($ref['patient_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($ref['patient_code'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span class="font-mono text-xs"><?= htmlspecialchars($ref['visit_number'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <?php if ($ref['from_doctor_id'] == $doctor_id): ?>
                                        <span class="text-green-600 font-medium">Me</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($ref['from_doctor_name'] ?? 'Unknown') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ref['to_doctor_id'] == $doctor_id): ?>
                                        <span class="text-blue-600 font-medium">Me</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($ref['to_doctor_name'] ?? 'Unknown') ?>
                                    <?php endif; ?>
                                    <?php if (!empty($ref['to_doctor_specialty'])): ?>
                                        <div class="text-xs text-muted"><?= htmlspecialchars($ref['to_doctor_specialty']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-sm"><?= htmlspecialchars(substr($ref['reason'] ?? '', 0, 50)) ?><?= strlen($ref['reason'] ?? '') > 50 ? '...' : '' ?></td>
                                <td>
                                    <span class="badge <?= getStatusBadgeClass($ref['status']) ?>">
                                        <?= ucfirst($ref['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-sm"><?= time_ago($ref['created_at'] ?? '') ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_referral.php?id=<?= $ref['id'] ?>" class="btn btn-view btn-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (($ref['status'] ?? '') === 'pending' && $ref['to_doctor_id'] == $doctor_id): ?>
                                            <a href="accept_referral.php?id=<?= $ref['id'] ?>" class="btn btn-success btn-sm" title="Accept" onclick="return confirm('Accept this referral?')">
                                                <i class="fas fa-check"></i> Accept
                                            </a>
                                            <a href="reject_referral.php?id=<?= $ref['id'] ?>" class="btn btn-danger btn-sm" title="Reject" onclick="return confirm('Reject this referral?')">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-8 text-muted">
                                <i class="fas fa-ambulance text-3xl block mb-2"></i>
                                <?php if ($search || $status_filter): ?>
                                    No referrals found matching your filters
                                <?php else: ?>
                                    No referrals found. Click "New Referral" to create one.
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
            Referrals
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
    .stat-card.yellow .stat-icon { background: #D97706; }
    .stat-card.green .stat-icon { background: #059669; }
    .stat-card.purple .stat-icon { background: #7C3AED; }
    
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
    
    .data-table td .font-medium { font-weight: 500; }
    .data-table td .font-semibold { font-weight: 600; }
    .data-table td .text-sm { font-size: 0.8rem; }
    .data-table td .text-xs { font-size: 0.7rem; }
    .data-table td .text-muted { color: var(--text-muted); }
    .data-table td .text-green-600 { color: #059669; }
    .data-table td .text-blue-600 { color: var(--primary); }
    .data-table td .font-mono { font-family: monospace; }
    
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
    .badge-warning { background: #D97706; }
    .badge-info { background: var(--primary); }
    
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
    
    .btn-success {
        background: #059669;
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
    .btn-success:hover {
        background: #047857;
        transform: scale(1.05);
    }
    
    .btn-danger {
        background: #EF4444;
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
    .btn-danger:hover {
        background: #DC2626;
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
    .font-medium { font-weight: 500; }
    .font-semibold { font-weight: 600; }
    .font-mono { font-family: monospace; }
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
        .data-table th,
        .data-table td {
            padding: 4px 6px;
            font-size: 0.7rem;
        }
        .btn-sm {
            padding: 2px 6px;
            font-size: 0.55rem;
        }
        .action-buttons {
            gap: 3px;
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

    console.log('%c🔄 Referrals - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total: <?= $total_referrals ?> | Pending: <?= $pending_count ?> | Accepted: <?= $accepted_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c✅ Query fixed: Uses visit_id to get patient data', 'font-size:12px; color:#0B5ED7;');
</script>

</body>
</html>