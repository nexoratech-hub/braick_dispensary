<?php
// ================================================================
// FILE: frontend/pages/doctor/appointment.php
// DOCTOR - APPOINTMENTS MANAGEMENT
// VIEW AND CREATE APPOINTMENTS FOR PATIENTS
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
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$filter_date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET DOCTOR'S PATIENTS (for dropdown)
// ================================================================
$patients = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT p.id, p.full_name, p.patient_id 
        FROM patients p
        JOIN visits v ON p.id = v.patient_id
        WHERE v.doctor_id = ?
        ORDER BY p.full_name
    ");
    $stmt->execute([$doctor_id]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patients = [];
}

// ================================================================
// GET SELECTED PATIENT DETAILS
// ================================================================
$selected_patient = null;
if ($patient_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $selected_patient = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $selected_patient = null;
    }
}

// ================================================================
// GET APPOINTMENTS
// ================================================================
$appointments = [];
try {
    $query = "
        SELECT a.*, 
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone,
               p.email,
               u.full_name as doctor_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.doctor_id = ?
    ";
    $params = [$doctor_id];
    
    if ($patient_id > 0) {
        $query .= " AND a.patient_id = ?";
        $params[] = $patient_id;
    }
    
    if ($filter_status !== 'all') {
        $query .= " AND a.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_date)) {
        $query .= " AND DATE(a.appointment_date) = ?";
        $params[] = $filter_date;
    }
    
    $query .= " ORDER BY a.appointment_date ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $appointments = [];
}

// ================================================================
// GET APPOINTMENT STATS
// ================================================================
$stats = [
    'total' => 0,
    'scheduled' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0
];

