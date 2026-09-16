<?php
// ================================================================
// FILE: frontend/pages/admin/view_appointment.php
// SUPER ADMIN - VIEW APPOINTMENT DETAILS
// ✅ BLUE THEME ONLY
// ✅ View, Edit, Delete buttons (Start Consultation & Print removed)
// ✅ View Patient → patient_details.php
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET APPOINTMENT ID
// ================================================================
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($appointment_id <= 0) {
    header('Location: appointments.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH APPOINTMENT DETAILS
// ================================================================
$appointment = null;

try {
    $sql = "
        SELECT 
            a.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            p.email as patient_email,
            p.gender as patient_gender,
            p.date_of_birth as patient_dob,
            p.address as patient_address,
            p.blood_group as patient_blood,
            p.allergies as patient_allergies,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            u.phone as doctor_phone,
            u.email as doctor_email,
            b.name as branch_name,
            b.location as branch_location,
            b.phone as branch_phone,
            b.email as branch_email,
            cb.full_name as created_by_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        LEFT JOIN branches b ON a.branch_id = b.id
        LEFT JOIN users cb ON a.created_by = cb.id
        WHERE a.id = ?
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        header('Location: appointments.php?branch=' . urlencode($selected_branch_id) . '&error=not_found');
        exit;
    }
} catch (Exception $e) {
    die("Error fetching appointment: " . $e->getMessage());
}

// ================================================================
// STATUS BADGE HELPER
// ================================================================
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'scheduled': return 'badge-blue-2';
        case 'confirmed': return 'badge-blue-1';
        case 'completed': return 'badge-blue-3';
        case 'cancelled': return 'badge-blue-5';
        case 'pending': return 'badge-blue-4';
        default: return 'badge-blue-2';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'scheduled': return 'fa-clock';
        case 'confirmed': return 'fa-check-circle';
        case 'completed': return 'fa-check-double';
        case 'cancelled': return 'fa-times-circle';
        case 'pending': return 'fa-hourglass-half';
        default: return 'fa-clock';
    }
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<style>
/* ================================================================
   BLUE THEME ONLY
   ================================================================ */
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #6EA8FE;
    --primary-bg: #E8F0FE;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary-bg: #1E3A5F;
}

/* PAGE HEADER */
.page-header-custom {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-radius: 16px;
    padding: 24px 32px;
    margin-bottom: 28px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
    color: white;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin: 0;
}

.page-header-custom .page-title i {
    color: rgba(255,255,255,0.85);
}

.page-header-custom .badge-branch {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.page-header-custom .header-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.page-header-custom .btn-header {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s;
    cursor: pointer;
}

.page-header-custom .btn-header:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

.page-header-custom .btn-header.primary {
    background: white;
    color: #0B5ED7;
    border: none;
}

.page-header-custom .btn-header.primary:hover {
    background: #F0F7FF;
}

.page-header-custom .btn-header.danger {
    background: rgba(220,38,38,0.85);
    border-color: rgba(220,38,38,0.9);
}

.page-header-custom .btn-header.danger:hover {
    background: #DC2626;
}

/* CARD */
.card {
    background: var(--bg-card);
    border-radius: 16px;
    padding: 24px 28px;
    border: 2px solid var(--border-color);
    margin-bottom: 20px;
    transition: all 0.3s;
}

.card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1rem;
    font-weight: 700;
    color: var(--primary);
    padding-bottom: 12px;
    margin-bottom: 18px;
    border-bottom: 2px solid var(--primary-bg);
}

.section-title i {
    width: 34px;
    height: 34px;
    background: var(--primary);
    color: white;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

/* INFO GRID */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
}

.info-item {
    padding: 12px 16px;
    background: var(--primary-bg);
    border-radius: 10px;
    border-left: 4px solid var(--primary);
    transition: all 0.3s;
}

.info-item:hover {
    transform: translateX(3px);
}

.info-item .info-label {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: block;
    margin-bottom: 4px;
}

.info-item .info-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    display: block;
    word-break: break-word;
}

.info-item .info-value.big {
    font-size: 1rem;
    color: var(--primary);
}

.info-item.full-width {
    grid-column: 1 / -1;
}

