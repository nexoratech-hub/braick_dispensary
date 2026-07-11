<?php
// ================================================================
// FILE: frontend/pages/doctor/appointments.php
// DOCTOR - APPOINTMENTS
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
// GET APPOINTMENTS
// ================================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$sql = "SELECT a.*, p.full_name as patient_name, p.patient_id, p.phone
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = ?";

$params = [$doctor_id];

if (!empty($status_filter)) {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $sql .= " AND DATE(a.appointment_date) = ?";
    $params[] = $date_filter;
}

$sql .= " ORDER BY a.appointment_date ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$total_appointments = count($appointments);
$scheduled = 0;
$confirmed = 0;
$completed = 0;
$cancelled = 0;
foreach ($appointments as $a) {
    if ($a['status'] === 'scheduled') $scheduled++;
    elseif ($a['status'] === 'confirmed') $confirmed++;
    elseif ($a['status'] === 'completed') $completed++;
    elseif ($a['status'] === 'cancelled') $cancelled++;
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

    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="page-title">
                <i class="fas fa-calendar-check mr-2" style="color: #0B5ED7;"></i> Appointments
            </h1>
            <p class="page-subtitle">
                Manage your appointments
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-calendar-check mr-1"></i> <?= $total_appointments ?> appointments
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="new_appointment.php" class="btn btn-blue btn-sm">
                <i class="fas fa-plus"></i> New Appointment
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="stat-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Scheduled</p>
                    <p class="stat-number"><?= $scheduled ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        <div class="stat-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Confirmed</p>
                    <p class="stat-number"><?= $confirmed ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="stat-card purple animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Completed</p>
                    <p class="stat-number"><?= $completed ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-flag-checkered"></i></div>
            </div>
        </div>
        <div class="stat-card orange animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Cancelled</p>
                    <p class="stat-number"><?= $cancelled ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card mb-6">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <input type="date" name="date" class="form-control w-auto" value="<?= $date_filter ?>">
            <select name="status" class="form-control w-auto min-w-[120px]">
                <option value="">All Status</option>
                <option value="scheduled" <?= $status_filter === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <button type="submit" class="btn btn-blue btn-sm">
                <i class="fas fa-filter"></i> Filter
            </button>
            <a href="appointments.php" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>

    <!-- Appointments Table -->
    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Date & Time</th>
                        <th>Patient</th>
                        <th>Phone</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th style="border-radius: 0 8px 0 0; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($appointments) > 0): ?>
                        <?php foreach ($appointments as $index => $appt): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($appt['appointment_date'])) ?></td>
                                <td>
                                    <div>
                                        <div class="font-medium"><?= htmlspecialchars($appt['patient_name']) ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($appt['patient_id'] ?? 'N/A') ?></div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($appt['phone'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars(substr($appt['purpose'] ?? '', 0, 30)) ?></td>
                                <td>
                                    <span class="badge <?= $appt['status'] === 'completed' ? 'badge-success' : ($appt['status'] === 'cancelled' ? 'badge-danger' : ($appt['status'] === 'confirmed' ? 'badge-warning' : 'badge-info')) ?>">
                                        <?= ucfirst($appt['status'] ?? 'Scheduled') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-view" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (($appt['status'] ?? 'scheduled') !== 'completed' && ($appt['status'] ?? 'scheduled') !== 'cancelled'): ?>
                                            <a href="edit_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-400">
                                <i class="fas fa-calendar-check text-3xl block mb-2"></i>
                                No appointments found for the selected date.
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
            Appointments
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<style>
    .form-control { width: 100%; padding: 8px 14px; border: 2px solid var(--border-color); border-radius: 10px; font-size: 0.85rem; background: var(--bg-card); color: var(--text-primary); outline: none; transition: all 0.3s; }
    .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12); }
    .table-wrap { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .data-table thead th { text-align: left; padding: 10px 14px; font-weight: 700; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: white; background: var(--primary); border-bottom: 3px solid var(--primary-dark); white-space: nowrap; }
    .data-table tbody tr:nth-child(even) { background: var(--primary-bg); }
    .data-table tbody tr:nth-child(odd) { background: var(--bg-card); }
    .data-table tbody tr:hover { background: var(--green-bg); }
    .data-table td { padding: 10px 14px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; }
    .badge { padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; color: white; border: none; }
    .badge-success { background: var(--green); }
    .badge-danger { background: var(--red); }
    .badge-info { background: var(--primary); }
    .badge-warning { background: var(--orange); }
    .btn-view { background: var(--primary); color: white; padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
    .btn-view:hover { background: var(--primary-dark); transform: scale(1.05); }
    .btn-edit { background: var(--orange); color: white; padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
    .btn-edit:hover { background: #B45309; transform: scale(1.05); }
    .action-buttons { display: flex; align-items: center; gap: 4px; flex-wrap: nowrap; justify-content: center; }
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    .w-auto { width: auto; }
    .min-w-\[120px\] { min-width: 120px; }
    [data-theme="dark"] .data-table tbody tr:nth-child(even) { background: #1E293B; }
    [data-theme="dark"] .data-table tbody tr:nth-child(odd) { background: #1E293B; }
    [data-theme="dark"] .data-table tbody tr:hover { background: #1A3A2A; }
</style>

<script>
    console.log('%c👨‍⚕️ Appointments - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
</script>

</body>
</html>