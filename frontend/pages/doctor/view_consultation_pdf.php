<?php
// ================================================================
// FILE: frontend/pages/doctor/view_consultation_pdf.php
// VIEW CONSULTATION AS PDF - A4 SIZE WITH LOGO, BRANCH & PHONE
// BRAICK DISPENSARY - TUNAJALI AFYA YAKO
// FIXED: Shows diagnosis directly from visits table
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK ROLE
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET PARAMETERS
// ================================================================
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

if ($visit_id <= 0) {
    header('Location: my_patients.php');
    exit;
}

// ================================================================
// GET DOCTOR INFO
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$is_admin = ($_SESSION['role'] === 'admin');

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
// GET VISIT DETAILS - DIRECT FROM VISITS TABLE
// ================================================================
if ($is_admin) {
    $stmt = $db->prepare("
        SELECT 
            v.id, v.visit_number, v.visit_date, v.visit_type, v.status,
            v.symptoms, v.hpi, v.physical_exam, v.complaint,
            v.diagnosis, v.disease_code, v.treatment, v.notes,
            v.created_at, v.consultation_fee,
            p.id as patient_id,
            p.patient_id as patient_code,
            p.full_name as patient_name,
            p.phone, p.email, p.date_of_birth, p.gender,
            p.address, p.blood_group, p.allergies, p.emergency_contact,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            u.phone as doctor_phone,
            b.name as branch_name,
            b.location as branch_location,
            b.phone as branch_phone,
            d.disease_name, 
            d.disease_code as disease_code_from_table,
            d.treatment as disease_treatment
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        LEFT JOIN diseases d ON v.disease_id = d.id
        WHERE v.id = ?
    ");
    $stmt->execute([$visit_id]);
} else {
    $stmt = $db->prepare("
        SELECT 
            v.id, v.visit_number, v.visit_date, v.visit_type, v.status,
            v.symptoms, v.hpi, v.physical_exam, v.complaint,
            v.diagnosis, v.disease_code, v.treatment, v.notes,
            v.created_at, v.consultation_fee,
            p.id as patient_id,
            p.patient_id as patient_code,
            p.full_name as patient_name,
            p.phone, p.email, p.date_of_birth, p.gender,
            p.address, p.blood_group, p.allergies, p.emergency_contact,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            u.phone as doctor_phone,
            b.name as branch_name,
            b.location as branch_location,
            b.phone as branch_phone,
            d.disease_name, 
            d.disease_code as disease_code_from_table,
            d.treatment as disease_treatment
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        LEFT JOIN diseases d ON v.disease_id = d.id
        WHERE v.id = ? AND v.doctor_id = ?
    ");
    $stmt->execute([$visit_id, $doctor_id]);
}
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    header('Location: my_patients.php?error=visit_not_found');
    exit;
}

// ================================================================
// EXTRACT DIAGNOSIS DIRECTLY FROM VISIT
// ================================================================
$diagnosis_display = '';
$disease_code_display = '';
$treatment_display = '';

// DIAGNOSIS - Direct from visits.diagnosis
if (!empty($visit['diagnosis'])) {
    $diagnosis_display = $visit['diagnosis'];
} 
// Fallback to disease_name if diagnosis is empty
else if (!empty($visit['disease_name'])) {
    $diagnosis_display = $visit['disease_name'];
}

// DISEASE CODE - Direct from visits.disease_code
if (!empty($visit['disease_code'])) {
    $disease_code_display = $visit['disease_code'];
} 
// Fallback to disease_code from diseases table
else if (!empty($visit['disease_code_from_table'])) {
    $disease_code_display = $visit['disease_code_from_table'];
}

// TREATMENT - Direct from visits.treatment
if (!empty($visit['treatment'])) {
    $treatment_display = $visit['treatment'];
} 
// Fallback to disease_treatment
else if (!empty($visit['disease_treatment'])) {
    $treatment_display = $visit['disease_treatment'];
}

// ================================================================
// GET ADMIN PHONE NUMBER
// ================================================================
$admin_phone = '';
try {
    $stmt = $db->prepare("SELECT phone FROM users WHERE role = 'admin' AND branch_id = ? LIMIT 1");
    $stmt->execute([$doctor_branch_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $admin_phone = $admin['phone'] ?? '';
    }
    if (empty($admin_phone)) {
        $stmt = $db->prepare("SELECT phone FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        $admin_phone = $admin['phone'] ?? '';
    }
} catch (Exception $e) {
    $admin_phone = '';
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'assigned' => 'badge-info',
        'with_doctor' => 'badge-warning',
        'lab_test' => 'badge-purple',
        'in_progress' => 'badge-info',
        'prescribed' => 'badge-purple',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-info';
}

function getVitalStatus($value, $type) {
    if ($value === null || $value === '--' || $value === '') return ['label' => 'N/A', 'class' => 'unknown'];
    switch ($type) {
        case 'temperature':
            if ($value > 37.5) return ['label' => 'HIGH', 'class' => 'high'];
            if ($value < 36.0) return ['label' => 'LOW', 'class' => 'low'];
            return ['label' => 'NORMAL', 'class' => 'normal'];
        case 'systolic':
            if ($value > 140) return ['label' => 'HIGH', 'class' => 'high'];
            if ($value < 90) return ['label' => 'LOW', 'class' => 'low'];
            return ['label' => 'NORMAL', 'class' => 'normal'];
        case 'pulse':
            if ($value > 100) return ['label' => 'HIGH', 'class' => 'high'];
            if ($value < 60) return ['label' => 'LOW', 'class' => 'low'];
            return ['label' => 'NORMAL', 'class' => 'normal'];
        case 'bmi':
            if ($value >= 30) return ['label' => 'OBESE', 'class' => 'high'];
            if ($value >= 25) return ['label' => 'OVERWEIGHT', 'class' => 'high'];
            if ($value >= 18.5) return ['label' => 'NORMAL', 'class' => 'normal'];
            return ['label' => 'UNDERWEIGHT', 'class' => 'low'];
        default:
            return ['label' => 'N/A', 'class' => 'unknown'];
    }
}

// ================================================================
// GET ADDITIONAL DATA
// ================================================================

// 1. Vital Signs
$vital_signs = null;
$stmt = $db->prepare("
    SELECT 
        temperature,
        blood_pressure_systolic,
        blood_pressure_diastolic,
        pulse_rate,
        weight,
        height,
        bmi,
        notes,
        recorded_at,
        u.full_name as recorded_by
    FROM vital_signs vs
    LEFT JOIN users u ON vs.recorded_by = u.id
    WHERE vs.visit_id = ? 
    ORDER BY vs.recorded_at DESC 
    LIMIT 1
");
$stmt->execute([$visit_id]);
$vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Lab Results
$lab_results = [];
$stmt = $db->prepare("
    SELECT lt.*, lc.category as test_category
    FROM lab_tests lt
    LEFT JOIN lab_tests_catalog lc ON lt.test_id = lc.id
    WHERE lt.visit_id = ? AND lt.status = 'completed'
    ORDER BY lt.completed_at DESC
");
$stmt->execute([$visit_id]);
$lab_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Lab Tests (Pending)
$lab_requests = [];
$stmt = $db->prepare("
    SELECT lt.*
    FROM lab_tests lt
    WHERE lt.visit_id = ? AND lt.status IN ('pending', 'in_progress')
    ORDER BY lt.created_at DESC
");
$stmt->execute([$visit_id]);
$lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Prescriptions
$prescriptions = [];
$prescription_items = [];
$stmt = $db->prepare("
    SELECT p.* 
    FROM prescriptions p
    WHERE p.visit_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$visit_id]);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($prescriptions as $pres) {
    $stmt = $db->prepare("
        SELECT pi.*
        FROM prescription_items pi
        WHERE pi.prescription_id = ?
        ORDER BY pi.created_at DESC
    ");
    $stmt->execute([$pres['id']]);
    $prescription_items[$pres['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 5. Procedures
$procedures = [];
$stmt = $db->prepare("
    SELECT p.*
    FROM procedures p
    WHERE p.visit_id = ? AND p.status != 'cancelled'
    ORDER BY p.created_at DESC
");
$stmt->execute([$visit_id]);
$procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Equipment Items
$equipment_items = [];
$stmt = $db->prepare("
    SELECT bi.*, b.bill_number
    FROM bill_items bi
    JOIN bills b ON bi.bill_id = b.id
    WHERE b.visit_id = ? AND bi.item_type = 'equipment' AND bi.status != 'cancelled'
    ORDER BY bi.created_at DESC
");
$stmt->execute([$visit_id]);
$equipment_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Bill Summary
$bill_items = [];
$total_bill_amount = 0;
$paid_total = 0;
$bill_balance = 0;
$bill_status = 'pending';

$stmt = $db->prepare("SELECT id, status, total_amount, paid_amount, balance FROM bills WHERE visit_id = ?");
$stmt->execute([$visit_id]);
$bill = $stmt->fetch(PDO::FETCH_ASSOC);
if ($bill) {
    $bill_id = $bill['id'];
    $bill_status = $bill['status'];
    $total_bill_amount = $bill['total_amount'] ?? 0;
    $paid_total = $bill['paid_amount'] ?? 0;
    $bill_balance = $bill['balance'] ?? 0;
    
    $stmt = $db->prepare("
        SELECT id, item_name, item_type, quantity, unit_price, total_price, status 
        FROM bill_items 
        WHERE bill_id = ? AND status != 'cancelled'
    ");
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// BRANCH INFO
// ================================================================
$doctor_branch_name = 'Not Assigned';
$branch_location = '';
$branch_phone = '';
try {
    $stmt = $db->prepare("SELECT name, location, phone FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $doctor_branch_name = $branch_data['name'];
        $branch_location = $branch_data['location'] ?? '';
        $branch_phone = $branch_data['phone'] ?? '';
    }
} catch (Exception $e) { $doctor_branch_name = 'Branch'; }

// ================================================================
// LOGO PATH
// ================================================================
$logo_base64 = '';
$logo_paths = [
    '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png',
    '../../assets/uploads/profiles/braick_logo.png',
    '../../../assets/uploads/profiles/braick_logo.png',
    '/dispensary_system/frontend/assets/images/logo.png',
    '../../assets/images/logo.png',
    '../../../assets/images/logo.png',
    '/dispensary_system/frontend/assets/uploads/profiles/Braick_logo.png',
    '/dispensary_system/frontend/assets/uploads/profiles/braick.png'
];

foreach ($logo_paths as $path) {
    $test_paths = [
        __DIR__ . '/../../..' . str_replace('/dispensary_system/frontend', '', $path),
        __DIR__ . '/../..' . str_replace('/dispensary_system/frontend', '', $path),
        __DIR__ . '/../../assets/uploads/profiles/braick_logo.png',
        __DIR__ . '/../../../assets/uploads/profiles/braick_logo.png'
    ];
    
    foreach ($test_paths as $test) {
        if (file_exists($test)) {
            try {
                $image_data = file_get_contents($test);
                $image_type = pathinfo($test, PATHINFO_EXTENSION);
                if ($image_type === 'png') {
                    $logo_base64 = 'data:image/png;base64,' . base64_encode($image_data);
                } elseif ($image_type === 'jpg' || $image_type === 'jpeg') {
                    $logo_base64 = 'data:image/jpeg;base64,' . base64_encode($image_data);
                } else {
                    $logo_base64 = 'data:image/' . $image_type . ';base64,' . base64_encode($image_data);
                }
                break 2;
            } catch (Exception $e) {}
        }
    }
}

// ================================================================
// BUILD PDF CONTENT FUNCTION - FIXED DIAGNOSIS DISPLAY
// ================================================================
function buildPDFContent($visit, $vital_signs, $lab_results, $lab_requests, $prescriptions, $prescription_items, $procedures, $equipment_items, $bill_items, $total_bill_amount, $paid_total, $bill_balance, $bill_status, $branch_location, $branch_phone, $doctor_branch_name, $doctor_name, $logo_base64, $admin_phone, $diagnosis_display, $disease_code_display, $treatment_display) {
    ?>
    
    <!-- ================================================================ -->
    <!-- PDF CONTENT - A4 SIZE WITH LOGO, BRANCH & PHONE -->
    <!-- ================================================================ -->
    
    <!-- HEADER WITH LOGO -->
    <div class="pdf-header">
        <div class="header-logo-area">
            <?php if (!empty($logo_base64)): ?>
                <img src="<?= $logo_base64 ?>" alt="Braick Dispensary" class="header-logo-img">
            <?php else: ?>
                <div class="header-logo-text">🏥</div>
            <?php endif; ?>
            <div>
                <div class="clinic-name">BRAICK DISPENSARY</div>
                <div class="clinic-sub">TUNAJALI AFYA YAKO</div>
            </div>
        </div>
        <div class="header-contact">
            <div class="contact-row">
                <i class="fas fa-map-marker-alt"></i> 
                <span><?= htmlspecialchars($branch_location ?: $doctor_branch_name) ?></span>
            </div>
            <div class="contact-row">
                <i class="fas fa-phone"></i> 
                <span><?= htmlspecialchars($branch_phone ?: $admin_phone) ?></span>
            </div>
            <div class="contact-row">
                <i class="fas fa-calendar-alt"></i> 
                <span><?= date('F d, Y') ?></span>
            </div>
        </div>
    </div>
    
    <!-- DOCUMENT TITLE -->
    <div class="doc-title-bar">
        <div class="doc-title">📋 CONSULTATION REPORT</div>
        <div class="doc-sub"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></div>
    </div>

    <!-- ================================================================ -->
    <!-- 1. PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span class="section-icon">👤</span>
        PATIENT INFORMATION
        <span class="section-count">ID: <?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></span>
    </div>
    <div class="info-grid-2">
        <div class="info-row">
            <span class="info-label">Full Name</span>
            <span class="info-value"><strong><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></strong></span>
        </div>
        <div class="info-row">
            <span class="info-label">Patient ID</span>
            <span class="info-value"><?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Gender</span>
            <span class="info-value"><?= htmlspecialchars($visit['gender'] ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Date of Birth</span>
            <span class="info-value"><?= !empty($visit['date_of_birth']) ? date('M d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?> (<?= calculateAge($visit['date_of_birth'] ?? '') ?> years)</span>
        </div>
        <div class="info-row">
            <span class="info-label">Phone</span>
            <span class="info-value"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Email</span>
            <span class="info-value"><?= htmlspecialchars($visit['email'] ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Blood Group</span>
            <span class="info-value"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Allergies</span>
            <span class="info-value"><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></span>
        </div>
        <div class="info-row full-width">
            <span class="info-label">Address</span>
            <span class="info-value"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 2. VISIT INFORMATION -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span class="section-icon">📋</span>
        VISIT INFORMATION
        <span class="section-count"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
    </div>
    <div class="info-grid-3">
        <div class="info-row">
            <span class="info-label">Visit Number</span>
            <span class="info-value"><strong><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></strong></span>
        </div>
        <div class="info-row">
            <span class="info-label">Status</span>
            <span class="info-value"><span class="badge-pdf <?= getStatusBadgeClass($visit['status'] ?? 'pending') ?>"><?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'Pending')) ?></span></span>
        </div>
        <div class="info-row">
            <span class="info-label">Visit Type</span>
            <span class="info-value"><?= ucfirst($visit['visit_type'] ?? 'New') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Date</span>
            <span class="info-value"><?= date('M d, Y h:i A', strtotime($visit['created_at'] ?? 'now')) ?></span>
        </div>
        <div class="info-row full-width">
            <span class="info-label">Doctor</span>
            <span class="info-value"><strong>Dr. <?= htmlspecialchars($visit['doctor_name'] ?? $doctor_name) ?></strong> (<?= htmlspecialchars($visit['doctor_specialty'] ?? 'General Medicine') ?>)</span>
        </div>
        <div class="info-row full-width">
            <span class="info-label">Branch</span>
            <span class="info-value"><?= htmlspecialchars($visit['branch_name'] ?? $doctor_branch_name) ?></span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 3. VITAL SIGNS -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span class="section-icon">❤️</span>
        VITAL SIGNS
        <span class="section-count">6 Parameters</span>
    </div>
    <?php if ($vital_signs): ?>
        <div class="vital-grid-cards">
            <?php
            $temp_status = getVitalStatus($vital_signs['temperature'] ?? null, 'temperature');
            $sys = $vital_signs['blood_pressure_systolic'] ?? null;
            $bp_status = getVitalStatus($sys, 'systolic');
            $pulse_status = getVitalStatus($vital_signs['pulse_rate'] ?? null, 'pulse');
            $bmi_status = getVitalStatus($vital_signs['bmi'] ?? null, 'bmi');
            ?>
            <div class="vital-card-pdf temp">
                <span class="vital-icon">🌡️</span>
                <span class="vital-value"><?= $vital_signs['temperature'] ?? '--' ?> <span class="vital-unit">°C</span></span>
                <span class="vital-label">Temperature</span>
                <span class="vital-status <?= $temp_status['class'] ?>"><?= $temp_status['label'] ?></span>
            </div>
            <div class="vital-card-pdf bp">
                <span class="vital-icon">💓</span>
                <span class="vital-value"><?= ($vital_signs['blood_pressure_systolic'] ?? '--') . '/' . ($vital_signs['blood_pressure_diastolic'] ?? '--') ?> <span class="vital-unit">mmHg</span></span>
                <span class="vital-label">Blood Pressure</span>
                <span class="vital-status <?= $bp_status['class'] ?>"><?= $bp_status['label'] ?></span>
            </div>
            <div class="vital-card-pdf pulse">
                <span class="vital-icon">💓</span>
                <span class="vital-value"><?= $vital_signs['pulse_rate'] ?? '--' ?> <span class="vital-unit">bpm</span></span>
                <span class="vital-label">Pulse Rate</span>
                <span class="vital-status <?= $pulse_status['class'] ?>"><?= $pulse_status['label'] ?></span>
            </div>
            <div class="vital-card-pdf weight">
                <span class="vital-icon">⚖️</span>
                <span class="vital-value"><?= $vital_signs['weight'] ?? '--' ?> <span class="vital-unit">kg</span></span>
                <span class="vital-label">Weight</span>
            </div>
            <div class="vital-card-pdf height">
                <span class="vital-icon">📏</span>
                <span class="vital-value"><?= $vital_signs['height'] ?? '--' ?> <span class="vital-unit">cm</span></span>
                <span class="vital-label">Height</span>
            </div>
            <div class="vital-card-pdf bmi">
                <span class="vital-icon">📊</span>
                <span class="vital-value"><?= $vital_signs['bmi'] ?? '--' ?> <span class="vital-unit">kg/m²</span></span>
                <span class="vital-label">BMI</span>
                <span class="vital-status <?= $bmi_status['class'] ?>"><?= $bmi_status['label'] ?></span>
            </div>
        </div>
        <?php if ($vital_signs['recorded_by']): ?>
            <div class="vital-recorded-by">
                <i class="fas fa-user-circle"></i> Recorded by: <?= htmlspecialchars($vital_signs['recorded_by']) ?> 
                at <?= date('M d, Y h:i A', strtotime($vital_signs['recorded_at'] ?? 'now')) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($vital_signs['notes'])): ?>
            <div class="vital-notes">
                <strong>Notes:</strong> <?= htmlspecialchars($vital_signs['notes']) ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state-pdf">No vital signs recorded</div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- 4. SYMPTOMS -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span class="section-icon">📝</span>
        SYMPTOMS
    </div>
    <?php if (!empty($visit['symptoms'])): ?>
        <div class="symptom-tags">
            <?php 
                $symptoms_array = array_map('trim', explode(',', $visit['symptoms']));
                foreach ($symptoms_array as $sym):
                    if (!empty($sym)):
            ?>
                <span class="symptom-tag-pdf"><?= htmlspecialchars($sym) ?></span>
            <?php 
                    endif;
                endforeach; 
            ?>
        </div>
    <?php else: ?>
        <div class="empty-state-pdf">No symptoms recorded</div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- 5. COMPLAINT -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span class="section-icon">🗣️</span>
        CHIEF COMPLAINT
    </div>
    <div class="text-box-pdf">
        <?= nl2br(htmlspecialchars($visit['complaint'] ?? 'No complaint recorded')) ?>
    </div>

    <!-- ================================================================ -->
    <!-- 6. HPI -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span class="section-icon">📝</span>
        HISTORY OF PRESENTING ILLNESS (HPI)
    </div>
    <div class="text-box-pdf">
        <?= nl2br(htmlspecialchars($visit['hpi'] ?? 'No HPI recorded')) ?>
    </div>

    <!-- ================================================================ -->
    <!-- 7. PHYSICAL EXAMINATION -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span class="section-icon">🩺</span>
        PHYSICAL EXAMINATION
    </div>
    <div class="text-box-pdf">
        <?= nl2br(htmlspecialchars($visit['physical_exam'] ?? 'No physical exam recorded')) ?>
    </div>

    <!-- ================================================================ -->
    <!-- 8. LAB TESTS & RESULTS -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span class="section-icon">🧪</span>
        LABORATORY TESTS & RESULTS
        <span class="section-count"><?= count($lab_requests) + count($lab_results) ?> tests</span>
    </div>
    <?php if (count($lab_requests) > 0 || count($lab_results) > 0): ?>
        <table class="pdf-table">
            <thead>
                <tr>
                    <th style="width:35%;">Test Name</th>
                    <th style="width:20%;">Date</th>
                    <th style="width:20%;">Status</th>
                    <th style="width:25%;">Result</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lab_requests as $test): ?>
                    <tr>
                        <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                        <td><?= date('M d, Y', strtotime($test['created_at'] ?? 'now')) ?></td>
                        <td><span class="badge-pdf warning">⏳ <?= ucfirst($test['status'] ?? 'Pending') ?></span></td>
                        <td style="color:#94A3B8;">Pending...</td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($lab_results as $result): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($result['test_name'] ?? 'N/A') ?></strong></td>
                        <td><?= date('M d, Y', strtotime($result['completed_at'] ?? $result['created_at'] ?? 'now')) ?></td>
                        <td><span class="badge-pdf success">✅ Completed</span></td>
                        <td style="font-weight:600;color:#059669;"><?= htmlspecialchars($result['results'] ?? 'N/A') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state-pdf">No lab tests found</div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- 9. DIAGNOSIS - FIXED: DIRECTLY FROM VISITS TABLE -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span class="section-icon">🩺</span>
        DIAGNOSIS
        <?php if (!empty($diagnosis_display) || !empty($visit['diagnosis'])): ?>
            <span class="section-count" style="color:#059669;">✅ Diagnosis recorded</span>
        <?php else: ?>
            <span class="section-count" style="color:#DC2626;">⚠️ No diagnosis</span>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($diagnosis_display) || !empty($visit['diagnosis']) || !empty($visit['disease_name'])): ?>
        <div class="diagnosis-box-pdf">
            <!-- MAIN DIAGNOSIS - From visits.diagnosis -->
            <?php if (!empty($diagnosis_display)): ?>
                <div class="diag-text"><?= htmlspecialchars($diagnosis_display) ?></div>
            <?php elseif (!empty($visit['diagnosis'])): ?>
                <div class="diag-text"><?= htmlspecialchars($visit['diagnosis']) ?></div>
            <?php elseif (!empty($visit['disease_name'])): ?>
                <div class="diag-text"><?= htmlspecialchars($visit['disease_name']) ?></div>
            <?php endif; ?>
            
            <!-- DISEASE CODE - From visits.disease_code -->
            <?php if (!empty($disease_code_display)): ?>
                <div class="diag-code"><strong>Code:</strong> <?= htmlspecialchars($disease_code_display) ?></div>
            <?php elseif (!empty($visit['disease_code'])): ?>
                <div class="diag-code"><strong>Code:</strong> <?= htmlspecialchars($visit['disease_code']) ?></div>
            <?php elseif (!empty($visit['disease_code_from_table'])): ?>
                <div class="diag-code"><strong>Code:</strong> <?= htmlspecialchars($visit['disease_code_from_table']) ?></div>
            <?php endif; ?>
            
            <!-- TREATMENT - From visits.treatment -->
            <?php if (!empty($treatment_display)): ?>
                <div class="treatment-text"><strong>💊 Treatment:</strong> <?= nl2br(htmlspecialchars($treatment_display)) ?></div>
            <?php elseif (!empty($visit['treatment'])): ?>
                <div class="treatment-text"><strong>💊 Treatment:</strong> <?= nl2br(htmlspecialchars($visit['treatment'])) ?></div>
            <?php elseif (!empty($visit['disease_treatment'])): ?>
                <div class="treatment-text"><strong>💊 Treatment:</strong> <?= nl2br(htmlspecialchars($visit['disease_treatment'])) ?></div>
            <?php endif; ?>
            
            <!-- ADDITIONAL NOTES -->
            <?php if (!empty($visit['notes'])): ?>
                <div class="treatment-text" style="border-top-color:#CBD5E1;margin-top:4px;padding-top:4px;border-top:1px dashed #CBD5E1;">
                    <strong>📝 Notes:</strong> <?= nl2br(htmlspecialchars($visit['notes'])) ?>
                </div>
            <?php endif; ?>
            
            <!-- SHOW SOURCE OF DATA -->
            <div style="font-size:6pt;color:#94A3B8;margin-top:6px;border-top:1px dashed #E2E8F0;padding-top:4px;">
                <?php if (!empty($visit['diagnosis'])): ?>
                    <span>📌 Source: visits.diagnosis</span>
                <?php elseif (!empty($visit['disease_name'])): ?>
                    <span>📌 Source: diseases.disease_name</span>
                <?php endif; ?>
                <?php if (!empty($visit['disease_code']) || !empty($visit['disease_code_from_table'])): ?>
                    <span style="margin-left:8px;">🔑 Code: <?= htmlspecialchars($visit['disease_code'] ?: $visit['disease_code_from_table']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state-pdf">No diagnosis recorded</div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- 10. PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span class="section-icon">💊</span>
        PRESCRIPTIONS & MEDICATIONS
        <span class="section-count"><?= count($prescriptions) ?> prescriptions</span>
    </div>
    <?php if (count($prescriptions) > 0): ?>
        <?php foreach ($prescriptions as $pres): 
            $items = $prescription_items[$pres['id']] ?? [];
        ?>
            <div class="prescription-box">
                <div class="pres-header">
                    <span class="pres-number">#<?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?></span>
                    <span class="badge-pdf <?= ($pres['status'] === 'dispensed') ? 'success' : 'warning' ?>"><?= ucfirst($pres['status'] ?? 'Pending') ?></span>
                </div>
                <?php if (!empty($pres['diagnosis'])): ?>
                    <div class="pres-diagnosis"><strong>Diagnosis:</strong> <?= htmlspecialchars($pres['diagnosis']) ?></div>
                <?php endif; ?>
                <?php if (count($items) > 0): ?>
                    <table class="pdf-table" style="font-size:7.5pt;margin-top:4px;">
                        <thead>
                            <tr>
                                <th style="width:25%;">Medication</th>
                                <th style="width:15%;">Dosage</th>
                                <th style="width:15%;">Frequency</th>
                                <th style="width:10%;">Qty</th>
                                <th style="width:35%;">Instructions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></strong></td>
                                    <td><?= htmlspecialchars($item['dosage'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($item['frequency'] ?? '') ?></td>
                                    <td><?= $item['quantity'] ?? 0 ?></td>
                                    <td><?= htmlspecialchars($item['instructions'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state-pdf" style="font-size:7.5pt;">No items in this prescription</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state-pdf">No medications prescribed</div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- 11. PROCEDURES & EQUIPMENT -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span class="section-icon">💉</span>
        PROCEDURES & MEDICAL EQUIPMENT
        <span class="section-count"><?= count($procedures) + count($equipment_items) ?> items</span>
    </div>
    <?php if (count($procedures) > 0 || count($equipment_items) > 0): ?>
        <table class="pdf-table">
            <thead>
                <tr>
                    <th style="width:35%;">Item Name</th>
                    <th style="width:15%;">Type</th>
                    <th style="width:10%;">Qty</th>
                    <th style="width:20%;">Price</th>
                    <th style="width:20%;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($procedures as $proc): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($proc['procedure_name'] ?? 'N/A') ?></strong></td>
                        <td>Procedure</td>
                        <td>1</td>
                        <td><?= ($proc['procedure_price'] ?? 0) > 0 ? 'TSh ' . number_format($proc['procedure_price'] ?? 0, 0) : '<span class="free-text">FREE</span>' ?></td>
                        <td><span class="badge-pdf <?= ($proc['status'] === 'completed') ? 'success' : 'info' ?>"><?= ucfirst($proc['status'] ?? 'Pending') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($equipment_items as $eq): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($eq['item_name'] ?? 'N/A') ?></strong></td>
                        <td>Equipment</td>
                        <td><?= $eq['quantity'] ?? 1 ?></td>
                        <td><?= ($eq['total_price'] ?? 0) > 0 ? 'TSh ' . number_format($eq['total_price'] ?? 0, 0) : '<span class="free-text">FREE</span>' ?></td>
                        <td><span class="badge-pdf <?= ($eq['status'] === 'paid') ? 'success' : 'warning' ?>"><?= ucfirst($eq['status'] ?? 'Pending') ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state-pdf">No procedures or equipment used</div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- 12. BILL SUMMARY -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span class="section-icon">💰</span>
        BILL SUMMARY
        <span class="section-count"><?= count($bill_items) ?> items</span>
    </div>
    <div class="bill-grid-pdf">
        <div class="bill-card-pdf total">
            <div class="amount">TSh <?= number_format($total_bill_amount, 0) ?></div>
            <div class="label">Total Amount</div>
        </div>
        <div class="bill-card-pdf paid">
            <div class="amount">TSh <?= number_format($paid_total, 0) ?></div>
            <div class="label">Paid</div>
        </div>
        <div class="bill-card-pdf balance <?= $bill_balance <= 0 ? 'zero' : '' ?>">
            <div class="amount">TSh <?= number_format($bill_balance, 0) ?></div>
            <div class="label">Balance</div>
        </div>
    </div>
    <div class="bill-status-bar">
        <span class="status-label">Status:</span>
        <span class="badge-pdf <?= $bill_balance <= 0 ? 'success' : 'warning' ?>"><?= $bill_balance <= 0 ? '✅ Paid' : '⏳ ' . ucfirst($bill_status) ?></span>
        <span class="item-count">Total Items: <?= count($bill_items) ?></span>
    </div>
    <?php 
        $consultation_total_display = 0;
        foreach ($bill_items as $item) {
            if ($item['item_type'] === 'consultation') {
                $consultation_total_display += $item['total_price'];
            }
        }
    ?>
    <?php if ($consultation_total_display > 0): ?>
        <div class="consultation-fee-bar">
            <span class="label">Consultation Fee</span>
            <span class="value">TSh <?= number_format($consultation_total_display, 0) ?></span>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER WITH OFFICIAL STAMP & MOTTO -->
    <!-- ================================================================ -->
    <div class="pdf-footer">
        <div class="footer-stamp-area">
            <div class="footer-left">
                <div><strong>Doctor:</strong> Dr. <?= htmlspecialchars($visit['doctor_name'] ?? $doctor_name) ?></div>
                <div><strong>Date:</strong> <?= date('F d, Y') ?></div>
                <div><strong>Visit:</strong> <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></div>
                <div class="signature-line-area">
                    <span class="signature-line">_________________</span> 
                    <span class="signature-label">Signature</span>
                </div>
            </div>
            <div class="stamp-box">
                <div class="stamp-title">OFFICIAL STAMP</div>
                <div class="stamp-name">BRAICK DISPENSARY</div>
                <div class="stamp-line">Approved By: _________________</div>
                <div class="stamp-date">Date: <?= date('F d, Y') ?></div>
            </div>
        </div>
        <div class="footer-motto">
            <span class="brand">💙 BRAICK DISPENSARY</span> 
            <span class="motto-text">- TUNAJALI AFYA YAKO</span>
        </div>
        <div class="footer-bottom">
            <?= htmlspecialchars($branch_location ?: $doctor_branch_name) ?> • 
            <?= htmlspecialchars($branch_phone ?: $admin_phone) ?> • 
            Generated on <?= date('F d, Y h:i A') ?> • 
            All rights reserved
        </div>
    </div>
    
    <?php
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Report - <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏥</text></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* ================================================================ */
        /* PDF STYLES - A4 SIZE WITH LOGO, BRANCH & PHONE */
        /* ================================================================ */
        @page {
            size: A4;
            margin: 12mm 14mm 12mm 14mm;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: #f1f5f9;
            color: #1E293B;
            padding: 16px;
            line-height: 1.5;
        }
        
        .pdf-container {
            max-width: 210mm;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        #pdfContent {
            padding: 28px 32px;
            background: #ffffff;
            font-size: 9.5pt;
        }
        
        /* ================================================================ */
        /* HEADER WITH LOGO, BRANCH & PHONE */
        /* ================================================================ */
        .pdf-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0 14px 0;
            border-bottom: 4px solid #0B5ED7;
            margin-bottom: 18px;
            flex-wrap: wrap;
            gap: 12px;
            position: relative;
        }
        .pdf-header::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 20%;
            right: 20%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #6EA8FE, transparent);
        }
        
        .header-logo-area {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .header-logo-img {
            height: 52px;
            width: auto;
            object-fit: contain;
        }
        .header-logo-text {
            font-size: 2.4rem;
            line-height: 1;
        }
        .clinic-name {
            font-size: 20pt;
            font-weight: 800;
            color: #0B5ED7;
            letter-spacing: 2px;
            line-height: 1.1;
        }
        .clinic-sub {
            font-size: 8.5pt;
            color: #059669;
            letter-spacing: 3px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .header-contact {
            text-align: right;
            font-size: 8.5pt;
            color: #475569;
            line-height: 1.7;
        }
        .header-contact .contact-row {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
        }
        .header-contact .contact-row i {
            color: #0B5ED7;
            width: 16px;
            text-align: center;
            font-size: 9pt;
        }
        
        /* ================================================================ */
        /* DOCUMENT TITLE */
        /* ================================================================ */
        .doc-title-bar {
            text-align: center;
            padding: 8px 0 14px 0;
            margin-bottom: 16px;
            border-bottom: 2px solid #E2E8F0;
        }
        .doc-title {
            font-size: 16pt;
            font-weight: 700;
            color: #0B5ED7;
            letter-spacing: 1px;
        }
        .doc-sub {
            font-size: 10pt;
            color: #64748B;
            font-family: monospace;
            margin-top: 2px;
        }
        
        /* ================================================================ */
        /* SECTION TITLES */
        /* ================================================================ */
        .section-title {
            font-size: 10pt;
            font-weight: 700;
            color: #0B5ED7;
            border-bottom: 2px solid #0B5ED7;
            padding: 4px 10px 4px 10px;
            margin: 14px 0 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(90deg, #EBF4FF, transparent);
            border-radius: 6px 6px 0 0;
        }
        .section-title .section-icon { font-size: 1rem; }
        .section-title .section-count {
            font-size: 7pt;
            color: #64748B;
            font-weight: 400;
            margin-left: auto;
        }
        
        /* ================================================================ */
        /* INFO ROWS */
        /* ================================================================ */
        .info-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px 20px;
            padding: 4px 6px;
        }
        .info-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 2px 16px;
            padding: 4px 6px;
        }
        .info-row {
            display: flex;
            padding: 3px 0;
            border-bottom: 1px solid #F1F5F9;
        }
        .info-row:last-child { border-bottom: none; }
        .info-row.full-width { grid-column: 1 / -1; }
        .info-label {
            font-weight: 600;
            color: #64748B;
            width: 100px;
            flex-shrink: 0;
            font-size: 7.5pt;
        }
        .info-value {
            flex: 1;
            font-size: 8.5pt;
            color: #1E293B;
        }
        .info-value strong { color: #0B5ED7; }
        
        /* ================================================================ */
        /* VITAL SIGNS CARDS */
        /* ================================================================ */
        .vital-grid-cards {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            margin: 4px 0 6px 0;
        }
        .vital-card-pdf {
            background: #FFFFFF;
            border-radius: 8px;
            padding: 8px 6px 6px 6px;
            text-align: center;
            border: 2px solid #E2E8F0;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            position: relative;
            overflow: hidden;
        }
        .vital-card-pdf::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }
        .vital-card-pdf.temp::before { background: linear-gradient(90deg, #DC2626, #F87171); }
        .vital-card-pdf.temp { border-color: #FCA5A5; }
        .vital-card-pdf.bp::before { background: linear-gradient(90deg, #0B5ED7, #6EA8FE); }
        .vital-card-pdf.bp { border-color: #93C5FD; }
        .vital-card-pdf.pulse::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
        .vital-card-pdf.pulse { border-color: #C4B5FD; }
        .vital-card-pdf.weight::before { background: linear-gradient(90deg, #D97706, #FBBF24); }
        .vital-card-pdf.weight { border-color: #FCD34D; }
        .vital-card-pdf.height::before { background: linear-gradient(90deg, #0D9488, #34D399); }
        .vital-card-pdf.height { border-color: #6EE7B7; }
        .vital-card-pdf.bmi::before { background: linear-gradient(90deg, #2563EB, #60A5FA); }
        .vital-card-pdf.bmi { border-color: #93C5FD; }
        
        .vital-card-pdf .vital-icon { font-size: 1rem; display: block; margin-bottom: 1px; }
        .vital-card-pdf .vital-value {
            font-size: 11pt;
            font-weight: 700;
            display: block;
            line-height: 1.2;
        }
        .vital-card-pdf .vital-label {
            font-size: 5.5pt;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            font-weight: 600;
            margin-top: 1px;
        }
        .vital-card-pdf .vital-unit { font-size: 6pt; font-weight: 400; color: #94A3B8; }
        .vital-card-pdf .vital-status {
            font-size: 5pt;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 8px;
            display: inline-block;
            margin-top: 2px;
            letter-spacing: 0.3px;
        }
        .vital-card-pdf .vital-status.normal {
            background: #D1FAE5;
            color: #059669;
            border: 1px solid #6EE7B7;
        }
        .vital-card-pdf .vital-status.high {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FCA5A5;
        }
        .vital-card-pdf .vital-status.low {
            background: #FEF3C7;
            color: #D97706;
            border: 1px solid #FCD34D;
        }
        .vital-card-pdf .vital-status.unknown {
            background: #F1F5F9;
            color: #64748B;
            border: 1px solid #E2E8F0;
        }
        
        .vital-card-pdf.temp .vital-value { color: #DC2626; }
        .vital-card-pdf.bp .vital-value { color: #0B5ED7; }
        .vital-card-pdf.pulse .vital-value { color: #7C3AED; }
        .vital-card-pdf.weight .vital-value { color: #D97706; }
        .vital-card-pdf.height .vital-value { color: #0D9488; }
        .vital-card-pdf.bmi .vital-value { color: #2563EB; }
        
        .vital-recorded-by {
            font-size: 6.5pt;
            color: #94A3B8;
            margin-top: 4px;
            padding: 2px 10px;
            background: #F8FAFC;
            border-radius: 4px;
            display: inline-block;
        }
        .vital-notes {
            font-size: 7pt;
            color: #475569;
            margin-top: 4px;
            padding: 3px 10px;
            background: #F8FAFC;
            border-radius: 4px;
            border-left: 3px solid #0B5ED7;
        }
        
        /* ================================================================ */
        /* SYMPTOM TAGS */
        /* ================================================================ */
        .symptom-tags { padding: 4px 0; }
        .symptom-tag-pdf {
            display: inline-block;
            background: #EBF4FF;
            color: #0B5ED7;
            padding: 2px 12px;
            border-radius: 14px;
            font-size: 7.5pt;
            margin: 2px 4px 2px 0;
            border: 1px solid #6EA8FE;
            font-weight: 500;
        }
        
        /* ================================================================ */
        /* TEXT BOX */
        /* ================================================================ */
        .text-box-pdf {
            padding: 6px 12px;
            font-size: 8.5pt;
            background: #F8FAFC;
            border-radius: 6px;
            border: 1px solid #E2E8F0;
            min-height: 30px;
        }
        
        /* ================================================================ */
        /* DIAGNOSIS BOX - IMPROVED */
        /* ================================================================ */
        .diagnosis-box-pdf {
            padding: 10px 14px;
            background: linear-gradient(135deg, #EBF4FF, #F8FAFC);
            border-radius: 6px;
            border-left: 4px solid #0B5ED7;
            box-shadow: 0 1px 4px rgba(11,94,215,0.06);
        }
        .diagnosis-box-pdf .diag-text {
            font-size: 10pt;
            font-weight: 700;
            color: #0B5ED7;
        }
        .diagnosis-box-pdf .diag-code {
            font-size: 8pt;
            color: #64748B;
            margin-top: 1px;
        }
        .diagnosis-box-pdf .diag-desc {
            font-size: 8.5pt;
            color: #475569;
            margin-top: 3px;
        }
        .diagnosis-box-pdf .treatment-text {
            font-size: 8.5pt;
            color: #475569;
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px dashed #CBD5E1;
        }
        .diagnosis-box-pdf .treatment-text strong { color: #059669; }
        
        /* ================================================================ */
        /* TABLES */
        /* ================================================================ */
        .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
            margin: 4px 0;
            border-radius: 6px;
            overflow: hidden;
        }
        .pdf-table thead th {
            text-align: left;
            padding: 4px 8px;
            background: linear-gradient(135deg, #0B5ED7, #1A7FE8);
            color: white;
            font-size: 6pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            border: none;
        }
        .pdf-table tbody td {
            padding: 4px 8px;
            border-bottom: 1px solid #F1F5F9;
            font-size: 7.5pt;
        }
        .pdf-table tbody tr:nth-child(even) td {
            background: #F8FAFC;
        }
        
        /* ================================================================ */
        /* BADGES */
        /* ================================================================ */
        .badge-pdf {
            font-size: 6pt;
            font-weight: 600;
            padding: 1px 8px;
            border-radius: 8px;
            display: inline-block;
            letter-spacing: 0.2px;
        }
        .badge-pdf.success {
            background: #D1FAE5;
            color: #059669;
            border: 1px solid #6EE7B7;
        }
        .badge-pdf.warning {
            background: #FEF3C7;
            color: #D97706;
            border: 1px solid #FCD34D;
        }
        .badge-pdf.info {
            background: #EBF4FF;
            color: #0B5ED7;
            border: 1px solid #93C5FD;
        }
        .badge-pdf.purple {
            background: #EDE9FE;
            color: #7C3AED;
            border: 1px solid #C4B5FD;
        }
        .badge-pdf.danger {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FCA5A5;
        }
        .free-text { color: #059669; font-weight: 600; }
        
        /* ================================================================ */
        /* PRESCRIPTION BOX */
        /* ================================================================ */
        .prescription-box {
            margin-bottom: 6px;
            padding: 6px 10px;
            background: #FFFFFF;
            border-radius: 4px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .prescription-box .pres-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            padding-bottom: 3px;
            border-bottom: 1px solid #F1F5F9;
            margin-bottom: 3px;
        }
        .prescription-box .pres-number {
            font-size: 8pt;
            font-weight: 700;
            color: #0B5ED7;
        }
        .prescription-box .pres-diagnosis {
            font-size: 7pt;
            color: #64748B;
        }
        
        /* ================================================================ */
        /* BILL SUMMARY */
        /* ================================================================ */
        .bill-grid-pdf {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin: 4px 0;
        }
        .bill-card-pdf {
            background: #FFFFFF;
            padding: 8px 12px;
            border-radius: 6px;
            text-align: center;
            border: 2px solid #E2E8F0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .bill-card-pdf .amount { font-size: 11pt; font-weight: 700; }
        .bill-card-pdf .label {
            font-size: 5.5pt;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-top: 1px;
        }
        .bill-card-pdf.total { border-color: #93C5FD; background: #EBF4FF; }
        .bill-card-pdf.total .amount { color: #0B5ED7; }
        .bill-card-pdf.paid { border-color: #6EE7B7; background: #D1FAE5; }
        .bill-card-pdf.paid .amount { color: #059669; }
        .bill-card-pdf.balance { border-color: #FCA5A5; background: #FEE2E2; }
        .bill-card-pdf.balance .amount { color: #DC2626; }
        .bill-card-pdf.balance.zero { border-color: #6EE7B7; background: #D1FAE5; }
        .bill-card-pdf.balance.zero .amount { color: #059669; }
        
        .bill-status-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            padding: 4px 10px;
            background: #F8FAFC;
            border-radius: 4px;
            margin: 2px 0;
            font-size: 7.5pt;
        }
        .bill-status-bar .status-label { font-weight: 600; color: #64748B; }
        .bill-status-bar .item-count { color: #94A3B8; margin-left: auto; }
        
        .consultation-fee-bar {
            margin-top: 4px;
            padding: 4px 12px;
            background: linear-gradient(135deg, #EBF4FF, #F8FAFC);
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            font-size: 8pt;
            border: 1px solid #93C5FD;
        }
        .consultation-fee-bar .label { color: #64748B; font-weight: 500; }
        .consultation-fee-bar .value { font-weight: 700; color: #0B5ED7; }
        
        /* ================================================================ */
        /* EMPTY STATE */
        /* ================================================================ */
        .empty-state-pdf {
            text-align: center;
            padding: 8px;
            color: #94A3B8;
            font-size: 8pt;
            background: #F8FAFC;
            border-radius: 4px;
            border: 1px dashed #E2E8F0;
        }
        
        /* ================================================================ */
        /* FOOTER WITH MOTTO */
        /* ================================================================ */
        .pdf-footer {
            margin-top: 18px;
            padding-top: 14px;
            border-top: 3px solid #E2E8F0;
            position: relative;
        }
        .pdf-footer::before {
            content: '';
            position: absolute;
            top: -3px;
            left: 25%;
            right: 25%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #0B5ED7, transparent);
        }
        .pdf-footer .footer-stamp-area {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 12px;
        }
        .pdf-footer .footer-left {
            font-size: 7.5pt;
            color: #475569;
            line-height: 1.8;
        }
        .pdf-footer .signature-line-area {
            margin-top: 4px;
        }
        .pdf-footer .signature-line {
            display: inline-block;
            width: 80px;
            border-bottom: 2px solid #475569;
            margin-right: 4px;
        }
        .pdf-footer .signature-label {
            font-size: 6pt;
            color: #94A3B8;
        }
        .pdf-footer .stamp-box {
            text-align: center;
            padding: 6px 16px;
            border: 3px solid #0B5ED7;
            border-radius: 8px;
            background: linear-gradient(135deg, #EBF4FF, #F8FAFC);
            min-width: 140px;
            box-shadow: 0 2px 8px rgba(11,94,215,0.06);
        }
        .pdf-footer .stamp-box .stamp-title {
            font-size: 5.5pt;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        .pdf-footer .stamp-box .stamp-name {
            font-size: 10pt;
            font-weight: 800;
            color: #0B5ED7;
            letter-spacing: 0.5px;
        }
        .pdf-footer .stamp-box .stamp-line {
            font-size: 6.5pt;
            color: #475569;
            margin-top: 2px;
        }
        .pdf-footer .stamp-box .stamp-date {
            font-size: 5.5pt;
            color: #94A3B8;
            margin-top: 2px;
        }
        
        .pdf-footer .footer-motto {
            text-align: center;
            margin-top: 10px;
            padding: 6px 0;
            font-size: 9pt;
            font-weight: 600;
            border-top: 2px solid #E2E8F0;
        }
        .pdf-footer .footer-motto .brand {
            color: #0B5ED7;
        }
        .pdf-footer .footer-motto .motto-text {
            color: #059669;
        }
        .pdf-footer .footer-bottom {
            text-align: center;
            margin-top: 4px;
            font-size: 6.5pt;
            color: #94A3B8;
        }
        
        /* ================================================================ */
        /* HEADER ACTIONS (NO-PRINT) */
        /* ================================================================ */
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .header-actions .btn {
            padding: 6px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .header-actions .btn-primary-pdf {
            background: #0B5ED7;
            color: white;
        }
        .header-actions .btn-primary-pdf:hover {
            background: #0A4CA8;
            transform: translateY(-1px);
        }
        .header-actions .btn-outline-pdf {
            background: transparent;
            color: #475569;
            border: 2px solid #E2E8F0;
        }
        .header-actions .btn-outline-pdf:hover {
            border-color: #0B5ED7;
            color: #0B5ED7;
        }
        .header-actions .btn-success-pdf {
            background: #059669;
            color: white;
        }
        .header-actions .btn-success-pdf:hover {
            background: #047857;
        }
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 768px) {
            .pdf-header {
                flex-direction: column;
                text-align: center;
            }
            .header-contact { text-align: center; }
            .header-contact .contact-row { justify-content: center; }
            .vital-grid-cards { grid-template-columns: repeat(3, 1fr); }
            .info-grid-2 { grid-template-columns: 1fr; }
            .info-grid-3 { grid-template-columns: 1fr; }
            .bill-grid-pdf { grid-template-columns: 1fr; }
            .pdf-footer .footer-stamp-area { flex-direction: column; align-items: center; }
            #pdfContent { padding: 16px; }
            .clinic-name { font-size: 16pt; }
            .doc-title { font-size: 13pt; }
        }
        
        @media (max-width: 480px) {
            .vital-grid-cards { grid-template-columns: 1fr 1fr; }
            .header-logo-area { flex-direction: column; }
            .header-logo-img { height: 40px; }
            .pdf-container { border-radius: 0; }
            #pdfContent { padding: 12px; }
        }
        
        @media print {
            body { background: white; padding: 0; }
            .pdf-container { box-shadow: none; border-radius: 0; }
            #pdfContent { padding: 20px; }
            .header-actions { display: none !important; }
            .vital-card-pdf { break-inside: avoid; }
            .pdf-table { break-inside: auto; }
            .prescription-box { break-inside: avoid; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="pdf-container">
    <!-- Header with Actions (No-Print) -->
    <div class="pdf-header no-print" style="border-bottom: none; padding-bottom: 10px; margin-bottom: 0;">
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:1.2rem;">📄</span>
            <span style="font-weight:600;font-size:0.95rem;color:#0B5ED7;">Consultation Report</span>
        </div>
        <div class="header-actions">
            <button onclick="window.print()" class="btn btn-outline-pdf">
                <i class="fas fa-print"></i> Print
            </button>
            <button onclick="downloadPDF()" class="btn btn-primary-pdf">
                <i class="fas fa-file-pdf"></i> Download PDF
            </button>
            <a href="my_patients.php" class="btn btn-outline-pdf">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
    
    <!-- PDF Content -->
    <div id="pdfContent">
        <?php buildPDFContent(
            $visit, 
            $vital_signs, 
            $lab_results, 
            $lab_requests, 
            $prescriptions, 
            $prescription_items, 
            $procedures, 
            $equipment_items, 
            $bill_items, 
            $total_bill_amount, 
            $paid_total, 
            $bill_balance, 
            $bill_status, 
            $branch_location, 
            $branch_phone, 
            $doctor_branch_name, 
            $doctor_name, 
            $logo_base64, 
            $admin_phone,
            $diagnosis_display,
            $disease_code_display,
            $treatment_display
        ); ?>
    </div>
</div>

<script>
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [8, 10, 8, 10],
            filename: 'Consultation_<?= htmlspecialchars($visit['visit_number'] ?? 'visit') ?>_<?= date('Ymd') ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                useCORS: true, 
                backgroundColor: '#ffffff', 
                logging: false,
                allowTaint: true
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait' 
            },
            pagebreak: { mode: 'avoid-all' }
        };
        html2pdf().set(opt).from(element).save();
    }
    
    console.log('📄 BRAICK DISPENSARY - Consultation PDF Report');
    console.log('📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>');
    console.log('👤 Patient: <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>');
    console.log('👨‍⚕️ Doctor: <?= htmlspecialchars($visit['doctor_name'] ?? $doctor_name) ?>');
    console.log('🩺 Diagnosis (from visits): <?= htmlspecialchars($diagnosis_display ?: 'Not recorded') ?>');
    console.log('🔑 Disease Code (from visits): <?= htmlspecialchars($disease_code_display ?: 'Not recorded') ?>');
    console.log('💊 Treatment (from visits): <?= htmlspecialchars($treatment_display ?: 'Not recorded') ?>');
    console.log('🏢 Branch: <?= htmlspecialchars($branch_location ?: $doctor_branch_name) ?>');
    console.log('📞 Phone: <?= htmlspecialchars($branch_phone ?: $admin_phone) ?>');
    console.log('💙 Motto: BRAICK DISPENSARY - TUNAJALI AFYA YAKO');
    console.log('✅ FIXED: Diagnosis shows directly from visits.diagnosis column');
</script>

</body>
</html>