/* BADGE */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-blue-1 { background: #0B5ED7; color: white; }
.badge-blue-2 { background: #1A73E8; color: white; }
.badge-blue-3 { background: #0A4CA8; color: white; }
.badge-blue-4 { background: #6EA8FE; color: white; }
.badge-blue-5 { background: #1E40AF; color: white; }

/* PATIENT AVATAR */
.patient-card {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 18px 22px;
    background: linear-gradient(135deg, #E8F0FE, #DBEAFE);
    border-radius: 14px;
    border: 2px solid var(--primary-light);
}

[data-theme="dark"] .patient-card {
    background: linear-gradient(135deg, #1E3A5F, #1E40AF);
    border-color: #3B82F6;
}

.patient-avatar {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.8rem;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    border: 3px solid white;
}

.patient-info h2 {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 4px 0;
}

.patient-info p {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin: 2px 0;
}

/* TIMELINE */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 5px;
    bottom: 5px;
    width: 3px;
    background: var(--primary-bg);
    border-radius: 3px;
}

.timeline-item {
    position: relative;
    padding: 10px 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -30px;
    top: 16px;
    width: 19px;
    height: 19px;
    background: var(--primary);
    border: 4px solid var(--bg-card);
    border-radius: 50%;
    box-shadow: 0 0 0 3px var(--primary-bg);
}

.timeline-item .timeline-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 2px 0;
}

.timeline-item .timeline-date {
    font-size: 0.7rem;
    color: var(--text-secondary);
}

/* ACTION BUTTONS */
.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
}

.action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 18px 12px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.8rem;
    transition: all 0.3s;
    border: 2px solid transparent;
    cursor: pointer;
    min-height: 100px;
    text-align: center;
}

.action-btn i {
    font-size: 1.5rem;
    margin-bottom: 8px;
}

.action-btn .btn-label {
    font-size: 0.75rem;
    font-weight: 700;
}

.action-btn .btn-sublabel {
    font-size: 0.62rem;
    font-weight: 400;
    opacity: 0.85;
    margin-top: 2px;
}

.action-btn-blue-1 {
    background: #E8F0FE;
    color: #0B5ED7;
    border-color: #6EA8FE;
}
.action-btn-blue-1:hover {
    background: #0B5ED7;
    color: white;
    border-color: #0B5ED7;
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.3);
}

.action-btn-blue-2 {
    background: #DBEAFE;
    color: #0A4CA8;
    border-color: #93C5FD;
}
.action-btn-blue-2:hover {
    background: #0A4CA8;
    color: white;
    border-color: #0A4CA8;
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(10, 76, 168, 0.3);
}

.action-btn-blue-3 {
    background: #E0F2FE;
    color: #0B3D8A;
    border-color: #7DD3FC;
}
.action-btn-blue-3:hover {
    background: #0B3D8A;
    color: white;
    border-color: #0B3D8A;
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(11, 61, 138, 0.3);
}

.action-btn-danger {
    background: #FEE2E2;
    color: #DC2626;
    border-color: #FCA5A5;
}
.action-btn-danger:hover {
    background: #DC2626;
    color: white;
    border-color: #DC2626;
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.3);
}

/* FOOTER */
.footer {
    padding: 14px 0;
    border-top: 1px solid var(--border-color);
    margin-top: 20px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand { color: var(--primary); font-weight: 600; }

/* RESPONSIVE */
@media (max-width: 768px) {
    .card { padding: 16px; }
    .page-header-custom { padding: 18px 20px; }
    .page-header-custom .page-title { font-size: 1.2rem; }
    .header-actions { width: 100%; }
    .header-actions .btn-header { flex: 1; justify-content: center; }
    .info-grid { grid-template-columns: 1fr; }
    .patient-card { flex-direction: column; text-align: center; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-calendar-check"></i> Appointment Details
                <span class="badge-branch">
                    <i class="fas fa-hashtag"></i> #<?= $appointment['id'] ?>
                </span>
                <span class="badge-branch">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($appointment['branch_name'] ?? 'N/A') ?>
                </span>
            </h1>
            <p style="color:rgba(255,255,255,0.85); font-size:0.9rem; margin-top:4px;">
                View complete appointment information
            </p>
        </div>
        <div class="header-actions">
            <a href="appointments.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="edit_appointment.php?id=<?= $appointment['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="?id=<?= $appointment['id'] ?>&branch=<?= $selected_branch_id ?>&delete=1" 
               class="btn-header danger"
               onclick="return confirm('⚠️ Delete this appointment?\n\nPatient: <?= htmlspecialchars(addslashes($appointment['patient_name'] ?? 'N/A')) ?>\nDoctor: Dr. <?= htmlspecialchars(addslashes($appointment['doctor_name'] ?? 'N/A')) ?>\nDate: <?= date('M d, Y h:i A', strtotime($appointment['appointment_date'] ?? 'now')) ?>\n\nThis action cannot be undone!');">
                <i class="fas fa-trash"></i> Delete
            </a>
        </div>
    </div>

    <!-- HANDLE DELETE -->
    <?php
    if (isset($_GET['delete']) && $_GET['delete'] == 1) {
        try {
            $stmt = $db->prepare("DELETE FROM appointments WHERE id = ?");
            $stmt->execute([$appointment_id]);
            
            try {
                $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'appointment_deleted', ?, NOW())");
                $log_stmt->execute([$user_id, $user_branch_id, "Deleted appointment #$appointment_id from view page"]);
            } catch (Exception $e) {}
            
            header('Location: appointments.php?branch=' . urlencode($selected_branch_id) . '&deleted=1');
            exit;
        } catch (Exception $e) {
            echo '<div style="background:#FEE2E2;color:#991B1B;padding:14px 20px;border-radius:12px;margin-bottom:16px;border:2px solid #FCA5A5;"><i class="fas fa-exclamation-circle"></i> Failed to delete: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    ?>

    <!-- PATIENT CARD -->
    <div class="patient-card" style="margin-bottom:20px;">
        <div class="patient-avatar">
            <?= strtoupper(substr($appointment['patient_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="patient-info" style="flex:1;">
            <h2><?= htmlspecialchars($appointment['patient_name'] ?? 'N/A') ?></h2>
            <p>
                <i class="fas fa-id-card"></i> <?= htmlspecialchars($appointment['patient_code'] ?? 'N/A') ?>
                <?php if (!empty($appointment['patient_phone'])): ?>
                    <span style="margin-left:12px;"><i class="fas fa-phone"></i> <?= htmlspecialchars($appointment['patient_phone']) ?></span>
                <?php endif; ?>
            </p>
            <p>
                <?php if (!empty($appointment['patient_gender'])): ?>
                    <i class="fas fa-venus-mars"></i> <?= htmlspecialchars($appointment['patient_gender']) ?>
                <?php endif; ?>
                <?php if (!empty($appointment['patient_dob'])): ?>
                    <span style="margin-left:12px;"><i class="fas fa-birthday-cake"></i> <?= date('M d, Y', strtotime($appointment['patient_dob'])) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div style="text-align:right;">
            <span class="badge <?= getStatusBadgeClass($appointment['status'] ?? 'scheduled') ?>" style="font-size:0.85rem;padding:10px 22px;">
                <i class="fas <?= getStatusIcon($appointment['status'] ?? 'scheduled') ?>"></i>
                <?= ucfirst($appointment['status'] ?? 'Scheduled') ?>
            </span>
        </div>
    </div>

    <!-- APPOINTMENT DETAILS -->
    <div class="card">
        <div class="section-title">
            <i class="fas fa-calendar-alt"></i>
            <span>Appointment Information</span>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Appointment Date</span>
                <span class="info-value big">
                    <i class="fas fa-calendar"></i> 
                    <?= !empty($appointment['appointment_date']) ? date('l, F d, Y', strtotime($appointment['appointment_date'])) : 'N/A' ?>
                </span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Appointment Time</span>
                <span class="info-value big">
                    <i class="fas fa-clock"></i> 
                    <?= !empty($appointment['appointment_date']) ? date('h:i A', strtotime($appointment['appointment_date'])) : 'N/A' ?>
                </span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Duration</span>
                <span class="info-value">
                    <?php 
                        $duration = $appointment['duration_minutes'] ?? $appointment['duration'] ?? 30;
                        echo (int)$duration . ' minutes';
                    ?>
                </span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Appointment Type</span>
                <span class="info-value">
                    <?php 
                        $type = $appointment['appointment_type'] ?? $appointment['visit_type'] ?? 'consultation';
                        $type_labels = [
                            'consultation' => '🩺 Consultation',
                            'follow_up' => '🔄 Follow-up',
                            'checkup' => '✅ Check-up',
                            'emergency' => '🚨 Emergency',
                            'procedure' => '💉 Procedure',
                            'lab_review' => '🧪 Lab Review',
                            'vaccination' => '💊 Vaccination',
                            'other' => '📋 Other',
                            'new' => '🆕 New Patient'
                        ];
                        echo $type_labels[$type] ?? ucfirst($type);
                    ?>
                </span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Branch</span>
                <span class="info-value">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($appointment['branch_name'] ?? 'N/A') ?>
                </span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Created By</span>
                <span class="info-value">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($appointment['created_by_name'] ?? 'System') ?>
                </span>
            </div>
            
            <div class="info-item full-width">
                <span class="info-label">Purpose / Reason</span>
                <span class="info-value">
                    <?= !empty($appointment['purpose']) ? htmlspecialchars($appointment['purpose']) : '<span style="color:#94A3B8;font-style:italic;">No purpose specified</span>' ?>
                </span>
            </div>
            
            <?php if (!empty($appointment['notes'])): ?>
            <div class="info-item full-width">
                <span class="info-label">Notes / Instructions</span>
                <span class="info-value" style="font-weight:400;line-height:1.6;">
                    <?= nl2br(htmlspecialchars($appointment['notes'])) ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- DOCTOR INFORMATION -->
    <div class="card">
        <div class="section-title">
            <i class="fas fa-user-md"></i>
            <span>Doctor Information</span>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Doctor Name</span>
                <span class="info-value big">
                    <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($appointment['doctor_name'] ?? 'N/A') ?>
                </span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Specialty</span>
                <span class="info-value">
                    <?= htmlspecialchars($appointment['doctor_specialty'] ?? 'General Medicine') ?>
                </span>
            </div>
            
            <?php if (!empty($appointment['doctor_phone'])): ?>
            <div class="info-item">
                <span class="info-label">Phone</span>
                <span class="info-value">
                    <i class="fas fa-phone"></i> <?= htmlspecialchars($appointment['doctor_phone']) ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($appointment['doctor_email'])): ?>
            <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value">
                    <i class="fas fa-envelope"></i> <?= htmlspecialchars($appointment['doctor_email']) ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PATIENT DETAILS -->
    <div class="card">
        <div class="section-title">
            <i class="fas fa-user"></i>
            <span>Patient Details</span>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Full Name</span>
                <span class="info-value big"><?= htmlspecialchars($appointment['patient_name'] ?? 'N/A') ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Patient ID</span>
                <span class="info-value" style="font-family:monospace;"><?= htmlspecialchars($appointment['patient_code'] ?? 'N/A') ?></span>
            </div>
            
            <?php if (!empty($appointment['patient_phone'])): ?>
            <div class="info-item">
                <span class="info-label">Phone</span>
                <span class="info-value"><i class="fas fa-phone"></i> <?= htmlspecialchars($appointment['patient_phone']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($appointment['patient_email'])): ?>
            <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value"><i class="fas fa-envelope"></i> <?= htmlspecialchars($appointment['patient_email']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($appointment['patient_gender'])): ?>
            <div class="info-item">
                <span class="info-label">Gender</span>
                <span class="info-value"><?= htmlspecialchars($appointment['patient_gender']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($appointment['patient_dob'])): ?>
            <div class="info-item">
                <span class="info-label">Date of Birth</span>
                <span class="info-value"><?= date('M d, Y', strtotime($appointment['patient_dob'])) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($appointment['patient_blood'])): ?>
            <div class="info-item">
                <span class="info-label">Blood Group</span>
                <span class="info-value" style="color:#DC2626;font-weight:700;"><?= htmlspecialchars($appointment['patient_blood']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($appointment['patient_allergies'])): ?>
            <div class="info-item full-width">
                <span class="info-label">⚠️ Allergies</span>
                <span class="info-value" style="color:#DC2626;"><?= htmlspecialchars($appointment['patient_allergies']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($appointment['patient_address'])): ?>
            <div class="info-item full-width">
                <span class="info-label">Address</span>
                <span class="info-value" style="font-weight:400;"><?= htmlspecialchars($appointment['patient_address']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TIMELINE -->
    <div class="card">
        <div class="section-title">
            <i class="fas fa-history"></i>
            <span>Activity Timeline</span>
        </div>
        
        <div class="timeline">
            <div class="timeline-item">
                <p class="timeline-title">
                    <i class="fas fa-plus-circle" style="color:#0B5ED7;"></i>
                    Appointment Created
                </p>
                <p class="timeline-date">
                    <?= !empty($appointment['created_at']) ? date('l, F d, Y \a\t h:i A', strtotime($appointment['created_at'])) : 'N/A' ?>
                    <?php if (!empty($appointment['created_by_name'])): ?>
                        • by <?= htmlspecialchars($appointment['created_by_name']) ?>
                    <?php endif; ?>
                </p>
            </div>
            
            <?php if (!empty($appointment['updated_at']) && $appointment['updated_at'] !== $appointment['created_at']): ?>
            <div class="timeline-item">
                <p class="timeline-title">
                    <i class="fas fa-edit" style="color:#1A73E8;"></i>
                    Last Updated
                </p>
                <p class="timeline-date">
                    <?= date('l, F d, Y \a\t h:i A', strtotime($appointment['updated_at'])) ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if (($appointment['status'] ?? '') === 'confirmed' && !empty($appointment['confirmed_at'])): ?>
            <div class="timeline-item">
                <p class="timeline-title">
                    <i class="fas fa-check-circle" style="color:#059669;"></i>
                    Appointment Confirmed
                </p>
                <p class="timeline-date">
                    <?= date('l, F d, Y \a\t h:i A', strtotime($appointment['confirmed_at'])) ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if (($appointment['status'] ?? '') === 'completed' && !empty($appointment['completed_at'])): ?>
            <div class="timeline-item">
                <p class="timeline-title">
                    <i class="fas fa-check-double" style="color:#0A4CA8;"></i>
                    Appointment Completed
                </p>
                <p class="timeline-date">
                    <?= date('l, F d, Y \a\t h:i A', strtotime($appointment['completed_at'])) ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if (($appointment['status'] ?? '') === 'cancelled' && !empty($appointment['cancelled_at'])): ?>
            <div class="timeline-item">
                <p class="timeline-title">
                    <i class="fas fa-times-circle" style="color:#DC2626;"></i>
                    Appointment Cancelled
                </p>
                <p class="timeline-date">
                    <?= date('l, F d, Y \a\t h:i A', strtotime($appointment['cancelled_at'])) ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="card">
        <div class="section-title">
            <i class="fas fa-bolt"></i>
            <span>Quick Actions</span>
        </div>
        
        <div class="action-grid">
            <a href="edit_appointment.php?id=<?= $appointment['id'] ?>&branch=<?= $selected_branch_id ?>" class="action-btn action-btn-blue-1">
                <i class="fas fa-edit"></i>
                <span class="btn-label">Edit</span>
                <span class="btn-sublabel">Modify details</span>
            </a>
            
            <?php if (($appointment['status'] ?? '') === 'scheduled'): ?>
            <a href="edit_appointment.php?id=<?= $appointment['id'] ?>&branch=<?= $selected_branch_id ?>&status=confirmed" class="action-btn action-btn-blue-2">
                <i class="fas fa-check-circle"></i>
                <span class="btn-label">Confirm</span>
                <span class="btn-sublabel">Mark as confirmed</span>
            </a>
            <?php endif; ?>
            
            <?php if (!empty($appointment['patient_id'])): ?>
            <!-- ✅ VIEW PATIENT → patient_details.php -->
            <a href="patient_details.php?id=<?= $appointment['patient_id'] ?>&branch=<?= $selected_branch_id ?>" class="action-btn action-btn-blue-3">
                <i class="fas fa-user-circle"></i>
                <span class="btn-label">View Patient</span>
                <span class="btn-sublabel">Full profile</span>
            </a>
            <?php endif; ?>
            
            <a href="?id=<?= $appointment['id'] ?>&branch=<?= $selected_branch_id ?>&delete=1" 
               class="action-btn action-btn-danger"
               onclick="return confirm('⚠️ Delete this appointment?\n\nThis action cannot be undone!');">
                <i class="fas fa-trash"></i>
                <span class="btn-label">Delete</span>
                <span class="btn-sublabel">Remove permanently</span>
            </a>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            Appointment #<?= $appointment['id'] ?>
            <span>|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
// ================================================================
// UPDATE FOOTER TIME
// ================================================================
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

// ================================================================
// ESC KEY TO GO BACK
// ================================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        window.location.href = 'appointments.php?branch=<?= $selected_branch_id ?>';
    }
});

console.log('%c📅 Admin - View Appointment (BLUE THEME)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Start Consultation button removed', 'font-size:12px;color:#34D399;');
console.log('%c✅ Print button removed', 'font-size:12px;color:#34D399;');
console.log('%c✅ View Patient → patient_details.php', 'font-size:12px;color:#34D399;');
console.log('%c⌨️ Press ESC to go back to appointments list', 'font-size:12px;color:#64748B;');
</script>

</body>
</html>