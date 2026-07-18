<?php
// ================================================================
// FILE: frontend/pages/doctor/appointments.php
// DOCTOR - APPOINTMENTS MANAGEMENT
// WITH DOCTOR GLOBAL STATS AUTO-UPDATE
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
    $_SESSION['username'] = 'dr.john';
    $_SESSION['email'] = 'john@braick.com';
    $_SESSION['phone'] = '+255 700 000 011';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'General Medicine';
    $_SESSION['profile_pic'] = '';
    $_SESSION['is_online'] = 1;
}

$doctor_id = $_SESSION['user_id'] ?? 5;
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';

// ================================================================
// GET PARAMETERS
// ================================================================
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET APPOINTMENTS FOR THIS DOCTOR
// ================================================================
$appointments = [];
$total_appointments = 0;
$scheduled_count = 0;
$confirmed_count = 0;
$completed_count = 0;
$cancelled_count = 0;

try {
    $sql = "
        SELECT 
            a.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            p.email as patient_email,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            r.full_name as created_by_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        LEFT JOIN users r ON a.created_by = r.id
        WHERE a.doctor_id = ?
    ";

    $params = [$doctor_id];

    if (!empty($status_filter)) {
        $sql .= " AND a.status = ?";
        $params[] = $status_filter;
    }

    if (!empty($date_filter)) {
        $sql .= " AND DATE(a.appointment_date) = ?";
        $params[] = $date_filter;
    }

    if (!empty($search)) {
        $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY a.appointment_date ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_appointments = count($appointments);

    // Count statuses
    foreach ($appointments as $appt) {
        switch ($appt['status']) {
            case 'scheduled': $scheduled_count++; break;
            case 'confirmed': $confirmed_count++; break;
            case 'completed': $completed_count++; break;
            case 'cancelled': $cancelled_count++; break;
            default: $scheduled_count++;
        }
    }
} catch (Exception $e) {
    error_log("Appointments error: " . $e->getMessage());
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
        case 'confirmed': return 'badge-info';
        case 'cancelled': return 'badge-danger';
        case 'scheduled': return 'badge-warning';
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
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">
                <i class="fas fa-calendar-check"></i> Appointments
                <span class="page-badge"><?= $total_appointments ?> total</span>
            </h1>
            <p class="page-subtitle">
                Manage your appointments
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-list mr-1"></i> <?= $total_appointments ?> appointments
                </span>
                <?php if ($scheduled_count > 0): ?>
                    <span class="ml-2 inline-flex bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs border border-yellow-200">
                        <i class="fas fa-clock mr-1"></i> <?= $scheduled_count ?> scheduled
                    </span>
                <?php endif; ?>
                <span class="update-badge ml-2" id="lastUpdateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Starting...
                </span>
            </p>
        </div>
        <div class="page-header-right">
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="appointment.php?patient_id=0" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Appointment
            </a>
            <button onclick="window.location.href='appointments.php'" class="btn btn-outline">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div>
                <p class="stat-label">Total</p>
                <p class="stat-number" id="statTotal"><?= $total_appointments ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        </div>
        <div class="stat-card yellow">
            <div>
                <p class="stat-label">Scheduled</p>
                <p class="stat-number" id="statScheduled"><?= $scheduled_count ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
        <div class="stat-card green">
            <div>
                <p class="stat-label">Confirmed</p>
                <p class="stat-number" id="statConfirmed"><?= $confirmed_count ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </div>
        <div class="stat-card purple">
            <div>
                <p class="stat-label">Completed</p>
                <p class="stat-number" id="statCompleted"><?= $completed_count ?></p>
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
                    <input type="text" name="search" class="filter-input" placeholder="Search by patient..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <input type="date" name="date" class="filter-date" value="<?= htmlspecialchars($date_filter) ?>" placeholder="Filter by date">
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="scheduled" <?= $status_filter === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <button type="submit" class="btn btn-blue btn-sm">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if ($search || $status_filter || $date_filter): ?>
                    <a href="appointments.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Appointments Table -->
    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Patient</th>
                        <th>Date & Time</th>
                        <th>Purpose</th>
                        <th>Created By</th>
                        <th>Status</th>
                        <th style="border-radius: 0 8px 0 0; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($appointments) > 0): ?>
                        <?php foreach ($appointments as $index => $appt): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div class="font-medium"><?= htmlspecialchars($appt['patient_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($appt['patient_code'] ?? '') ?></div>
                                    <?php if (!empty($appt['patient_phone'])): ?>
                                        <div class="text-xs text-muted"><?= htmlspecialchars($appt['patient_phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium"><?= date('M d, Y', strtotime($appt['appointment_date'])) ?></div>
                                    <div class="text-xs text-muted"><?= date('h:i A', strtotime($appt['appointment_date'])) ?></div>
                                </td>
                                <td class="text-sm"><?= htmlspecialchars(substr($appt['purpose'] ?? '', 0, 40)) ?><?= strlen($appt['purpose'] ?? '') > 40 ? '...' : '' ?></td>
                                <td class="text-sm"><?= htmlspecialchars($appt['created_by_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge <?= getStatusBadgeClass($appt['status']) ?>">
                                        <?= ucfirst($appt['status'] ?? 'Scheduled') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-view btn-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (($appt['status'] ?? '') === 'scheduled' || ($appt['status'] ?? '') === 'pending'): ?>
                                            <a href="confirm_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-success btn-sm" title="Confirm" onclick="return confirm('Confirm this appointment?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="cancel_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-danger btn-sm" title="Cancel" onclick="return confirm('Cancel this appointment?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (($appt['status'] ?? '') === 'confirmed'): ?>
                                            <a href="complete_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-success btn-sm" title="Complete" onclick="return confirm('Mark this appointment as completed?')">
                                                <i class="fas fa-check-double"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-muted">
                                <i class="fas fa-calendar-check text-3xl block mb-2"></i>
                                <?php if ($search || $status_filter || $date_filter): ?>
                                    No appointments found matching your filters
                                <?php else: ?>
                                    No appointments scheduled. Click "New Appointment" to create one.
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
            <span class="separator">|</span>
            Appointments
            <span class="separator">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
            <span class="separator">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="separator">|</span>
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
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 24px 28px;
        min-height: calc(100vh - 68px);
        background: var(--bg-body);
        color: var(--text-primary);
        transition: all 0.3s ease;
    }
    
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
    
    .page-header-left { flex: 1; }
    .page-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .page-title i { color: var(--primary); }
    .page-badge {
        font-size: 0.7rem;
        font-weight: 600;
        background: var(--primary-bg);
        color: var(--primary);
        padding: 2px 14px;
        border-radius: 20px;
    }
    .page-subtitle {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin-top: 4px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    .separator { color: var(--border-color); margin: 0 4px; }
    .ml-2 { margin-left: 8px; }
    
    .page-header-right {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .update-badge {
        background: rgba(255,255,255,0.1);
        color: #93C5FD;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .update-badge .fa-spin { animation: fa-spin 2s infinite linear; }
    @keyframes fa-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    
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
    .mb-6 { margin-bottom: 24px; }
    
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
    .filter-search .fa-search { color: var(--text-muted); font-size: 0.85rem; }
    .filter-input {
        border: none;
        background: transparent;
        padding: 8px 12px;
        width: 100%;
        font-size: 0.85rem;
        outline: none;
        color: var(--text-primary);
    }
    .filter-input::placeholder { color: var(--text-muted); }
    
    .filter-date {
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-card);
        color: var(--text-primary);
        font-size: 0.85rem;
        outline: none;
        transition: all 0.3s;
        cursor: pointer;
        min-width: 160px;
    }
    .filter-date:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
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
    .btn-primary {
        background: var(--primary);
        color: white;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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
    .btn-success {
        background: #059669;
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
    .btn-success:hover {
        background: #047857;
        transform: scale(1.05);
    }
    .btn-danger {
        background: #EF4444;
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
    .btn-danger:hover {
        background: #DC2626;
        transform: scale(1.05);
    }
    .btn-sm {
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
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
    .data-table thead th:first-child { border-radius: 8px 0 0 0; }
    .data-table thead th:last-child { border-radius: 0 8px 0 0; }
    
    .data-table tbody tr:nth-child(even) { background: var(--primary-bg); }
    .data-table tbody tr:nth-child(odd) { background: var(--bg-card); }
    .data-table tbody tr:hover { background: #D1FAE5; }
    [data-theme="dark"] .data-table tbody tr:hover { background: #1A3A2A; }
    
    .data-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    .data-table td .font-medium { font-weight: 500; }
    .data-table td .text-sm { font-size: 0.8rem; }
    .data-table td .text-xs { font-size: 0.7rem; }
    .data-table td .text-muted { color: var(--text-muted); }
    
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
    
    .action-buttons {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: nowrap;
        justify-content: center;
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
    
    .text-muted { color: var(--text-muted); }
    .text-center { text-align: center; }
    .py-8 { padding-top: 2rem; padding-bottom: 2rem; }
    .text-3xl { font-size: 1.875rem; }
    .block { display: block; }
    .mb-2 { margin-bottom: 0.5rem; }
    
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
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: var(--primary); font-weight: 600; }
    
    @media (max-width: 1024px) {
        .main-content { padding: 16px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
    
    @media (max-width: 768px) {
        .main-content { margin-left: 0; padding: 12px; }
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .page-header { flex-direction: column; }
        .page-header-right { width: 100%; }
        .page-header-right .btn { flex: 1; justify-content: center; }
        .card { padding: 14px 16px; }
        .filter-group { flex-direction: column; align-items: stretch; }
        .filter-search { min-width: 100%; }
        .filter-date { width: 100%; min-width: 100%; }
        .filter-select { width: 100%; min-width: 100%; }
        .data-table { font-size: 0.75rem; }
        .data-table th, .data-table td { padding: 6px 10px; }
        .btn-sm { padding: 3px 8px; font-size: 0.6rem; }
        .page-title { font-size: 1.2rem; }
        .filter-form .btn { width: 100%; justify-content: center; }
        .stat-card { padding: 14px 16px; }
        .stat-card .stat-number { font-size: 1.2rem; }
        .action-buttons { flex-wrap: wrap; justify-content: center; }
    }
    
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .data-table th, .data-table td { padding: 4px 6px; font-size: 0.7rem; }
        .btn-sm { padding: 2px 6px; font-size: 0.55rem; }
        .action-buttons { gap: 3px; }
        .page-subtitle { flex-direction: column; align-items: flex-start; gap: 4px; }
        .separator { display: none; }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
        .stat-card { border: 1px solid #ddd !important; }
        .filter-form { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // SHOW TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }

    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // ================================================================
    // SHOW TOAST FOR MESSAGES
    // ================================================================
    <?php if ($message && $message_type): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? '✅ Success' : ($message_type === 'warning' ? '⚠️ Notice' : '❌ Error') ?>', 
                '<?= addslashes($message) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    console.log('%c📅 Appointments - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total: <?= $total_appointments ?> | Scheduled: <?= $scheduled_count ?> | Confirmed: <?= $confirmed_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c🔄 Auto-update active every 3 seconds', 'font-size:12px; color:#34D399;');
</script>

<!-- ================================================================ -->
<!-- DOCTOR GLOBAL STATS AUTO-UPDATE -->
<!-- ================================================================ -->
<script src="/dispensary_system/frontend/assets/js/doctor_global_stats.js"></script>

</body>
</html>