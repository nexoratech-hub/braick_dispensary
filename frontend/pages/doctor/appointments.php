<?php
// ================================================================
// FILE: frontend/pages/doctor/appointments.php
// DOCTOR - APPOINTMENTS MANAGEMENT
// WITH FULL AUTO-UPDATE (3 SECONDS)
// FIXED: Only View, Confirm, Cancel buttons
// After Confirm/Cancel: Only View button remains
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET DOCTOR INFO FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$doctor_username = $_SESSION['username'] ?? 'dr.john';
$doctor_email = $_SESSION['email'] ?? 'john@braick.com';
$doctor_phone = $_SESSION['phone'] ?? '+255 700 000 011';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET PARAMETERS
// ================================================================
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$message = isset($_GET['message']) ? trim($_GET['message']) : '';
$message_type = isset($_GET['type']) ? trim($_GET['type']) : 'info';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

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
// GET TODAY'S DATE FOR DEFAULT FILTER
// ================================================================
$today_date = date('Y-m-d');

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

function getStatusIcon($status) {
    switch ($status) {
        case 'completed': return 'fa-check-double';
        case 'confirmed': return 'fa-check-circle';
        case 'cancelled': return 'fa-times-circle';
        case 'scheduled': return 'fa-clock';
        case 'pending': return 'fa-hourglass-half';
        default: return 'fa-clock';
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
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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
        <div class="page-header-left">
            <h1 class="page-title">
                <i class="fas fa-calendar-check"></i> Appointments
                <span class="page-badge" id="totalBadge"><?= $total_appointments ?> total</span>
            </h1>
            <p class="page-subtitle">
                Manage your appointments
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200" id="recordsBadge">
                    <i class="fas fa-list mr-1"></i> <?= $total_appointments ?> appointments
                </span>
                <?php if ($scheduled_count > 0): ?>
                    <span class="ml-2 inline-flex bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs border border-yellow-200" id="scheduledBadge">
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
            <button onclick="manualRefresh()" class="btn btn-outline" id="refreshBtn">
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
        <form method="GET" class="filter-form" id="filterForm">
            <div class="filter-group">
                <div class="filter-search">
                    <i class="fas fa-search text-muted"></i>
                    <input type="text" name="search" class="filter-input" placeholder="Search by patient..." value="<?= htmlspecialchars($search) ?>" id="searchInput">
                </div>
                <input type="date" name="date" class="filter-date" value="<?= htmlspecialchars($date_filter) ?>" placeholder="Filter by date" id="dateInput">
                <select name="status" class="filter-select" id="statusSelect">
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
        <div class="table-header">
            <span class="table-title">
                <i class="fas fa-list mr-2"></i> Appointments List
                <span class="text-sm font-normal text-gray-400">(<strong id="recordsCount"><?= $total_appointments ?></strong> records)</span>
            </span>
            <span class="text-xs text-gray-400" id="lastUpdateTime">⏱ Auto-updating</span>
        </div>
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
                <tbody id="appointmentsTableBody">
                    <?php if (count($appointments) > 0): ?>
                        <?php foreach ($appointments as $index => $appt): ?>
                            <tr data-appointment-id="<?= $appt['id'] ?>" data-status="<?= $appt['status'] ?? 'scheduled' ?>">
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
                                        <i class="fas <?= getStatusIcon($appt['status']) ?>"></i>
                                        <?= ucfirst($appt['status'] ?? 'Scheduled') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons" id="actions-<?= $appt['id'] ?>">
                                        <!-- View Button - Always Visible -->
                                        <a href="view_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-view btn-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (($appt['status'] ?? '') === 'scheduled' || ($appt['status'] ?? '') === 'pending'): ?>
                                            <!-- Confirm Button - Only for scheduled/pending -->
                                            <a href="confirm_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-success btn-sm btn-confirm" title="Confirm" onclick="return confirm('Confirm this appointment?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <!-- Cancel Button - Only for scheduled/pending -->
                                            <a href="cancel_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-danger btn-sm btn-cancel" title="Cancel" onclick="return confirm('Cancel this appointment?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="emptyStateRow">
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
        <!-- Table Footer -->
        <div class="table-footer">
            <span class="text-sm text-gray-500">
                <i class="fas fa-calendar-alt mr-1"></i> 
                Showing <strong id="footerRecordsCount"><?= $total_appointments ?></strong> appointment(s)
            </span>
            <span class="text-sm text-gray-500">
                <i class="fas fa-user mr-1"></i> 
                Doctor: <strong><?= htmlspecialchars($doctor_name) ?></strong>
            </span>
            <span class="text-sm text-gray-500">
                <i class="fas fa-clock mr-1"></i> 
                <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
            </span>
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
            <span id="footerTimestampBottom">Last updated: <?= date('H:i:s') ?></span>
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
        background: rgba(11, 94, 215, 0.1);
        color: var(--primary);
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(11, 94, 215, 0.15);
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
    
    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        flex-wrap: wrap;
        gap: 8px;
    }
    .table-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    .table-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0 0 0;
        margin-top: 12px;
        border-top: 2px solid var(--border-color);
        flex-wrap: wrap;
        gap: 8px;
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
    
    .action-buttons .btn-confirm,
    .action-buttons .btn-cancel {
        transition: all 0.3s ease;
    }
    
    .action-buttons .btn-confirm:hover,
    .action-buttons .btn-cancel:hover {
        transform: scale(1.1);
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
    
    .spinner {
        display: inline-block;
        width: 14px;
        height: 14px;
        border: 2px solid rgba(255,255,255,0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    
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
        .table-footer { flex-direction: column; text-align: center; }
        .table-header { flex-direction: column; text-align: center; }
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
        .action-buttons .btn-success, .action-buttons .btn-danger { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT - FULL AUTO-UPDATE -->
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
    // ESCAPE HTML
    // ================================================================
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ================================================================
    // STATUS BADGE HTML
    // ================================================================
    function getStatusBadgeHtml(status) {
        var colorClass = 'badge-warning';
        var icon = 'fa-clock';
        
        switch (status) {
            case 'completed':
                colorClass = 'badge-success';
                icon = 'fa-check-double';
                break;
            case 'confirmed':
                colorClass = 'badge-info';
                icon = 'fa-check-circle';
                break;
            case 'cancelled':
                colorClass = 'badge-danger';
                icon = 'fa-times-circle';
                break;
            case 'scheduled':
                colorClass = 'badge-warning';
                icon = 'fa-clock';
                break;
            case 'pending':
                colorClass = 'badge-warning';
                icon = 'fa-hourglass-half';
                break;
        }
        
        return '<span class="badge ' + colorClass + '">' +
               '<i class="fas ' + icon + '"></i> ' +
               status.charAt(0).toUpperCase() + status.slice(1) +
               '</span>';
    }

    // ================================================================
    // GET ACTION BUTTONS - FIXED: Only View remains after action
    // ================================================================
    function getActionButtons(appt) {
        var status = appt.status || 'scheduled';
        var id = appt.id;
        var html = '';
        
        // View button - ALWAYS visible
        html += '<a href="view_appointment.php?id=' + id + '" class="btn btn-view btn-sm" title="View Details">' +
                '<i class="fas fa-eye"></i>' +
                '</a>';
        
        // Only show Confirm and Cancel if status is scheduled or pending
        if (status === 'scheduled' || status === 'pending') {
            html += '<a href="confirm_appointment.php?id=' + id + '" class="btn btn-success btn-sm btn-confirm" title="Confirm" onclick="return confirm(\'Confirm this appointment?\')">' +
                    '<i class="fas fa-check"></i>' +
                    '</a>';
            html += '<a href="cancel_appointment.php?id=' + id + '" class="btn btn-danger btn-sm btn-cancel" title="Cancel" onclick="return confirm(\'Cancel this appointment?\')">' +
                    '<i class="fas fa-times"></i>' +
                    '</a>';
        }
        // For 'confirmed', 'completed', 'cancelled' - only View button shows
        
        return html;
    }

    // ================================================================
    // FETCH APPOINTMENTS DATA
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;

    function fetchAppointments() {
        if (isUpdating) return;
        isUpdating = true;
        
        var status = '<?= addslashes($status_filter) ?>';
        var date = '<?= addslashes($date_filter) ?>';
        var search = '<?= addslashes($search) ?>';
        var doctorId = <?= $doctor_id ?>;
        
        var url = '/dispensary_system/frontend/api/get_doctor_appointments.php?t=' + new Date().getTime();
        url += '&doctor_id=' + doctorId;
        if (status) url += '&status=' + encodeURIComponent(status);
        if (date) url += '&date=' + encodeURIComponent(date);
        if (search) url += '&search=' + encodeURIComponent(search);
        
        fetch(url)
            .then(function(response) { 
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json(); 
            })
            .then(function(data) {
                if (data.success) {
                    updateUI(data);
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Fetch appointments error:', error);
                isUpdating = false;
            });
    }

    // ================================================================
    // UPDATE UI
    // ================================================================
    function updateUI(data) {
        var appointments = data.appointments || [];
        var stats = data.stats || {};
        var totalRecords = data.total_records || 0;
        
        // Update stats
        document.getElementById('statTotal').textContent = stats.total || 0;
        document.getElementById('statScheduled').textContent = stats.scheduled || 0;
        document.getElementById('statConfirmed').textContent = stats.confirmed || 0;
        document.getElementById('statCompleted').textContent = stats.completed || 0;
        
        // Update badges
        document.getElementById('totalBadge').textContent = (stats.total || 0) + ' total';
        document.getElementById('recordsBadge').innerHTML = '<i class="fas fa-list mr-1"></i> ' + totalRecords + ' appointments';
        
        var scheduledBadge = document.getElementById('scheduledBadge');
        if (scheduledBadge) {
            scheduledBadge.innerHTML = '<i class="fas fa-clock mr-1"></i> ' + (stats.scheduled || 0) + ' scheduled';
            if ((stats.scheduled || 0) > 0) {
                scheduledBadge.style.display = 'inline-flex';
            } else {
                scheduledBadge.style.display = 'none';
            }
        }
        
        document.getElementById('recordsCount').textContent = totalRecords;
        document.getElementById('footerRecordsCount').textContent = totalRecords;
        
        // Update table
        var tbody = document.getElementById('appointmentsTableBody');
        if (!tbody) return;
        
        if (appointments.length === 0) {
            tbody.innerHTML = `
                <tr id="emptyStateRow">
                    <td colspan="7" class="text-center py-8 text-muted">
                        <i class="fas fa-calendar-check text-3xl block mb-2"></i>
                        ${search || status || date ? 'No appointments found matching your filters' : 'No appointments scheduled. Click "New Appointment" to create one.'}
                    </td>
                </tr>
            `;
            return;
        }
        
        var html = '';
        appointments.forEach(function(appt, index) {
            var appointmentDate = new Date(appt.appointment_date);
            var dateStr = appointmentDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            var timeStr = appointmentDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            
            html += `
                <tr data-appointment-id="${appt.id}" data-status="${appt.status || 'scheduled'}">
                    <td>${index + 1}</td>
                    <td>
                        <div class="font-medium">${escapeHtml(appt.patient_name || 'N/A')}</div>
                        <div class="text-xs text-muted">${escapeHtml(appt.patient_code || '')}</div>
                        ${appt.patient_phone ? `<div class="text-xs text-muted">${escapeHtml(appt.patient_phone)}</div>` : ''}
                    </td>
                    <td>
                        <div class="font-medium">${dateStr}</div>
                        <div class="text-xs text-muted">${timeStr}</div>
                    </td>
                    <td class="text-sm">${escapeHtml((appt.purpose || 'N/A').substring(0, 40))}${(appt.purpose || '').length > 40 ? '...' : ''}</td>
                    <td class="text-sm">${escapeHtml(appt.created_by_name || 'N/A')}</td>
                    <td>${getStatusBadgeHtml(appt.status || 'scheduled')}</td>
                    <td>
                        <div class="action-buttons" id="actions-${appt.id}">
                            ${getActionButtons(appt)}
                        </div>
                    </td>
                </tr>
            `;
        });
        
        tbody.innerHTML = html;
        
        // Update timestamp
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.getElementById('lastUpdateBadge').innerHTML = 
            '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + timeStr;
        document.getElementById('lastUpdateTime').textContent = '⏱ ' + timeStr;
        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
        document.getElementById('footerTimestampBottom').textContent = 'Last updated: ' + timeStr;
        
        // Check for notifications
        if (data.notification) {
            showToast('📋 Update', data.notification, 'info');
        }
    }

    // ================================================================
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<span class="spinner"></span> Loading...';
        btn.disabled = true;
        
        fetchAppointments();
        
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
            showToast('✅ Refreshed', 'Appointments data updated manually', 'success');
        }, 1500);
    }

    // ================================================================
    // START / STOP AUTO-UPDATE
    // ================================================================
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        fetchAppointments();
        updateInterval = setInterval(fetchAppointments, 3000);
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }

    // ================================================================
    // VISIBILITY CHANGE
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

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

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            document.getElementById('searchInput')?.focus();
            document.getElementById('searchInput')?.select();
        }
        if (e.altKey && e.key === 'n') {
            e.preventDefault();
            window.location.href = 'appointment.php?patient_id=0';
        }
        if (e.key === 'F5') {
            e.preventDefault();
            manualRefresh();
        }
        if (e.key === 'Escape' && document.activeElement === document.getElementById('searchInput')) {
            document.getElementById('searchInput').value = '';
            document.getElementById('searchInput').blur();
        }
    });

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 1500);
    });

    console.log('%c📅 Appointments - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User ID: <?= $doctor_id ?> | Role: <?= $_SESSION['role'] ?>', 'font-size:12px; color:#64748B;');
    console.log('%c📊 Total: <?= $total_appointments ?> | Scheduled: <?= $scheduled_count ?> | Confirmed: <?= $confirmed_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c🔄 Full auto-update every 3 seconds', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Only View, Confirm, Cancel buttons | After action: Only View remains', 'font-size:12px; color:#34D399;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($doctor_branch_name) ?>', 'font-size:12px; color:#7C3AED;');
    console.log('%c⌨️ Shortcuts: Ctrl+K=Search | Alt+N=New Appointment | F5=Refresh | Esc=Clear search', 'font-size:12px; color:#64748B;');
</script>

<!-- ================================================================ -->
<!-- DOCTOR GLOBAL STATS AUTO-UPDATE -->
<!-- ================================================================ -->
<script src="/dispensary_system/frontend/assets/js/doctor_global_stats.js"></script>

</body>
</html>