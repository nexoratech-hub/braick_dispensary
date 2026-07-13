<?php
// ================================================================
// FILE: frontend/pages/doctor/view_appointment.php
// DOCTOR - VIEW APPOINTMENT DETAILS
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
// GET APPOINTMENT ID
// ================================================================
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($appointment_id <= 0) {
    header('Location: appointments.php');
    exit;
}

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
// GET APPOINTMENT DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        a.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone as patient_phone,
        p.email as patient_email,
        p.date_of_birth,
        p.gender,
        p.address,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        r.full_name as created_by_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users u ON a.doctor_id = u.id
    LEFT JOIN users r ON a.created_by = r.id
    WHERE a.id = ? AND a.doctor_id = ?
");
$stmt->execute([$appointment_id, $doctor_id]);
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    header('Location: appointments.php?error=not_found');
    exit;
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

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age;
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
                <i class="fas fa-calendar-check mr-2" style="color: #0B5ED7;"></i> Appointment Details
            </h1>
            <p class="page-subtitle">
                View complete appointment information
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-hashtag mr-1"></i> Appointment #<?= $appointment['id'] ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($appointment['patient_name']) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="appointments.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if (($appointment['status'] ?? '') === 'scheduled' || ($appointment['status'] ?? '') === 'pending'): ?>
                <a href="confirm_appointment.php?id=<?= $appointment['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Confirm this appointment?')">
                    <i class="fas fa-check"></i> Confirm
                </a>
                <a href="cancel_appointment.php?id=<?= $appointment['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Cancel this appointment?')">
                    <i class="fas fa-times"></i> Cancel
                </a>
            <?php endif; ?>
            <?php if (($appointment['status'] ?? '') === 'confirmed'): ?>
                <a href="complete_appointment.php?id=<?= $appointment['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Mark this appointment as completed?')">
                    <i class="fas fa-check-double"></i> Complete
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- APPOINTMENT SUMMARY -->
    <!-- ================================================================ -->
    <div class="summary-header">
        <div class="summary-header-left">
            <h2 class="summary-title">
                <?= htmlspecialchars($appointment['patient_name']) ?>
                <span class="status-badge <?= getStatusBadgeClass($appointment['status']) ?>">
                    <?= ucfirst($appointment['status'] ?? 'Scheduled') ?>
                </span>
            </h2>
            <div class="summary-meta">
                <span class="meta-item">
                    <i class="far fa-calendar-alt"></i>
                    <?= date('F d, Y', strtotime($appointment['appointment_date'])) ?>
                </span>
                <span class="meta-item">
                    <i class="far fa-clock"></i>
                    <?= date('h:i A', strtotime($appointment['appointment_date'])) ?>
                </span>
                <span class="meta-item">
                    <i class="fas fa-user-md"></i>
                    Doctor: <?= htmlspecialchars($appointment['doctor_name'] ?? $doctor_name) ?>
                </span>
            </div>
        </div>
        <div class="summary-header-right">
            <span class="appointment-id">#<?= $appointment['id'] ?></span>
            <span class="created-by">
                <i class="fas fa-user-plus"></i>
                Created by: <?= htmlspecialchars($appointment['created_by_name'] ?? 'N/A') ?>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- APPOINTMENT & PATIENT INFO -->
    <!-- ================================================================ -->
    <div class="info-grid">
        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-calendar-check text-blue-600"></i> Appointment Information
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Appointment Date</span>
                    <span class="info-value font-semibold"><?= date('F d, Y', strtotime($appointment['appointment_date'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Appointment Time</span>
                    <span class="info-value"><?= date('h:i A', strtotime($appointment['appointment_date'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Purpose</span>
                    <span class="info-value"><?= htmlspecialchars($appointment['purpose'] ?? 'Not specified') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <span class="badge <?= getStatusBadgeClass($appointment['status']) ?>">
                            <?= ucfirst($appointment['status'] ?? 'Scheduled') ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Created By</span>
                    <span class="info-value"><?= htmlspecialchars($appointment['created_by_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Created On</span>
                    <span class="info-value"><?= date('F d, Y h:i A', strtotime($appointment['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-user text-green-600"></i> Patient Information
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value font-semibold"><?= htmlspecialchars($appointment['patient_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($appointment['patient_code'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gender</span>
                    <span class="info-value"><?= htmlspecialchars($appointment['gender'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date of Birth</span>
                    <span class="info-value"><?= !empty($appointment['date_of_birth']) ? date('M d, Y', strtotime($appointment['date_of_birth'])) : 'N/A' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Age</span>
                    <span class="info-value"><?= calculateAge($appointment['date_of_birth'] ?? '') ?> years</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?= htmlspecialchars($appointment['patient_phone'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($appointment['patient_email'])): ?>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($appointment['patient_email']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($appointment['address'])): ?>
                    <div class="info-row">
                        <span class="info-label">Address</span>
                        <span class="info-value"><?= htmlspecialchars($appointment['address']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Appointment Details
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
       SUMMARY HEADER
       ================================================================ */
    .summary-header {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        transition: all 0.3s ease;
    }
    
    .summary-header:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .summary-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 6px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .summary-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
    }
    
    .meta-item {
        font-size: 0.8rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .meta-item i {
        font-size: 0.8rem;
        color: var(--primary);
    }
    
    .summary-header-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
    }
    
    .appointment-id {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--primary);
        font-family: monospace;
    }
    
    .created-by {
        font-size: 0.75rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .status-badge {
        padding: 4px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: white;
        border: none;
    }
    
    .status-badge.badge-success { background: #059669; }
    .status-badge.badge-danger { background: #EF4444; }
    .status-badge.badge-warning { background: #D97706; }
    .status-badge.badge-info { background: var(--primary); }
    
    /* ================================================================
       INFO GRID
       ================================================================ */
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .info-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .info-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .info-card-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .info-card-body {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        font-size: 0.8rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .info-value {
        font-size: 0.85rem;
        color: var(--text-primary);
        text-align: right;
        max-width: 60%;
        word-break: break-word;
    }
    
    .info-value.font-semibold { font-weight: 600; }
    .info-value.font-mono { font-family: monospace; }
    
    .text-blue-600 { color: var(--primary); }
    .text-green-600 { color: #059669; }
    
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
    
    /* ================================================================
       DARK MODE
       ================================================================ */
    [data-theme="dark"] .summary-header {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .summary-title {
        color: #F1F5F9;
    }
    [data-theme="dark"] .meta-item {
        color: #94A3B8;
    }
    [data-theme="dark"] .info-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .info-card-title {
        color: #F1F5F9;
        border-color: #334155;
    }
    [data-theme="dark"] .info-row {
        border-color: #334155;
    }
    [data-theme="dark"] .info-value {
        color: #F1F5F9;
    }
    [data-theme="dark"] .footer {
        border-color: #334155;
        color: #64748B;
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
        .summary-header {
            flex-direction: column;
            align-items: flex-start;
            padding: 16px 18px;
        }
        .summary-header-right {
            align-items: flex-start;
            width: 100%;
        }
        .summary-title {
            font-size: 1.1rem;
        }
        .summary-meta {
            flex-direction: column;
            gap: 4px;
        }
        .page-header .page-title {
            font-size: 1.2rem;
        }
        .info-card {
            padding: 14px 16px;
        }
        .info-row {
            flex-direction: column;
            align-items: flex-start;
        }
        .info-value {
            text-align: left;
            max-width: 100%;
        }
        .btn {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
    }
    
    @media (max-width: 480px) {
        .summary-title {
            font-size: 1rem;
        }
        .summary-header {
            padding: 12px 14px;
        }
        .page-header .page-title {
            font-size: 1rem;
        }
        .info-card {
            padding: 10px 12px;
        }
        .info-value {
            font-size: 0.8rem;
        }
        .status-badge {
            font-size: 0.7rem;
            padding: 3px 10px;
        }
        .branch-tag {
            font-size: 0.6rem;
            padding: 2px 10px;
        }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .summary-header, .info-card { 
            border: 1px solid #ddd !important; 
            box-shadow: none !important; 
            page-break-inside: avoid;
        }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
        .summary-header { background: white !important; }
        .info-card { background: white !important; }
        .status-badge { background: #0B5ED7 !important; color: white !important; }
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

    console.log('%c📅 Appointment Details - <?= htmlspecialchars($appointment['patient_name']) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Status: <?= ucfirst($appointment['status'] ?? 'Scheduled') ?>', 'font-size:12px; color:#64748B;');
    console.log('%c✅ View appointment page loaded', 'font-size:12px; color:#059669;');
</script>

</body>
</html>