try {
    $stmt = $db->prepare("
        SELECT status, COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ?
        GROUP BY status
    ");
    $stmt->execute([$doctor_id]);
    $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($status_counts as $sc) {
        $stats[$sc['status']] = $sc['count'];
        $stats['total'] += $sc['count'];
    }
} catch (Exception $e) {
    $stats = ['total' => 0, 'scheduled' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
}

// ================================================================
// HANDLE FORM SUBMISSIONS
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // CREATE APPOINTMENT
    // ================================================================
    if ($action === 'create') {
        $patient_id_post = (int)($_POST['patient_id'] ?? 0);
        $appointment_date = trim($_POST['appointment_date'] ?? '');
        $appointment_time = trim($_POST['appointment_time'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $status = trim($_POST['status'] ?? 'scheduled');
        
        $errors = [];
        if ($patient_id_post <= 0) $errors[] = "Please select a patient";
        if (empty($appointment_date)) $errors[] = "Please select a date";
        if (empty($appointment_time)) $errors[] = "Please select a time";
        
        if (empty($errors)) {
            $datetime = $appointment_date . ' ' . $appointment_time . ':00';
            
            $stmt = $db->prepare("
                INSERT INTO appointments (
                    patient_id, doctor_id, appointment_date, purpose, status, branch_id, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$patient_id_post, $doctor_id, $datetime, $purpose, $status, $doctor_branch_id, $doctor_id])) {
                $appt_id = $db->lastInsertId();
                $message = "✅ Appointment created successfully!";
                $message_type = 'success';
                
                // Log activity
                try {
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, action, details) 
                        VALUES (?, 'appointment_created', ?)
                    ");
                    $stmt->execute([$doctor_id, "New appointment created for patient ID: $patient_id_post"]);
                } catch (Exception $e) {}
                
                // Refresh appointments
                $query = "
                    SELECT a.*, 
                           p.full_name as patient_name,
                           p.patient_id as patient_code,
                           p.phone,
                           p.email,
                           u.full_name as doctor_name
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.id
                    LEFT JOIN users u ON a.doctor_id = u.id
                    WHERE a.doctor_id = ?
                    ORDER BY a.appointment_date ASC
                ";
                $stmt = $db->prepare($query);
                $stmt->execute([$doctor_id]);
                $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<script>setTimeout(function(){ window.location.href = "appointment.php?patient_id=' . $patient_id_post . '&date=' . $appointment_date . '"; }, 1500);</script>';
            } else {
                $message = "❌ Failed to create appointment!";
                $message_type = 'error';
            }
        } else {
            $message = "❌ " . implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // UPDATE APPOINTMENT STATUS
    // ================================================================
    if ($action === 'update_status') {
        $appointment_id = (int)($_POST['appointment_id'] ?? 0);
        $new_status = trim($_POST['new_status'] ?? '');
        $valid_statuses = ['scheduled', 'confirmed', 'completed', 'cancelled'];
        
        if ($appointment_id > 0 && in_array($new_status, $valid_statuses)) {
            $stmt = $db->prepare("
                UPDATE appointments 
                SET status = ?, updated_at = NOW()
                WHERE id = ? AND doctor_id = ?
            ");
            if ($stmt->execute([$new_status, $appointment_id, $doctor_id])) {
                $message = "✅ Appointment status updated to: " . ucfirst($new_status);
                $message_type = 'success';
                
                // Refresh appointments
                $query = "
                    SELECT a.*, 
                           p.full_name as patient_name,
                           p.patient_id as patient_code,
                           p.phone,
                           p.email,
                           u.full_name as doctor_name
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.id
                    LEFT JOIN users u ON a.doctor_id = u.id
                    WHERE a.doctor_id = ?
                    ORDER BY a.appointment_date ASC
                ";
                $stmt = $db->prepare($query);
                $stmt->execute([$doctor_id]);
                $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $message = "❌ Failed to update appointment status!";
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// GET BRANCH NAME
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
// HELPER FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'scheduled' => 'badge-info',
        'confirmed' => 'badge-success',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger',
        'pending' => 'badge-warning'
    ];
    return $map[$status] ?? 'badge-info';
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
                <span class="page-badge"><?= $stats['total'] ?> total</span>
            </h1>
            <p class="page-subtitle">
                Manage your patient appointments
                <span class="separator">|</span>
                <span class="status-badge badge-info">
                    <i class="fas fa-user-md"></i> <?= htmlspecialchars($doctor_name) ?>
                </span>
                <span class="separator">|</span>
                <span class="status-badge badge-success">
                    <i class="fas fa-calendar-alt"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div class="page-header-right">
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="window.location.href='appointment.php?patient_id=<?= $patient_id ?>&date=<?= $filter_date ?>'" class="btn btn-outline">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button onclick="window.print()" class="btn btn-outline">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card stat-card-blue">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-card-info">
                    <span class="stat-card-label">Total</span>
                    <span class="stat-card-number"><?= $stats['total'] ?></span>
                </div>
            </div>
        </div>
        <div class="stat-card stat-card-info">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-card-info">
                    <span class="stat-card-label">Scheduled</span>
                    <span class="stat-card-number"><?= $stats['scheduled'] ?></span>
                </div>
            </div>
        </div>
        <div class="stat-card stat-card-green">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-card-info">
                    <span class="stat-card-label">Confirmed</span>
                    <span class="stat-card-number"><?= $stats['confirmed'] ?></span>
                </div>
            </div>
        </div>
        <div class="stat-card stat-card-orange">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stat-card-info">
                    <span class="stat-card-label">Cancelled</span>
                    <span class="stat-card-number"><?= $stats['cancelled'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS & NEW APPOINTMENT -->
    <!-- ================================================================ -->
    <div class="filter-section">
        <div class="filter-row">
            <div class="filter-group">
                <label class="filter-label">Patient</label>
                <select id="patientFilter" class="form-control" onchange="window.location.href='appointment.php?patient_id='+this.value+'&status=<?= $filter_status ?>&date=<?= $filter_date ?>'">
                    <option value="0">All Patients</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $patient_id == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['patient_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select id="statusFilter" class="form-control" onchange="window.location.href='appointment.php?patient_id=<?= $patient_id ?>&status='+this.value+'&date=<?= $filter_date ?>'">
                    <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="scheduled" <?= $filter_status === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                    <option value="confirmed" <?= $filter_status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Date</label>
                <input type="date" id="dateFilter" class="form-control" value="<?= $filter_date ?>" 
                       onchange="window.location.href='appointment.php?patient_id=<?= $patient_id ?>&status=<?= $filter_status ?>&date='+this.value">
            </div>
            <div class="filter-group">
                <label class="filter-label">&nbsp;</label>
                <button class="btn btn-primary" onclick="showCreateForm()">
                    <i class="fas fa-plus"></i> New Appointment
                </button>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- CREATE APPOINTMENT FORM (Hidden by default) -->
    <!-- ================================================================ -->
    <div id="createForm" class="consultation-card" style="display:none; margin-bottom: 20px;">
        <h3 class="card-title">
            <i class="fas fa-calendar-plus title-blue"></i> Create New Appointment
            <button class="btn btn-outline btn-sm ml-2" onclick="hideCreateForm()">
                <i class="fas fa-times"></i> Close
            </button>
        </h3>
        
        <form method="POST" action="" id="appointmentForm">
            <input type="hidden" name="action" value="create">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">Patient <span class="required">*</span></label>
                    <select name="patient_id" class="form-control" required>
                        <option value="">Select Patient...</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $patient_id == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['patient_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="scheduled">Scheduled</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date <span class="required">*</span></label>
                    <input type="date" name="appointment_date" class="form-control" value="<?= $filter_date ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Time <span class="required">*</span></label>
                    <input type="time" name="appointment_time" class="form-control" value="09:00" required>
                </div>
                <div class="form-group md:col-span-2">
                    <label class="form-label">Purpose</label>
                    <textarea name="purpose" class="form-control" rows="2" placeholder="Reason for appointment..."></textarea>
                </div>
            </div>
            
            <div class="form-actions mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Appointment
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <button type="button" class="btn btn-outline" onclick="hideCreateForm()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- APPOINTMENTS LIST -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-list title-blue"></i> Appointments
            <span class="text-sm font-normal text-gray-400">(<?= count($appointments) ?> appointments)</span>
            <?php if ($patient_id > 0 && $selected_patient): ?>
                <span class="patient-badge ml-2">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($selected_patient['full_name']) ?>
                </span>
            <?php endif; ?>
            <?php if ($filter_status !== 'all'): ?>
                <span class="status-badge <?= getStatusBadgeClass($filter_status) ?> ml-2">
                    <?= ucfirst($filter_status) ?>
                </span>
            <?php endif; ?>
        </h3>
        
        <?php if (count($appointments) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Patient</th>
                            <th>Date & Time</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($appointments as $appt): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <div class="patient-cell">
                                        <div class="patient-avatar-sm" style="background: <?= '#' . substr(md5($appt['patient_name']), 0, 6) ?>;">
                                            <?= strtoupper(substr($appt['patient_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-medium"><?= htmlspecialchars($appt['patient_name']) ?></div>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars($appt['patient_code'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-medium"><?= date('M d, Y', strtotime($appt['appointment_date'])) ?></div>
                                    <div class="text-xs text-gray-400"><?= date('h:i A', strtotime($appt['appointment_date'])) ?></div>
                                </td>
                                <td><?= htmlspecialchars($appt['purpose'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= getStatusBadgeClass($appt['status'] ?? 'scheduled') ?>">
                                        <?= ucfirst($appt['status'] ?? 'Scheduled') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if (($appt['status'] ?? '') !== 'completed' && ($appt['status'] ?? '') !== 'cancelled'): ?>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?= $appt['id'] ?>">
                                                <select name="new_status" class="form-control-sm" onchange="this.form.submit()">
                                                    <option value="">Update</option>
                                                    <option value="confirmed">Confirm</option>
                                                    <option value="completed">Complete</option>
                                                    <option value="cancelled">Cancel</option>
                                                </select>
                                            </form>
                                        <?php endif; ?>
                                        <a href="view_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-primary btn-sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="consultation.php?patient_id=<?= $appt['patient_id'] ?>" class="btn btn-success btn-sm" title="Consult">
                                            <i class="fas fa-stethoscope"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-check"></i>
                <h3>No Appointments Found</h3>
                <p>
                    <?php if ($patient_id > 0): ?>
                        No appointments for this patient
                    <?php elseif ($filter_status !== 'all'): ?>
                        No <?= $filter_status ?> appointments
                    <?php else: ?>
                        No appointments scheduled
                    <?php endif; ?>
                </p>
                <button class="btn btn-primary btn-sm mt-2" onclick="showCreateForm()">
                    <i class="fas fa-plus"></i> Create Appointment
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="separator">|</span>
            Appointments
            <span class="separator">|</span>
            <?= htmlspecialchars($doctor_name) ?>
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
    
    .alert {
        padding: 12px 18px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
        border: 1px solid transparent;
    }
    .alert-success { background: #D1FAE5; color: #059669; border-color: #059669; }
    .alert-error { background: #FEE2E2; color: #DC2626; border-color: #DC2626; }
    .alert-warning { background: #FEF3C7; color: #D97706; border-color: #D97706; }
    .alert-info { background: #E8F0FE; color: #0B5ED7; border-color: #0B5ED7; }
    
    [data-theme="dark"] .alert-success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    [data-theme="dark"] .alert-error { background: #3A1A1A; color: #F87171; border-color: #F87171; }
    [data-theme="dark"] .alert-warning { background: #3D2E0A; color: #FBBF24; border-color: #FBBF24; }
    [data-theme="dark"] .alert-info { background: #1E3A5F; color: #6EA8FE; border-color: #6EA8FE; }
    
    .consultation-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    .consultation-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    
    .card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }
    .title-blue { color: var(--primary); }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    .stat-card-inner {
        display: flex;
        align-items: center;
        gap: 14px;
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
    .stat-card-green .stat-card-icon { background: linear-gradient(135deg, #059669, #34D399); }
    .stat-card-orange .stat-card-icon { background: linear-gradient(135deg, #D97706, #F59E0B); }
    .stat-card-info .stat-card-icon { background: linear-gradient(135deg, #0891B2, #06B6D4); }
    
    .stat-card-info .stat-card-info { flex: 1; }
    .stat-card-label {
        font-size: 0.7rem;
        font-weight: 500;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .stat-card-number {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }
    
    .filter-section {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        margin-bottom: 20px;
    }
    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-end;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-width: 150px;
    }
    .filter-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 2px;
    }
    
    .form-control {
        width: 100%;
        padding: 8px 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.85rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    .form-control::placeholder { color: var(--text-secondary); opacity: 0.6; }
    select.form-control { appearance: auto; cursor: pointer; }
    input[type="date"].form-control, input[type="time"].form-control { cursor: pointer; }
    
    .form-control-sm {
        padding: 3px 8px;
        border: 2px solid var(--border-color);
        border-radius: 6px;
        font-size: 0.7rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .form-control-sm:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    
    .form-group { margin-bottom: 14px; }
    .form-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }
    .required { color: #EF4444; }
    .md:col-span-2 { grid-column: span 2; }
    
    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 16px;
        margin-top: 16px;
        border-top: 2px solid var(--border-color);
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        background: transparent;
        min-height: 40px;
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
    .btn-success {
        background: #059669;
        color: white;
    }
    .btn-success:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
    }
    .btn-sm { padding: 4px 12px; font-size: 0.7rem; border-radius: 6px; min-height: 32px; }
    
    .status-badge {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 3px 14px;
        border-radius: 20px;
        line-height: 1.4;
        text-align: center;
        min-width: 60px;
        border: 1px solid transparent;
    }
    
    .badge-info {
        background: #E8F0FE;
        color: #0B5ED7;
        border-color: #BFDBFE;
    }
    .badge-success {
        background: #D1FAE5;
        color: #059669;
        border-color: #A7F3D0;
    }
    .badge-danger {
        background: #FEE2E2;
        color: #DC2626;
        border-color: #FCA5A5;
    }
    .badge-warning {
        background: #FEF3C7;
        color: #D97706;
        border-color: #FDE68A;
    }
    
    [data-theme="dark"] .badge-info {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #1E3A5F;
    }
    [data-theme="dark"] .badge-success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #065F46;
    }
    [data-theme="dark"] .badge-danger {
        background: #3A1A1A;
        color: #F87171;
        border-color: #7F1D1D;
    }
    [data-theme="dark"] .badge-warning {
        background: #3D2E0A;
        color: #FBBF24;
        border-color: #78350F;
    }
    
    .patient-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--primary-bg);
        color: var(--primary);
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    [data-theme="dark"] .patient-badge {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    .table-wrap { overflow-x: auto; }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .data-table th {
        text-align: left;
        padding: 8px 12px;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        color: var(--text-secondary);
        border-bottom: 2px solid var(--border-color);
    }
    .data-table td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    .data-table tr:hover td { background: var(--primary-bg); }
    
    .patient-cell {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .patient-avatar-sm {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
    }
    .font-medium { font-weight: 500; }
    .text-xs { font-size: 0.75rem; }
    .text-gray-400 { color: var(--text-secondary); }
    .mt-2 { margin-top: 8px; }
    .mt-3 { margin-top: 12px; }
    
    .action-buttons {
        display: flex;
        gap: 4px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .grid { display: grid; }
    .grid-cols-1 { grid-template-columns: 1fr; }
    .gap-4 { gap: 16px; }
    .md\:grid-cols-2 { grid-template-columns: 1fr 1fr; }
    
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
    .empty-state h3 {
        font-size: 1.1rem;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }
    .empty-state p { font-size: 0.9rem; margin: 0; }
    
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
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .main-content { padding: 16px; }
        .page-title { font-size: 1.3rem; }
        .filter-row { flex-direction: column; }
        .filter-group { min-width: 100%; }
    }
    
    @media (max-width: 768px) {
        .main-content { margin-left: 0; padding: 12px; }
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .page-header { flex-direction: column; }
        .page-header-right { width: 100%; }
        .page-header-right .btn { flex: 1; justify-content: center; }
        .consultation-card { padding: 14px 16px; }
        .data-table { font-size: 0.75rem; }
        .data-table th, .data-table td { padding: 4px 8px; }
        .action-buttons { flex-wrap: wrap; }
        .md\:grid-cols-2 { grid-template-columns: 1fr; }
        .md\:col-span-2 { grid-column: span 1; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
    }
    
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .page-subtitle { flex-direction: column; align-items: flex-start; gap: 4px; }
        .separator { display: none; }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .consultation-card { border: 1px solid #ddd !important; box-shadow: none !important; page-break-inside: avoid; }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
        .filter-section { border: 1px solid #ddd !important; }
        .stat-card { border: 1px solid #ddd !important; }
        #createForm { display: none !important; }
        .action-buttons select { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // SHOW/HIDE CREATE FORM
    // ================================================================
    function showCreateForm() {
        document.getElementById('createForm').style.display = 'block';
        document.getElementById('createForm').scrollIntoView({ behavior: 'smooth' });
    }
    
    function hideCreateForm() {
        document.getElementById('createForm').style.display = 'none';
    }
    
    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
    
    // ================================================================
    // TOAST
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
    console.log('%c📊 Total Appointments: <?= $stats['total'] ?>', 'font-size:12px; color:#059669;');
    console.log('%c📋 Showing: <?= count($appointments) ?> appointments', 'font-size:12px; color:#64748B;');
</script>

</body>
</html>