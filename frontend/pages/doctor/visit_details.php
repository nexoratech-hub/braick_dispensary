<?php
// ================================================================
// FILE: frontend/pages/doctor/visit_details.php
// DOCTOR - VIEW COMPLETE VISIT DETAILS
// WITH VITAL SIGNS, PRESCRIPTIONS, LAB TESTS, PROCEDURES, BILLS
// PATIENT NAME IN BLUE COLOR
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Dr. John Mushi (ID: 5)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['doctor_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['specialty'] = 'General Medicine';
}

$doctor_id = $_SESSION['user_id'] ?? 5;
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';

// ================================================================
// GET VISIT ID
// ================================================================
$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($visit_id <= 0) {
    header('Location: dashboard.php?error=invalid_visit');
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = getDB();
    
    // ================================================================
    // GET VISIT DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT v.*, 
               p.id as patient_id, p.full_name as patient_name, p.patient_id as patient_code,
               p.phone, p.email, p.date_of_birth, p.gender, p.address,
               p.blood_group, p.allergies,
               u.id as doctor_id, u.full_name as doctor_name, u.specialty,
               r.full_name as receptionist_name,
               b.name as branch_name
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN users r ON v.receptionist_id = r.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.id = ? AND v.branch_id = ?
    ");
    $stmt->execute([$visit_id, $user_branch_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        header('Location: dashboard.php?error=visit_not_found');
        exit;
    }
    
    // Check if doctor is assigned to this visit or is admin
    if ($visit['doctor_id'] != $doctor_id && $_SESSION['role'] !== 'admin') {
        header('Location: dashboard.php?error=unauthorized');
        exit;
    }
    
    // ================================================================
    // GET VITAL SIGNS FOR THIS VISIT
    // ================================================================
    $vital_signs = null;
    $stmt = $db->prepare("
        SELECT vs.*, u.full_name as recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.visit_id = ?
        ORDER BY vs.recorded_at DESC
        LIMIT 1
    ");
    $stmt->execute([$visit_id]);
    $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no vital signs for this visit, try to get latest for patient
    if (!$vital_signs && $visit['patient_id']) {
        $stmt = $db->prepare("
            SELECT vs.*, u.full_name as recorded_by_name
            FROM vital_signs vs
            LEFT JOIN users u ON vs.recorded_by = u.id
            WHERE vs.patient_id = ?
            ORDER BY vs.recorded_at DESC
            LIMIT 1
        ");
        $stmt->execute([$visit['patient_id']]);
        $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET ALL VITAL SIGNS HISTORY
    // ================================================================
    $vital_signs_history = [];
    $stmt = $db->prepare("
        SELECT vs.*, u.full_name as recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.patient_id = ?
        ORDER BY vs.recorded_at DESC
        LIMIT 10
    ");
    $stmt->execute([$visit['patient_id']]);
    $vital_signs_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET PRESCRIPTIONS FOR THIS VISIT
    // ================================================================
    $prescriptions = [];
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as doctor_name
        FROM prescriptions p
        LEFT JOIN users u ON p.doctor_id = u.id
        WHERE p.visit_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get prescription items
    $prescription_items = [];
    foreach ($prescriptions as $pres) {
        $stmt = $db->prepare("
            SELECT * FROM prescription_items 
            WHERE prescription_id = ?
            ORDER BY id
        ");
        $stmt->execute([$pres['id']]);
        $prescription_items[$pres['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET LAB REQUESTS FOR THIS VISIT
    // ================================================================
    $lab_requests = [];
    $stmt = $db->prepare("
        SELECT lr.*, 
               u.full_name as doctor_name,
               (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id) as test_count,
               (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id AND status = 'completed') as completed_count
        FROM lab_requests lr
        LEFT JOIN users u ON lr.doctor_id = u.id
        WHERE lr.visit_id = ?
        ORDER BY lr.requested_at DESC
    ");
    $stmt->execute([$visit_id]);
    $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get lab request items
    $lab_items = [];
    foreach ($lab_requests as $lab) {
        $stmt = $db->prepare("
            SELECT * FROM lab_request_items 
            WHERE request_id = ?
            ORDER BY id
        ");
        $stmt->execute([$lab['id']]);
        $lab_items[$lab['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET PROCEDURES FOR THIS VISIT (from bill_items)
    // ================================================================
    $procedures = [];
    $stmt = $db->prepare("
        SELECT bi.*, pb.bill_number, pb.status as bill_status
        FROM bill_items bi
        JOIN patient_bills pb ON bi.bill_id = pb.id
        WHERE pb.visit_id = ? AND bi.item_type = 'procedure'
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET MEDICATIONS FOR THIS VISIT (from bill_items)
    // ================================================================
    $medications = [];
    $stmt = $db->prepare("
        SELECT bi.*, pb.bill_number, pb.status as bill_status
        FROM bill_items bi
        JOIN patient_bills pb ON bi.bill_id = pb.id
        WHERE pb.visit_id = ? AND bi.item_type = 'medication'
        ORDER BY bi.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET BILLS FOR THIS VISIT
    // ================================================================
    $bills = [];
    $stmt = $db->prepare("
        SELECT pb.*, u.full_name as created_by_name
        FROM patient_bills pb
        LEFT JOIN users u ON pb.created_by = u.id
        WHERE pb.visit_id = ?
        ORDER BY pb.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get bill items for each bill
    $bill_items = [];
    foreach ($bills as $bill) {
        $stmt = $db->prepare("
            SELECT * FROM bill_items 
            WHERE bill_id = ?
            ORDER BY id
        ");
        $stmt->execute([$bill['id']]);
        $bill_items[$bill['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET UNREAD NOTIFICATIONS
    // ================================================================
    $unread_notifications = 0;
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$doctor_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $visit = null;
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getStatusBadgeColor($status) {
    $colors = [
        'pending' => '#D97706',
        'assigned' => '#0B5ED7',
        'with_doctor' => '#7C3AED',
        'completed' => '#059669',
        'cancelled' => '#DC2626',
        'lab_test' => '#0B5ED7',
        'prescribed' => '#D97706'
    ];
    return $colors[$status] ?? '#64748B';
}

function getStatusText($status) {
    return ucfirst(str_replace('_', ' ', $status));
}

function getStatusIcon($status) {
    $icons = [
        'pending' => '⏳',
        'assigned' => '👨‍⚕️',
        'with_doctor' => '🩺',
        'completed' => '✅',
        'cancelled' => '❌',
        'lab_test' => '🧪',
        'prescribed' => '💊'
    ];
    return $icons[$status] ?? '📌';
}

function getVisitTypeLabel($type) {
    $types = [
        'new' => 'New Patient',
        'follow-up' => 'Follow-up',
        'emergency' => 'Emergency'
    ];
    return $types[$type] ?? ucfirst($type);
}

function formatDateTime($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y h:i A', strtotime($date));
}

function formatCurrency($amount) {
    if (empty($amount)) return 'TSh 0';
    return 'TSh ' . number_format($amount, 0);
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<style>
    /* ================================================================
       VISIT DETAILS STYLES
       ================================================================ */
    
    .detail-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        box-shadow: var(--shadow-sm);
    }
    
    .detail-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    
    .detail-card .card-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .detail-card .card-title i {
        color: var(--primary);
    }
    
    .detail-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
    }
    
    .detail-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .detail-value .text-muted {
        color: var(--text-secondary);
        font-weight: 400;
    }
    
    /* Patient Name - Blue Color */
    .patient-name-blue {
        color: #0B5ED7 !important;
    }
    
    [data-theme="dark"] .patient-name-blue {
        color: #6EA8FE !important;
    }
    
    /* ================================================================
       VITAL SIGNS - 6 SIGNS
       ================================================================ */
    .vital-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }
    
    .vital-item {
        background: var(--bg-body);
        border-radius: 12px;
        padding: 14px 16px;
        text-align: center;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .vital-item:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .vital-item .vital-icon {
        font-size: 1.4rem;
        display: block;
        margin-bottom: 4px;
    }
    
    .vital-item .vital-value {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    
    .vital-item .vital-label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .vital-item .vital-normal {
        font-size: 0.55rem;
        color: var(--success);
    }
    
    .vital-item.bmi-item {
        background: var(--primary-bg);
        border-color: var(--primary);
    }
    
    .vital-item.bmi-item .vital-value {
        color: var(--primary);
    }
    
    /* ================================================================
       STATUS BADGE
       ================================================================ */
    .status-badge {
        display: inline-block;
        padding: 4px 16px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        color: white;
    }
    
    .status-badge.pending { background: #D97706; }
    .status-badge.assigned { background: #0B5ED7; }
    .status-badge.with_doctor { background: #7C3AED; }
    .status-badge.completed { background: #059669; }
    .status-badge.cancelled { background: #DC2626; }
    .status-badge.lab_test { background: #0B5ED7; }
    .status-badge.prescribed { background: #D97706; }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-primary {
        background: var(--primary);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(11, 94, 215, 0.4);
    }
    
    .btn-success {
        background: #059669;
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    
    .btn-success:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(5, 150, 105, 0.4);
    }
    
    .btn-warning {
        background: #D97706;
        color: white;
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
    }
    
    .btn-warning:hover {
        background: #B45309;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(217, 119, 6, 0.4);
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
    
    .btn-sm {
        padding: 6px 14px;
        font-size: 0.78rem;
        border-radius: 6px;
    }
    
    .btn-danger {
        background: #DC2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }
    
    .btn-danger:hover {
        background: #B91C1C;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(220, 38, 38, 0.4);
    }
    
    /* ================================================================
       ITEM TAGS
       ================================================================ */
    .item-tag {
        display: inline-block;
        border: 1px solid #0B5ED7;
        border-radius: 4px;
        padding: 2px 10px;
        font-size: 0.75rem;
        margin: 2px 4px 2px 0;
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    .item-tag.paid {
        border-color: #059669;
        background: #D1FAE5;
        color: #059669;
    }
    
    .item-tag.pending {
        border-color: #D97706;
        background: #FEF3C7;
        color: #D97706;
    }
    
    .item-tag.completed {
        border-color: #059669;
        background: #D1FAE5;
        color: #059669;
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
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
    }
    
    .toast-custom.show {
        transform: translateY(0);
        opacity: 1;
    }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #DC2626; }
    .toast-custom.info { background: #0B5ED7; }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .vital-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .detail-card {
            padding: 14px 16px;
        }
        .btn {
            width: 100%;
            justify-content: center;
        }
        .action-buttons {
            flex-direction: column;
        }
    }
    
    @media (max-width: 480px) {
        .vital-grid {
            grid-template-columns: 1fr 1fr;
        }
        .detail-card {
            padding: 10px 12px;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($visit): ?>
    
    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clinic-medical mr-2" style="color: var(--primary);"></i> 
                Visit Details
                <span class="text-sm font-normal text-gray-400 ml-2">
                    #<?= htmlspecialchars($visit['visit_number']) ?>
                </span>
            </h1>
            <p class="page-subtitle">
                View complete visit information
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <!-- ============================================================ -->
                <!-- PATIENT NAME - BLUE COLOR -->
                <!-- ============================================================ -->
                <span class="ml-2 inline-flex bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 px-3 py-1 rounded-full text-xs border border-blue-200 dark:border-blue-700 font-semibold">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($visit['patient_name']) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISIT HEADER -->
    <!-- ================================================================ -->
    <div class="detail-card mb-4" style="border-left: 4px solid <?= getStatusBadgeColor($visit['status']) ?>;">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <!-- ============================================================ -->
                    <!-- PATIENT NAME - BLUE COLOR (Large) -->
                    <!-- ============================================================ -->
                    <h2 class="text-xl font-bold text-blue-600 dark:text-blue-400">
                        <?= htmlspecialchars($visit['patient_name']) ?>
                    </h2>
                    <span class="status-badge <?= $visit['status'] ?? 'pending' ?>">
                        <?= getStatusIcon($visit['status']) ?> <?= getStatusText($visit['status']) ?>
                    </span>
                    <span class="text-sm text-gray-400">
                        <i class="fas fa-tag mr-1"></i>
                        <?= getVisitTypeLabel($visit['visit_type'] ?? 'new') ?>
                    </span>
                </div>
                <div class="text-sm text-gray-500 mt-1">
                    <i class="fas fa-id-card mr-1"></i> <?= htmlspecialchars($visit['patient_code']) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-calendar-alt mr-1"></i> <?= formatDateTime($visit['created_at']) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-user-md mr-1"></i> Dr. <?= htmlspecialchars($visit['doctor_name']) ?>
                </div>
            </div>
            <div class="text-sm text-gray-400">
                <span>Created by: <?= htmlspecialchars($visit['receptionist_name'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION & VITAL SIGNS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        
        <!-- Patient Information -->
        <div class="detail-card">
            <div class="card-title">
                <i class="fas fa-user"></i>
                Patient Information
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p class="detail-label">Full Name</p>
                    <!-- ============================================================ -->
                    <!-- PATIENT NAME - BLUE COLOR -->
                    <!-- ============================================================ -->
                    <p class="detail-value text-blue-600 dark:text-blue-400 font-semibold">
                        <?= htmlspecialchars($visit['patient_name']) ?>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Patient ID</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['patient_code']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Gender</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['gender'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Age</p>
                    <p class="detail-value"><?= calculateAge($visit['date_of_birth']) ?> years</p>
                </div>
                <div>
                    <p class="detail-label">Phone</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Email</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['email'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Blood Group</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Allergies</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></p>
                </div>
                <div class="col-span-2">
                    <p class="detail-label">Address</p>
                    <p class="detail-value"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></p>
                </div>
            </div>
        </div>
        
        <!-- Vital Signs -->
        <div class="detail-card">
            <div class="card-title">
                <i class="fas fa-heartbeat" style="color:#DC2626;"></i>
                6 Vital Signs
                <?php if ($vital_signs): ?>
                    <span class="text-xs font-normal text-green-500 ml-auto">
                        <i class="fas fa-check-circle"></i> Recorded: <?= date('d/m/Y H:i', strtotime($vital_signs['recorded_at'])) ?>
                    </span>
                <?php else: ?>
                    <span class="text-xs font-normal text-gray-400 ml-auto">
                        <i class="fas fa-clock"></i> Not recorded
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if ($vital_signs): ?>
                <div class="vital-grid">
                    <div class="vital-item">
                        <span class="vital-icon">💓</span>
                        <div class="vital-value">
                            <?= $vital_signs['blood_pressure_systolic'] ?: '—' ?>
                            <?php if ($vital_signs['blood_pressure_systolic'] && $vital_signs['blood_pressure_diastolic']): ?>
                                /<?= $vital_signs['blood_pressure_diastolic'] ?>
                            <?php endif; ?>
                        </div>
                        <span class="vital-label">Blood Pressure</span>
                        <span class="vital-normal">Normal: 120/80</span>
                    </div>
                    
                    <div class="vital-item">
                        <span class="vital-icon">🌡️</span>
                        <div class="vital-value">
                            <?= $vital_signs['temperature'] ?: '—' ?>
                            <?php if ($vital_signs['temperature']): ?>°C<?php endif; ?>
                        </div>
                        <span class="vital-label">Temperature</span>
                        <span class="vital-normal">Normal: 36.5-37.5</span>
                    </div>
                    
                    <div class="vital-item">
                        <span class="vital-icon">❤️</span>
                        <div class="vital-value">
                            <?= $vital_signs['pulse_rate'] ?: '—' ?>
                            <?php if ($vital_signs['pulse_rate']): ?>bpm<?php endif; ?>
                        </div>
                        <span class="vital-label">Pulse Rate</span>
                        <span class="vital-normal">Normal: 60-100</span>
                    </div>
                    
                    <div class="vital-item">
                        <span class="vital-icon">⚖️</span>
                        <div class="vital-value">
                            <?= $vital_signs['weight'] ?: '—' ?>
                            <?php if ($vital_signs['weight']): ?>kg<?php endif; ?>
                        </div>
                        <span class="vital-label">Weight</span>
                        <span class="vital-normal">Recorded</span>
                    </div>
                    
                    <div class="vital-item">
                        <span class="vital-icon">📏</span>
                        <div class="vital-value">
                            <?= $vital_signs['height'] ?: '—' ?>
                            <?php if ($vital_signs['height']): ?>cm<?php endif; ?>
                        </div>
                        <span class="vital-label">Height</span>
                        <span class="vital-normal">Recorded</span>
                    </div>
                    
                    <div class="vital-item bmi-item">
                        <span class="vital-icon">📊</span>
                        <div class="vital-value">
                            <?= $vital_signs['bmi'] ?: '—' ?>
                            <?php if ($vital_signs['bmi']): ?>kg/m²<?php endif; ?>
                        </div>
                        <span class="vital-label">BMI</span>
                        <?php if ($vital_signs['bmi']): 
                            $bmi = $vital_signs['bmi'];
                            $category = 'Normal';
                            $color = '#059669';
                            if ($bmi < 18.5) { $category = 'Underweight'; $color = '#D97706'; }
                            elseif ($bmi < 25) { $category = 'Normal'; $color = '#059669'; }
                            elseif ($bmi < 30) { $category = 'Overweight'; $color = '#D97706'; }
                            else { $category = 'Obese'; $color = '#DC2626'; }
                        ?>
                            <span class="vital-normal" style="color:<?= $color ?>;"><?= $category ?></span>
                        <?php else: ?>
                            <span class="vital-normal">Normal: 18.5-24.9</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($vital_signs['notes']): ?>
                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-400">Notes:</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300"><?= htmlspecialchars($vital_signs['notes']) ?></p>
                        <p class="text-xs text-gray-400 mt-1">
                            Recorded by: <?= htmlspecialchars($vital_signs['recorded_by_name'] ?? 'Unknown') ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <!-- Vital Signs History -->
                <?php if (count($vital_signs_history) > 1): ?>
                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <details>
                            <summary class="text-sm font-medium text-gray-600 cursor-pointer hover:text-primary">
                                <i class="fas fa-history mr-1"></i> View History (<?= count($vital_signs_history) ?> recordings)
                            </summary>
                            <div class="mt-2 overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="bg-gray-100 dark:bg-gray-700">
                                            <th class="p-1 text-left">Date</th>
                                            <th class="p-1 text-left">BP</th>
                                            <th class="p-1 text-left">Temp</th>
                                            <th class="p-1 text-left">Pulse</th>
                                            <th class="p-1 text-left">Weight</th>
                                            <th class="p-1 text-left">Height</th>
                                            <th class="p-1 text-left">BMI</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($vital_signs_history, 1, 5) as $vs): ?>
                                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                                <td class="p-1"><?= date('d/m/Y', strtotime($vs['recorded_at'])) ?></td>
                                                <td class="p-1"><?= $vs['blood_pressure_systolic'] ? $vs['blood_pressure_systolic'].'/'.$vs['blood_pressure_diastolic'] : '—' ?></td>
                                                <td class="p-1"><?= $vs['temperature'] ?? '—' ?></td>
                                                <td class="p-1"><?= $vs['pulse_rate'] ?? '—' ?></td>
                                                <td class="p-1"><?= $vs['weight'] ?? '—' ?></td>
                                                <td class="p-1"><?= $vs['height'] ?? '—' ?></td>
                                                <td class="p-1"><?= $vs['bmi'] ?? '—' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-heartbeat text-3xl block mb-2 text-gray-300"></i>
                    <p>No vital signs recorded</p>
                    <p class="text-sm mt-1">Vital signs will appear here once recorded</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- SYMPTOMS & COMPLAINT -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        
        <div class="detail-card">
            <div class="card-title">
                <i class="fas fa-notes-medical" style="color:#D97706;"></i>
                Symptoms
            </div>
            <?php if (!empty($visit['symptoms'])): ?>
                <p class="text-gray-700 dark:text-gray-300"><?= htmlspecialchars($visit['symptoms']) ?></p>
            <?php else: ?>
                <p class="text-gray-400">No symptoms recorded</p>
            <?php endif; ?>
        </div>
        
        <div class="detail-card">
            <div class="card-title">
                <i class="fas fa-comment-medical" style="color:#DC2626;"></i>
                Complaint / Reason
            </div>
            <?php if (!empty($visit['complaint'])): ?>
                <p class="text-gray-700 dark:text-gray-300"><?= htmlspecialchars($visit['complaint']) ?></p>
            <?php else: ?>
                <p class="text-gray-400">No complaint recorded</p>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- DIAGNOSIS & TREATMENT -->
    <!-- ================================================================ -->
    <?php if (!empty($visit['diagnosis']) || !empty($visit['treatment'])): ?>
    <div class="detail-card mb-4">
        <div class="card-title">
            <i class="fas fa-file-medical" style="color:#059669;"></i>
            Diagnosis & Treatment
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div>
                <p class="detail-label">Diagnosis</p>
                <p class="text-gray-700 dark:text-gray-300"><?= htmlspecialchars($visit['diagnosis'] ?? 'Not recorded') ?></p>
            </div>
            <div>
                <p class="detail-label">Treatment</p>
                <p class="text-gray-700 dark:text-gray-300"><?= htmlspecialchars($visit['treatment'] ?? 'Not recorded') ?></p>
            </div>
        </div>
        <?php if (!empty($visit['notes'])): ?>
            <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                <p class="detail-label">Notes</p>
                <p class="text-gray-700 dark:text-gray-300"><?= htmlspecialchars($visit['notes']) ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <?php if (count($prescriptions) > 0): ?>
    <div class="detail-card mb-4">
        <div class="card-title">
            <i class="fas fa-prescription" style="color:#D97706;"></i>
            Prescriptions
            <span class="text-sm font-normal text-gray-400">(<?= count($prescriptions) ?> prescriptions)</span>
        </div>
        
        <?php foreach ($prescriptions as $pres): ?>
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 mb-3 last:mb-0">
                <div class="flex flex-wrap justify-between items-center">
                    <div>
                        <span class="font-mono text-sm font-bold text-blue-600">#<?= htmlspecialchars($pres['prescription_number']) ?></span>
                        <span class="text-xs text-gray-400 ml-2"><?= date('d/m/Y H:i', strtotime($pres['created_at'])) ?></span>
                    </div>
                    <span class="item-tag <?= $pres['status'] === 'dispensed' ? 'paid' : 'pending' ?>">
                        <?= ucfirst($pres['status'] ?? 'Pending') ?>
                    </span>
                </div>
                
                <?php if (!empty($pres['diagnosis'])): ?>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Diagnosis: <?= htmlspecialchars($pres['diagnosis']) ?></p>
                <?php endif; ?>
                
                <?php if (isset($prescription_items[$pres['id']]) && count($prescription_items[$pres['id']]) > 0): ?>
                    <div class="mt-2">
                        <p class="text-xs text-gray-400">Medications:</p>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <?php foreach ($prescription_items[$pres['id']] as $item): ?>
                                <span class="item-tag">
                                    <?= htmlspecialchars($item['medication_name']) ?>
                                    x<?= $item['quantity'] ?>
                                    <?php if ($item['dosage']): ?>
                                        (<?= htmlspecialchars($item['dosage']) ?>)
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="mt-2 flex gap-2 flex-wrap">
                    <a href="prescribe.php?visit_id=<?= $visit_id ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <?php if ($pres['status'] !== 'dispensed'): ?>
                        <button onclick="sendToPharmacy(<?= $pres['id'] ?>)" class="btn btn-success btn-sm">
                            <i class="fas fa-paper-plane"></i> Send to Pharmacy
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- LAB REQUESTS -->
    <!-- ================================================================ -->
    <?php if (count($lab_requests) > 0): ?>
    <div class="detail-card mb-4">
        <div class="card-title">
            <i class="fas fa-flask" style="color:#7C3AED;"></i>
            Lab Requests
            <span class="text-sm font-normal text-gray-400">(<?= count($lab_requests) ?> requests)</span>
        </div>
        
        <?php foreach ($lab_requests as $lab): ?>
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 mb-3 last:mb-0">
                <div class="flex flex-wrap justify-between items-center">
                    <div>
                        <span class="font-mono text-sm font-bold text-purple-600">#<?= htmlspecialchars($lab['request_number']) ?></span>
                        <span class="text-xs text-gray-400 ml-2"><?= date('d/m/Y H:i', strtotime($lab['requested_at'])) ?></span>
                    </div>
                    <span class="item-tag <?= $lab['status'] === 'completed' ? 'paid' : 'pending' ?>">
                        <?= ucfirst(str_replace('_', ' ', $lab['status'] ?? 'Pending')) ?>
                    </span>
                </div>
                
                <?php if (isset($lab_items[$lab['id']]) && count($lab_items[$lab['id']]) > 0): ?>
                    <div class="mt-2">
                        <p class="text-xs text-gray-400">Tests:</p>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <?php foreach ($lab_items[$lab['id']] as $item): ?>
                                <span class="item-tag <?= $item['status'] === 'completed' ? 'paid' : 'pending' ?>">
                                    <?= htmlspecialchars($item['test_name']) ?>
                                    <?php if ($item['result']): ?>
                                        (<?= htmlspecialchars($item['result']) ?>)
                                    <?php endif; ?>
                                    <span style="font-weight:400;">(<?= ucfirst($item['status'] ?? 'Pending') ?>)</span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($lab['notes'])): ?>
                    <p class="text-xs text-gray-500 mt-1">Notes: <?= htmlspecialchars($lab['notes']) ?></p>
                <?php endif; ?>
                
                <div class="mt-2 flex gap-2 flex-wrap">
                    <a href="lab_request.php?visit_id=<?= $visit_id ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add Tests
                    </a>
                    <?php if ($lab['status'] !== 'completed' && $lab['status'] !== 'cancelled'): ?>
                        <button onclick="viewLabResults(<?= $lab['id'] ?>)" class="btn btn-outline btn-sm">
                            <i class="fas fa-eye"></i> View Results
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PROCEDURES & MEDICATIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        
        <!-- Procedures -->
        <?php if (count($procedures) > 0): ?>
        <div class="detail-card">
            <div class="card-title">
                <i class="fas fa-syringe" style="color:#0D9488;"></i>
                Procedures
                <span class="text-sm font-normal text-gray-400">(<?= count($procedures) ?>)</span>
            </div>
            <?php foreach ($procedures as $proc): ?>
                <div class="flex justify-between items-center border-b border-gray-100 dark:border-gray-700 py-2 last:border-0">
                    <span><?= htmlspecialchars($proc['item_name']) ?></span>
                    <span class="item-tag <?= $proc['bill_status'] === 'paid' ? 'paid' : 'pending' ?>">
                        <?= ucfirst($proc['bill_status'] ?? 'Pending') ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Medications -->
        <?php if (count($medications) > 0): ?>
        <div class="detail-card">
            <div class="card-title">
                <i class="fas fa-pills" style="color:#D97706;"></i>
                Medications
                <span class="text-sm font-normal text-gray-400">(<?= count($medications) ?>)</span>
            </div>
            <?php foreach ($medications as $med): ?>
                <div class="flex justify-between items-center border-b border-gray-100 dark:border-gray-700 py-2 last:border-0">
                    <span><?= htmlspecialchars($med['item_name']) ?> x<?= $med['quantity'] ?? 1 ?></span>
                    <span class="item-tag <?= $med['bill_status'] === 'paid' ? 'paid' : 'pending' ?>">
                        <?= ucfirst($med['bill_status'] ?? 'Pending') ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
    </div>

    <!-- ================================================================ -->
    <!-- BILLS -->
    <!-- ================================================================ -->
    <?php if (count($bills) > 0): ?>
    <div class="detail-card mb-4">
        <div class="card-title">
            <i class="fas fa-receipt" style="color:#059669;"></i>
            Bills
            <span class="text-sm font-normal text-gray-400">(<?= count($bills) ?> bills)</span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-700">
                        <th class="p-2 text-left">Bill #</th>
                        <th class="p-2 text-left">Total</th>
                        <th class="p-2 text-left">Paid</th>
                        <th class="p-2 text-left">Balance</th>
                        <th class="p-2 text-left">Status</th>
                        <th class="p-2 text-left">Items</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bills as $bill): ?>
                        <tr class="border-b border-gray-100 dark:border-gray-700">
                            <td class="p-2 font-mono text-blue-600"><?= htmlspecialchars($bill['bill_number']) ?></td>
                            <td class="p-2 font-semibold"><?= formatCurrency($bill['total_amount']) ?></td>
                            <td class="p-2"><?= formatCurrency($bill['paid_amount']) ?></td>
                            <td class="p-2" style="color:<?= ($bill['balance'] ?? 0) > 0 ? '#DC2626' : '#059669' ?>;">
                                <?= formatCurrency($bill['balance']) ?>
                            </td>
                            <td class="p-2">
                                <span class="item-tag <?= $bill['status'] === 'paid' ? 'paid' : 'pending' ?>">
                                    <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                </span>
                            </td>
                            <td class="p-2">
                                <?php if (isset($bill_items[$bill['id']]) && count($bill_items[$bill['id']]) > 0): ?>
                                    <?php foreach (array_slice($bill_items[$bill['id']], 0, 2) as $item): ?>
                                        <div class="text-xs"><?= htmlspecialchars($item['item_name']) ?></div>
                                    <?php endforeach; ?>
                                    <?php if (count($bill_items[$bill['id']]) > 2): ?>
                                        <div class="text-xs text-gray-400">+<?= count($bill_items[$bill['id']]) - 2 ?> more</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ACTION BUTTONS -->
    <!-- ================================================================ -->
    <div class="flex flex-wrap gap-3 mt-4 action-buttons">
        
        <?php if ($visit['status'] !== 'completed' && $visit['status'] !== 'cancelled'): ?>
            
            <?php if ($visit['status'] === 'assigned' || $visit['status'] === 'pending'): ?>
                <a href="consultation.php?visit_id=<?= $visit_id ?>" class="btn btn-success">
                    <i class="fas fa-stethoscope"></i> Start Consultation
                </a>
            <?php endif; ?>
            
            <?php if ($visit['status'] === 'with_doctor'): ?>
                <a href="consultation.php?visit_id=<?= $visit_id ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Continue Consultation
                </a>
            <?php endif; ?>
            
            <a href="lab_request.php?visit_id=<?= $visit_id ?>" class="btn btn-warning">
                <i class="fas fa-flask"></i> Add Lab Request
            </a>
            
            <a href="prescribe.php?visit_id=<?= $visit_id ?>" class="btn btn-primary">
                <i class="fas fa-prescription"></i> Prescribe
            </a>
            
            <?php if ($visit['status'] === 'with_doctor'): ?>
                <button onclick="completeVisit(<?= $visit_id ?>)" class="btn btn-success">
                    <i class="fas fa-check-circle"></i> Complete Visit
                </button>
            <?php endif; ?>
            
            <?php if ($visit['status'] !== 'cancelled'): ?>
                <button onclick="cancelVisit(<?= $visit_id ?>)" class="btn btn-danger">
                    <i class="fas fa-times-circle"></i> Cancel Visit
                </button>
            <?php endif; ?>
            
        <?php else: ?>
            <span class="text-gray-400 text-sm">
                <i class="fas fa-info-circle mr-1"></i>
                This visit is <?= $visit['status'] ?>
            </span>
        <?php endif; ?>
        
        <a href="view_patient.php?id=<?= $visit['patient_id'] ?>" class="btn btn-outline">
            <i class="fas fa-user"></i> View Patient
        </a>
        
        <a href="export_patient_pdf.php?id=<?= $visit['patient_id'] ?>" class="btn btn-outline" target="_blank">
            <i class="fas fa-file-pdf"></i> Export PDF
        </a>
        
    </div>

    <?php else: ?>
        <!-- Visit not found -->
        <div class="text-center py-12 text-gray-400">
            <i class="fas fa-clinic-medical text-5xl block mb-4 text-gray-300"></i>
            <h3 class="text-xl font-semibold text-gray-600 dark:text-gray-300">Visit Not Found</h3>
            <p class="mt-2">The visit you are looking for does not exist or has been removed.</p>
            <a href="dashboard.php" class="text-primary hover:underline mt-4 inline-block">
                <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
            </a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visit Details
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
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // TOAST
    // ================================================================
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // COMPLETE VISIT
    // ================================================================
    function completeVisit(visitId) {
        if (!confirm('Are you sure you want to mark this visit as completed?')) return;
        
        fetch('../../backend/api/update_visit_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'visit_id=' + visitId + '&status=completed'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('✅ Success', 'Visit marked as completed', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('❌ Error', data.message || 'Failed to complete visit', 'error');
            }
        })
        .catch(error => {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
        });
    }

    // ================================================================
    // CANCEL VISIT
    // ================================================================
    function cancelVisit(visitId) {
        if (!confirm('Are you sure you want to cancel this visit?')) return;
        
        fetch('../../backend/api/update_visit_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'visit_id=' + visitId + '&status=cancelled'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('✅ Success', 'Visit cancelled', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('❌ Error', data.message || 'Failed to cancel visit', 'error');
            }
        })
        .catch(error => {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
        });
    }

    // ================================================================
    // SEND TO PHARMACY
    // ================================================================
    function sendToPharmacy(prescriptionId) {
        if (!confirm('Send this prescription to pharmacy?')) return;
        
        fetch('../../backend/api/send_prescription_to_pharmacy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'prescription_id=' + prescriptionId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('✅ Success', 'Prescription sent to pharmacy', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('❌ Error', data.message || 'Failed to send prescription', 'error');
            }
        })
        .catch(error => {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
        });
    }

    // ================================================================
    // VIEW LAB RESULTS
    // ================================================================
    function viewLabResults(labId) {
        window.location.href = 'lab_results.php?lab_id=' + labId;
    }

    console.log('%c🏥 Braick - Visit Details (Patient Name in Blue)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= $visit ? htmlspecialchars($visit['patient_name']) : 'N/A' ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Visit #: <?= $visit ? htmlspecialchars($visit['visit_number']) : 'N/A' ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Status: <?= $visit ? getStatusText($visit['status']) : 'N/A' ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💓 Vital Signs: <?= $vital_signs ? '✅ Recorded' : '❌ Not recorded' ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c💊 Prescriptions: <?= count($prescriptions) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c🧪 Lab Requests: <?= count($lab_requests) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🔵 Patient name is BLUE in 3 places: Header badge, Visit title, Patient Info card', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>