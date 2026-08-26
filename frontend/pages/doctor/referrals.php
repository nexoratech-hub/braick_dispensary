<?php
// ================================================================
// FILE: frontend/pages/doctor/referrals.php
// DOCTOR - REFERRALS MANAGEMENT
// Session-based login (NO BYPASS)
// BRAICK DISPENSARY - USING dispensary_db
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT DOCTOR
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET DOCTOR DATA FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_online = $_SESSION['is_online'] ?? 0;

// ================================================================
// INCLUDE DATABASE - USING dispensary_db
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// VERIFY DOCTOR EXISTS AND IS ACTIVE
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, specialty, profile_pic, status, is_online FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor_data || $doctor_data['status'] !== 'active') {
        session_destroy();
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
    
    $doctor_name = $doctor_data['full_name'];
    $doctor_branch_id = $doctor_data['branch_id'] ?? 1;
    $doctor_specialty = $doctor_data['specialty'] ?? 'General Medicine';
    $profile_pic = $doctor_data['profile_pic'] ?? '';
    $is_online = $doctor_data['is_online'] ?? 0;
    
    $_SESSION['full_name'] = $doctor_name;
    $_SESSION['branch_id'] = $doctor_branch_id;
    $_SESSION['specialty'] = $doctor_specialty;
    $_SESSION['profile_pic'] = $profile_pic;
    $_SESSION['is_online'] = $is_online;
    
} catch (Exception $e) {
    error_log("referrals verification error: " . $e->getMessage());
}

// ================================================================
// GET REFERRALS FOR THIS DOCTOR - USING dispensary_db
// ================================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "
    SELECT 
        r.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone as patient_phone,
        p.gender as patient_gender,
        p.date_of_birth as patient_dob,
        u_from.full_name as from_doctor_name,
        u_from.specialty as from_doctor_specialty,
        u_to.full_name as to_doctor_name,
        u_to.specialty as to_doctor_specialty,
        v.visit_number,
        v.diagnosis as visit_diagnosis,
        v.status as visit_status
    FROM referrals r
    LEFT JOIN patients p ON r.patient_id = p.id
    LEFT JOIN visits v ON r.visit_id = v.id
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

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Referrals fetch error: " . $e->getMessage());
    $referrals = [];
}

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

