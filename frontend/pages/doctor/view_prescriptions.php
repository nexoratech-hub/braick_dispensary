<?php
// ================================================================
// FILE: frontend/pages/doctor/view_prescriptions.php
// DOCTOR - VIEW PRESCRIPTIONS (BLUE & GREEN CARDS, NO EDIT)
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
// GET ALL PRESCRIPTIONS FOR THIS DOCTOR
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$sql = "
    SELECT 
        pr.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone as patient_phone,
        v.visit_number,
        (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = pr.id) as item_count
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id = p.id
    LEFT JOIN visits v ON pr.visit_id = v.id
    WHERE pr.doctor_id = ?
";

$params = [$doctor_id];

if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR pr.prescription_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $sql .= " AND pr.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY pr.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$total_prescriptions = count($prescriptions);
$pending_count = 0;
$dispensed_count = 0;
$cancelled_count = 0;

foreach ($prescriptions as $pr) {
    if ($pr['status'] === 'pending') {
        $pending_count++;
    } elseif ($pr['status'] === 'dispensed') {
        $dispensed_count++;
    } elseif ($pr['status'] === 'cancelled') {
        $cancelled_count++;
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
        case 'dispensed': return 'badge-success';
        case 'cancelled': return 'badge-danger';
        case 'pending': return 'badge-warning';
        default: return 'badge-info';
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
$pending_prescriptions = $pending_count;

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

    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription mr-2" style="color: #0B5ED7;"></i> My Prescriptions
            </h1>
            <p class="page-subtitle">
                View all prescriptions you have created
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-list mr-1"></i> <?= $total_prescriptions ?> prescriptions
                </span>
                <?php if ($pending_count > 0): ?>
                    <span class="ml-2 inline-flex bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs border border-yellow-200">
                        <i class="fas fa-clock mr-1"></i> <?= $pending_count ?> pending
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <a href="prescribe.php" class="btn btn-blue btn-sm">
                <i class="fas fa-plus"></i> New Prescription
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS - 2 BLUE + 2 GREEN -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        
        <!-- CARD 1: BLUE - Total Prescriptions -->
        <div class="stat-card stat-card-blue">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-prescription"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Total Prescriptions</span>
                        <span class="stat-card-number"><?= $total_prescriptions ?></span>
                        <span class="stat-card-trend">
                            <i class="fas fa-prescription"></i> All time
                        </span>
                    </div>
                </div>
            </div>
            <div class="stat-card-progress" style="width: <?= $total_prescriptions > 0 ? min(100, ($total_prescriptions / 50) * 100) : 0 ?>%;"></div>
        </div>

        <!-- CARD 2: BLUE - Dispensed -->
        <div class="stat-card stat-card-blue">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Dispensed</span>
                        <span class="stat-card-number"><?= $dispensed_count ?></span>
                        <span class="stat-card-trend">
                            <i class="fas fa-check-circle"></i> Completed
                        </span>
                    </div>
                </div>
            </div>
            <div class="stat-card-progress" style="width: <?= $total_prescriptions > 0 ? min(100, ($dispensed_count / $total_prescriptions) * 100) : 0 ?>%;"></div>
        </div>

        <!-- CARD 3: GREEN - Pending -->
        <div class="stat-card stat-card-green <?= $pending_count > 0 ? 'has-badge' : '' ?>">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Pending</span>
                        <span class="stat-card-number <?= $pending_count > 0 ? 'text-orange' : '' ?>">
                            <?= $pending_count ?>
                        </span>
                        <span class="stat-card-trend">
                            <?php if ($pending_count > 0): ?>
                                <i class="fas fa-clock"></i> Waiting for pharmacy
                            <?php else: ?>
                                <i class="fas fa-check-circle"></i> All dispensed
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <?php if ($pending_count > 0): ?>
                    <div class="stat-card-right">
                        <span class="stat-card-badge danger"><?= $pending_count ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="stat-card-progress" style="width: <?= $total_prescriptions > 0 ? min(100, ($pending_count / $total_prescriptions) * 100) : 0 ?>%; background: #059669;"></div>
        </div>

        <!-- CARD 4: GREEN - Cancelled -->
        <div class="stat-card stat-card-green <?= $cancelled_count > 0 ? 'has-badge' : '' ?>">
            <div class="stat-card-inner">
                <div class="stat-card-left">
                    <div class="stat-card-icon"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-card-info">
                        <span class="stat-card-label">Cancelled</span>
                        <span class="stat-card-number <?= $cancelled_count > 0 ? 'text-red' : '' ?>">
                            <?= $cancelled_count ?>
                        </span>
                        <span class="stat-card-trend">
                            <?php if ($cancelled_count > 0): ?>
                                <i class="fas fa-times-circle"></i> Cancelled
                            <?php else: ?>
                                <i class="fas fa-check-circle"></i> No cancellations
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <?php if ($cancelled_count > 0): ?>
                    <div class="stat-card-right">
                        <span class="stat-card-badge danger"><?= $cancelled_count ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="stat-card-progress" style="width: <?= $total_prescriptions > 0 ? min(100, ($cancelled_count / $total_prescriptions) * 100) : 0 ?>%; background: #059669;"></div>
        </div>

    </div>

    <!-- Search & Filter -->
    <div class="card mb-6">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" class="form-control" placeholder="Search by patient, prescription #..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="status" class="form-control w-auto min-w-[120px]">
                <option value="">All Status</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="dispensed" <?= $status_filter === 'dispensed' ? 'selected' : '' ?>>Dispensed</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <button type="submit" class="btn btn-blue btn-sm">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if ($search || $status_filter): ?>
                <a href="view_prescriptions.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Prescriptions Table -->
    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Prescription #</th>
                        <th>Patient</th>
                        <th>Visit</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="border-radius: 0 8px 0 0;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($prescriptions) > 0): ?>
                        <?php foreach ($prescriptions as $index => $pr): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <span class="font-mono text-sm font-semibold text-blue-600">
                                        <?= htmlspecialchars($pr['prescription_number']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="font-medium"><?= htmlspecialchars($pr['patient_name']) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($pr['patient_code'] ?? '') ?></div>
                                </td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($pr['visit_number'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-info"><?= $pr['item_count'] ?? 0 ?> item(s)</span>
                                </td>
                                <td>
                                    <span class="badge <?= getStatusBadgeClass($pr['status']) ?>">
                                        <?= ucfirst($pr['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($pr['created_at'])) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_prescription.php?id=<?= $pr['id'] ?>" class="btn btn-view btn-sm" title="View Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400">
                                <i class="fas fa-prescription text-3xl block mb-2"></i>
                                <?php if ($search): ?>
                                    No prescriptions found matching "<strong><?= htmlspecialchars($search) ?></strong>"
                                <?php else: ?>
                                    No prescriptions found. Click "New Prescription" to create one.
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
            My Prescriptions
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
<!-- STYLES - BLUE & GREEN THEME -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       STATS GRID - 4 CARDS
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 22px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.08);
    }
    
    .stat-card-inner {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    
    .stat-card-left {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        flex: 1;
    }
    
    .stat-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: white;
        flex-shrink: 0;
    }
    
    .stat-card-blue .stat-card-icon { background: linear-gradient(135deg, #0B5ED7, #1A73E8); }
    .stat-card-green .stat-card-icon { background: linear-gradient(135deg, #059669, #0AA84F); }
    
    .stat-card-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
        flex: 1;
    }
    
    .stat-card-label {
        font-size: 0.75rem;
        font-weight: 500;
        color: #94A3B8;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    
    .stat-card-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #1E293B;
        line-height: 1.2;
    }
    
    .stat-card-number.text-orange { color: #D97706; }
    .stat-card-number.text-red { color: #EF4444; }
    
    .stat-card-trend {
        font-size: 0.65rem;
        color: #94A3B8;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .stat-card-trend .fa-prescription { color: #0B5ED7; }
    .stat-card-trend .fa-check-circle { color: #059669; }
    .stat-card-trend .fa-clock { color: #D97706; }
    .stat-card-trend .fa-times-circle { color: #EF4444; }
    
    .stat-card-right {
        display: flex;
        align-items: flex-start;
        flex-shrink: 0;
    }
    
    .stat-card-badge {
        font-size: 0.65rem;
        font-weight: 700;
        color: white;
        background: #0B5ED7;
        padding: 2px 12px;
        border-radius: 20px;
        min-width: 24px;
        text-align: center;
    }
    
    .stat-card-badge.danger {
        background: #EF4444;
        animation: pulse-badge 2s infinite;
    }
    
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .stat-card-progress {
        height: 3px;
        background: #0B5ED7;
        border-radius: 0 0 16px 16px;
        position: absolute;
        bottom: 0;
        left: 0;
        transition: width 1s ease;
        opacity: 0.3;
    }
    
    .stat-card-green .stat-card-progress { background: #059669; }
    
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
    
    .table-wrap { overflow-x: auto; }
    
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
        color: white;
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
    
    .data-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: white;
        border: none;
    }
    
    .badge-success { background: #059669; }
    .badge-danger { background: #EF4444; }
    .badge-warning { background: #D97706; }
    .badge-info { background: var(--primary); }
    
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
        color: white;
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
        color: white;
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
    
    .form-control {
        width: 100%;
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s;
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.6;
    }
    
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
    
    .text-xs { font-size: 0.75rem; }
    .text-sm { font-size: 0.875rem; }
    .text-gray-400 { color: var(--text-muted); }
    .font-mono { font-family: monospace; }
    .font-medium { font-weight: 500; }
    .font-semibold { font-weight: 600; }
    .min-w-[120px] { min-width: 120px; }
    .min-w-[200px] { min-width: 200px; }
    .w-auto { width: auto; }
    .flex-1 { flex: 1; }
    .mb-6 { margin-bottom: 1.5rem; }
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
    .text-center { text-align: center; }
    .py-8 { padding-top: 2rem; padding-bottom: 2rem; }
    .text-3xl { font-size: 1.875rem; }
    .block { display: block; }
    .mb-2 { margin-bottom: 0.5rem; }
    
    /* ================================================================
       DARK MODE
       ================================================================ */
    [data-theme="dark"] .stat-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .stat-card-number {
        color: #F1F5F9;
    }
    [data-theme="dark"] .stat-card-label {
        color: #94A3B8;
    }
    [data-theme="dark"] .stat-card-trend {
        color: #64748B;
    }
    [data-theme="dark"] .stat-card:hover {
        box-shadow: 0 12px 40px rgba(0,0,0,0.3);
    }
    [data-theme="dark"] .data-table tbody tr:nth-child(even) { background: #1E293B; }
    [data-theme="dark"] .data-table tbody tr:nth-child(odd) { background: #1E293B; }
    [data-theme="dark"] .data-table tbody tr:hover { background: #1A3A2A; }
    [data-theme="dark"] .card { background: #1E293B; border-color: #334155; }
    [data-theme="dark"] .page-header .page-title { color: #6EA8FE; }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .card { padding: 14px 16px; }
        .data-table { font-size: 0.75rem; }
        .data-table th, .data-table td { padding: 6px 10px; }
        .action-buttons { flex-wrap: wrap; }
        .btn-sm { padding: 3px 8px; font-size: 0.6rem; }
        .page-header .page-title { font-size: 1.2rem; }
        .stat-card { padding: 14px 16px; }
        .stat-card-number { font-size: 1.4rem; }
        .stat-card-icon { width: 40px; height: 40px; font-size: 1rem; }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .stat-card-number { font-size: 1.2rem; }
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
    
    console.log('%c💊 My Prescriptions - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total: <?= $total_prescriptions ?> | Pending: <?= $pending_count ?> | Dispensed: <?= $dispensed_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c🔒 Doctor: View Only - No Edit Button', 'font-size:12px; color:#EF4444;');
</script>

</body>
</html>