function getReferralTypeLabel($type) {
    if ($type === 'internal') {
        return '🏥 Internal';
    } else {
        return '🌍 External';
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
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
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
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user-md mr-1"></i> Dr. <?= htmlspecialchars($doctor_name) ?>
                </span>
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
                        <th>Type</th>
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
                                    <div class="text-xs text-muted">📞 <?= htmlspecialchars($ref['patient_phone'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <span class="font-mono text-xs"><?= htmlspecialchars($ref['visit_number'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $ref['referral_type'] === 'internal' ? 'badge-info' : 'badge-success' ?>">
                                        <?= getReferralTypeLabel($ref['referral_type']) ?>
                                    </span>
                                    <?php if ($ref['referral_type'] === 'external' && !empty($ref['to_hospital_name'])): ?>
                                        <div class="text-xs text-muted">🏥 <?= htmlspecialchars($ref['to_hospital_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ref['from_doctor_id'] == $doctor_id): ?>
                                        <span class="text-green-600 font-medium">Me</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($ref['from_doctor_name'] ?? 'Unknown') ?>
                                        <?php if (!empty($ref['from_doctor_specialty'])): ?>
                                            <div class="text-xs text-muted"><?= htmlspecialchars($ref['from_doctor_specialty']) ?></div>
                                        <?php endif; ?>
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
                                    <?php if ($ref['referral_type'] === 'external' && !empty($ref['to_hospital_name'])): ?>
                                        <div class="text-xs text-muted">🏥 <?= htmlspecialchars($ref['to_hospital_name']) ?></div>
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
                            <td colspan="10" class="text-center py-8 text-muted">
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
            Dr. <?= htmlspecialchars($doctor_name) ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
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
       ROOT VARIABLES
       ================================================================ */
    :root {
        --primary: #0B5ED7;
        --primary-dark: #0A4CA8;
        --primary-light: #6EA8FE;
        --primary-bg: #E8F0FE;
        --success: #059669;
        --success-bg: #D1FAE5;
        --danger: #DC2626;
        --danger-bg: #FEE2E2;
        --warning: #D97706;
        --warning-bg: #FEF3C7;
        --purple: #7C3AED;
        --purple-bg: #EDE9FE;
        --white: #FFFFFF;
        --gray-50: #F8FAFC;
        --gray-100: #F1F5F9;
        --gray-200: #E2E8F0;
        --gray-300: #CBD5E1;
        --gray-400: #94A3B8;
        --gray-500: #64748B;
        --gray-600: #475569;
        --gray-700: #334155;
        --gray-800: #1E293B;
        --gray-900: #0F172A;
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --text-primary: #1E293B;
        --text-secondary: #64748B;
        --border-color: #E2E8F0;
        --shadow: 0 1px 3px rgba(0,0,0,0.08);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.07);
        --shadow-lg: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --border-color: #334155;
        --shadow: 0 1px 3px rgba(0,0,0,0.3);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --shadow-lg: 0 8px 25px rgba(0,0,0,0.4);
    }
    
    /* ================================================================
       MAIN CONTENT
       ================================================================ */
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 24px 28px;
        min-height: calc(100vh - 68px);
        transition: all 0.3s ease;
        background: var(--bg-body);
    }
    
    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 3px solid var(--primary);
    }
    
    .page-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    
    .page-title i { color: var(--primary); }
    
    .page-subtitle {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin-top: 4px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
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
    
    .ml-2 { margin-left: 8px; }
    .mr-1 { margin-right: 4px; }
    .mr-2 { margin-right: 8px; }
    .mx-2 { margin-left: 8px; margin-right: 8px; }
    
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
    
    [data-theme="dark"] .card {
        background: #1E293B;
        border-color: #334155;
    }
    
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
        color: var(--text-secondary);
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
        color: var(--text-secondary);
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
    
    [data-theme="dark"] .filter-search {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .filter-input {
        color: #F1F5F9;
    }
    [data-theme="dark"] .filter-select {
        background: #1E293B;
        border-color: #334155;
        color: #F1F5F9;
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
    
    .data-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .data-table thead th:last-child {
        border-radius: 0 8px 0 0;
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
    
    [data-theme="dark"] .data-table tbody tr:nth-child(even) {
        background: #1E293B;
    }
    [data-theme="dark"] .data-table tbody tr:nth-child(odd) {
        background: #1E293B;
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
    .data-table td .text-muted { color: var(--text-secondary); }
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
        min-height: 36px;
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
        min-height: 30px;
    }
    
    .action-buttons {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: nowrap;
        justify-content: center;
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
    
    [data-theme="dark"] .footer {
        border-color: #334155;
        color: #94A3B8;
    }
    
    /* ================================================================
       TOAST
       ================================================================ */
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 12px 18px;
        border-radius: 12px;
        z-index: 999;
        max-width: 360px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #EF4444; }
    .toast-custom.info { background: var(--primary); }
    .toast-custom.warning { background: #D97706; }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .main-content { padding: 16px; }
    }
    
    @media (max-width: 768px) {
        .main-content { padding: 12px; }
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .card { padding: 14px 16px; }
        .filter-group { flex-direction: column; align-items: stretch; }
        .filter-search { min-width: 100%; }
        .filter-select { width: 100%; min-width: 100%; }
        .stat-card { padding: 14px 16px; }
        .stat-card .stat-number { font-size: 1.2rem; }
        .action-buttons { flex-wrap: wrap; justify-content: center; }
        .data-table { font-size: 0.75rem; }
        .data-table th, .data-table td { padding: 6px 10px; }
        .btn-sm { padding: 3px 8px; font-size: 0.6rem; }
        .page-title { font-size: 1.2rem; }
        .filter-form .btn { width: 100%; justify-content: center; }
        .page-header { flex-direction: column; }
        .page-header .btn { width: 100%; justify-content: center; }
    }
    
    @media (max-width: 480px) {
        .main-content { padding: 8px; }
        .stats-grid { grid-template-columns: 1fr; }
        .data-table th, .data-table td { padding: 4px 6px; font-size: 0.7rem; }
        .btn-sm { padding: 2px 6px; font-size: 0.55rem; }
        .action-buttons { gap: 3px; }
        .page-title { font-size: 1rem; }
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

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🔄 Referrals - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Session-based login active', 'font-size:12px; color:#34D399;');
    console.log('%c📊 Total: <?= $total_referrals ?> | Pending: <?= $pending_count ?> | Accepted: <?= $accepted_count ?> | Completed: <?= $completed_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c✅ Using dispensary_db database', 'font-size:12px; color:#0B5ED7;');
    console.log('%c👨‍⚕️ Doctor: Dr. <?= htmlspecialchars($doctor_name) ?>', 'font-size:12px; color:#64748B;');
    console.log('%c📋 Referral count: <?= $total_referrals ?>', 'font-size:12px; color:#64748B;');
</script>

</body>
</html>