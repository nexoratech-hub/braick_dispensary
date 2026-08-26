<?php
// ================================================================
// FILE: frontend/pages/doctor/refer_patient_pdf.php
// DOCTOR - REFER PATIENT PDF (EXTERNAL REFERRAL)
// PERFECT PDF - NEW WINDOW WITHOUT SIDEBAR/HEADER
// REDESIGNED WITH SINGLE LOGO, LAST VISIT INFO, REFERRED HOSPITAL
// FIXED: Removed 'equipment_used' column error
// BRAICK DISPENSARY
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
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_online = $_SESSION['is_online'] ?? 0;
$user_phone = $_SESSION['phone'] ?? '';

// ================================================================
// GET PATIENT ID
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if ($patient_id <= 0) {
    die('Invalid patient ID');
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET ADMIN INFO
// ================================================================
$admin_phone = '';
$admin_email = '';
$admin_name = '';
try {
    $stmt = $db->prepare("SELECT full_name, phone, email FROM users WHERE role = 'admin' AND branch_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$user_branch_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $admin_phone = $admin['phone'] ?? '';
        $admin_email = $admin['email'] ?? '';
        $admin_name = $admin['full_name'] ?? 'Admin';
    }
    if (empty($admin_phone)) {
        $stmt = $db->prepare("SELECT full_name, phone, email FROM users WHERE role = 'admin' AND status = 'active' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        $admin_phone = $admin['phone'] ?? '';
        $admin_email = $admin['email'] ?? '';
        $admin_name = $admin['full_name'] ?? 'Admin';
    }
} catch (Exception $e) {
    $admin_phone = '';
    $admin_email = '';
    $admin_name = 'Admin';
}

// ================================================================
// GET PATIENT DETAILS
// ================================================================
$patient = null;
$last_visit = null;
$medications = [];
$procedures = [];
$diagnosis = '';
$treatment = '';
$disease_code = '';
$disease_name = '';
$symptoms = '';
$hpi = '';
$physical_exam = '';
$vital_signs = null;
$lab_tests = [];
$procedure_list = [];
$complaint = '';
$notes = '';
$visit_number = '';
$consultation_fee = 0;
$visit_status = '';
$doctor_name = '';

try {
    $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        die('Patient not found');
    }
    
    // Get latest visit
    $stmt = $db->prepare("
        SELECT v.id, v.visit_number, v.diagnosis, v.symptoms, v.hpi, v.physical_exam, 
               v.treatment, v.disease_code, v.created_at, v.status, v.consultation_fee,
               v.disease_id, v.complaint, v.notes, v.doctor_id,
               u.full_name as doctor_name, u.specialty as doctor_specialty
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ?
        ORDER BY v.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $visit_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($visit_info) {
        $visit_number = $visit_info['visit_number'] ?? '';
        $diagnosis = $visit_info['diagnosis'] ?? '';
        $treatment = $visit_info['treatment'] ?? '';
        $disease_code = $visit_info['disease_code'] ?? '';
        $symptoms = $visit_info['symptoms'] ?? '';
        $hpi = $visit_info['hpi'] ?? '';
        $physical_exam = $visit_info['physical_exam'] ?? '';
        $complaint = $visit_info['complaint'] ?? '';
        $notes = $visit_info['notes'] ?? '';
        $consultation_fee = $visit_info['consultation_fee'] ?? 0;
        $visit_status = $visit_info['status'] ?? '';
        $doctor_name = $visit_info['doctor_name'] ?? '';
        $last_visit = $visit_info;
        
        if (!empty($visit_info['disease_id'])) {
            $stmt_disease = $db->prepare("SELECT disease_name FROM diseases WHERE id = ?");
            $stmt_disease->execute([$visit_info['disease_id']]);
            $disease = $stmt_disease->fetch(PDO::FETCH_ASSOC);
            if ($disease) {
                $disease_name = $disease['disease_name'];
            }
        }
        
        if (empty($disease_name) && !empty($diagnosis)) {
            $disease_name = $diagnosis;
        }
        
        // Get medications
        $stmt = $db->prepare("
            SELECT pi.medication_name, pi.dosage, pi.frequency, pi.quantity, pi.instructions, pi.total_price,
                   pi.duration, pi.route, pi.unit_price, pi.created_at
            FROM prescriptions p
            JOIN prescription_items pi ON p.id = pi.prescription_id
            WHERE p.visit_id = ?
        ");
        $stmt->execute([$visit_info['id']]);
        $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get procedures - FIXED: Removed equipment_used column
        $stmt = $db->prepare("
            SELECT p.id, p.procedure_name, p.status, p.procedure_price,
                   p.category, p.created_at
            FROM procedures p
            WHERE p.visit_id = ? AND p.status != 'cancelled'
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$visit_info['id']]);
        $procedure_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get equipment for procedures from bill_items
        $equipment_names = [];
        try {
            $stmt_eq = $db->prepare("
                SELECT bi.item_name, bi.quantity, bi.created_at
                FROM bill_items bi
                JOIN bills b ON bi.bill_id = b.id
                WHERE b.visit_id = ? AND bi.item_type IN ('equipment', 'tool')
                AND bi.status != 'cancelled'
                ORDER BY bi.created_at DESC
            ");
            $stmt_eq->execute([$visit_info['id']]);
            $equipment_items = $stmt_eq->fetchAll(PDO::FETCH_ASSOC);
            foreach ($equipment_items as $eq) {
                $equipment_names[] = $eq['item_name'];
            }
        } catch (Exception $e) {
            $equipment_names = [];
        }
        
        // Merge procedures with equipment info
        foreach ($procedure_list as &$proc) {
            $proc['equipment_name'] = !empty($equipment_names) ? implode(', ', $equipment_names) : 'None';
        }
        unset($proc);
        
        // Get lab tests
        $stmt = $db->prepare("
            SELECT lt.test_name, lt.results, lt.status, lt.test_price, lt.created_at,
                   u.full_name as lab_technician_name
            FROM lab_tests lt
            LEFT JOIN users u ON lt.performed_by = u.id
            WHERE lt.visit_id = ?
            ORDER BY lt.created_at DESC
        ");
        $stmt->execute([$visit_info['id']]);
        $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get vital signs
        $stmt = $db->prepare("
            SELECT temperature, blood_pressure_systolic, blood_pressure_diastolic,
                   pulse_rate, weight, height, bmi, notes, recorded_at,
                   u.full_name as recorded_by_name
            FROM vital_signs vs
            LEFT JOIN users u ON vs.recorded_by = u.id
            WHERE vs.visit_id = ?
            ORDER BY vs.recorded_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$visit_info['id']]);
        $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    die("Error fetching patient data: " . $e->getMessage());
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getLogoHTML() {
    $logo_paths = [
        '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png',
        '/dispensary_system/frontend/assets/uploads/profiles/logo.png',
        '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.jpg',
        '/dispensary_system/frontend/assets/uploads/profiles/logo.jpg',
        '/dispensary_system/frontend/assets/img/braick_logo.png',
        '/dispensary_system/frontend/assets/img/logo.png'
    ];
    
    $logo_url = '';
    foreach ($logo_paths as $path) {
        $full_path = $_SERVER['DOCUMENT_ROOT'] . $path;
        if (file_exists($full_path)) {
            $logo_url = $path;
            break;
        }
    }
    
    if (!empty($logo_url)) {
        return '<img src="' . $logo_url . '" alt="Braick Dispensary" style="height:60px;width:auto;max-height:60px;border-radius:6px;object-fit:contain;">';
    }
    
    return '<div style="display:inline-block;background:#0B5ED7;color:white;padding:6px 18px;border-radius:6px;font-size:18px;font-weight:bold;">BRAICK</div>';
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getVitalStatus($value, $type) {
    if ($value === null || $value === '' || $value === '--') return ['label' => 'N/A', 'class' => 'unknown'];
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

$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$user_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $doctor_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $doctor_branch_name = 'Branch';
}

// ================================================================
// GET LOGO
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_path = $_SERVER['DOCUMENT_ROOT'] . $logo_url;
if (!file_exists($logo_path)) {
    $logo_url = '/dispensary_system/frontend/assets/uploads/profiles/logo.png';
}

// ================================================================
// BUILD PDF CONTENT
// ================================================================
$pdf_content = '';

// ================================================================
// SINGLE PAGE - ALL CONTENT
// ================================================================
$pdf_content .= '<div style="font-family: Arial, sans-serif; font-size: 10pt; color: #1E293B; line-height: 1.6; max-width: 100%; margin: 0 auto; padding: 20px;">';

// ================================================================
// HEADER - SINGLE LOGO
// ================================================================
$pdf_content .= '
<div style="text-align: center; border-bottom: 3px double #0B5ED7; padding-bottom: 14px; margin-bottom: 16px;">
    <div style="display: flex; align-items: center; justify-content: center; gap: 14px; margin-bottom: 4px; flex-wrap: wrap;">
        ' . getLogoHTML() . '
        <div>
            <div style="font-size: 24px; font-weight: 800; color: #0B5ED7; letter-spacing: 2px;">BRAICK DISPENSARY</div>
            <div style="font-size: 10px; color: #059669; letter-spacing: 3px; text-transform: uppercase; font-weight: 600;">Tunajali Afya Yako</div>
        </div>
    </div>
    <div style="font-size: 14px; font-weight: 700; color: #0B5ED7; background: #E8F0FE; padding: 4px 20px; border-radius: 20px; display: inline-block; margin-top: 4px; border: 2px solid #6EA8FE;">
        📋 EXTERNAL REFERRAL LETTER
    </div>
    <div style="font-size: 8.5px; color: #64748B; margin-top: 5px; display: flex; justify-content: center; gap: 18px; flex-wrap: wrap;">
        <span>📞 ' . htmlspecialchars($admin_phone ?: $user_phone) . '</span>
        <span>📍 ' . htmlspecialchars($doctor_branch_name) . '</span>
        <span>📅 ' . date('d/m/Y') . '</span>
        ' . (!empty($admin_email) ? '<span>✉️ ' . htmlspecialchars($admin_email) . '</span>' : '') . '
    </div>
</div>';

// ================================================================
// 1. PATIENT INFORMATION
// ================================================================
$pdf_content .= '
<div style="font-size: 11pt; font-weight: 700; color: #0B5ED7; border-bottom: 2px solid #0B5ED7; padding-bottom: 4px; margin-bottom: 8px;">
    👤 PATIENT INFORMATION
</div>
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3px 16px; margin-bottom: 10px;">
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Patient Name:</span>
        <span style="font-weight: 700; font-size: 9.5pt;">' . htmlspecialchars($patient['full_name'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Patient ID:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($patient['patient_id'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Date of Birth:</span>
        <span style="font-size: 9.5pt;">' . (!empty($patient['date_of_birth']) ? date('d/m/Y', strtotime($patient['date_of_birth'])) . ' (' . calculateAge($patient['date_of_birth']) . ' yrs)' : 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Gender:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($patient['gender'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Phone:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($patient['phone'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Emergency Contact:</span>
        <span style="font-weight: 700; font-size: 9.5pt; color: #DC2626;">' . htmlspecialchars($patient['emergency_contact'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Blood Group:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($patient['blood_group'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Allergies:</span>
        <span style="font-size: 9.5pt; color: #DC2626;">' . htmlspecialchars($patient['allergies'] ?? 'None') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Address:</span>
        <span style="font-size: 9pt;">' . htmlspecialchars($patient['address'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Branch:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($doctor_branch_name) . '</span>
    </div>
</div>';

// ================================================================
// 2. LAST VISIT INFORMATION
// ================================================================
$pdf_content .= '
<div style="font-size: 11pt; font-weight: 700; color: #059669; border-bottom: 2px solid #059669; padding-bottom: 4px; margin-bottom: 8px;">
    🏥 LAST VISIT INFORMATION
</div>
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3px 16px; margin-bottom: 10px;">
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Visit Number:</span>
        <span style="font-weight: 700; font-size: 9.5pt; color: #0B5ED7;">' . htmlspecialchars($visit_number ?: 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Visit Date:</span>
        <span style="font-size: 9.5pt;">' . (!empty($last_visit['created_at']) ? date('d/m/Y h:i A', strtotime($last_visit['created_at'])) : 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Visit Status:</span>
        <span style="font-size: 9.5pt;"><span style="padding: 2px 10px; border-radius: 12px; background: ' . ($visit_status === 'completed' ? '#D1FAE5' : ($visit_status === 'prescribed' ? '#EDE9FE' : '#FEF3C7')) . '; color: ' . ($visit_status === 'completed' ? '#059669' : ($visit_status === 'prescribed' ? '#7C3AED' : '#D97706')) . '; font-weight: 600; font-size: 8pt;">' . ucfirst(str_replace('_', ' ', $visit_status ?: 'Pending')) . '</span></span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Consultation Fee:</span>
        <span style="font-size: 9.5pt; font-weight: 600; color: #0B5ED7;">TSh ' . number_format($consultation_fee, 0) . '</span>
    </div>
</div>';

// ================================================================
// 3. DOCTOR INFORMATION
// ================================================================
$pdf_content .= '
<div style="font-size: 11pt; font-weight: 700; color: #7C3AED; border-bottom: 2px solid #7C3AED; padding-bottom: 4px; margin-bottom: 8px;">
    👨‍⚕️ DOCTOR INFORMATION
</div>
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3px 16px; margin-bottom: 10px;">
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Doctor Name:</span>
        <span style="font-weight: 700; font-size: 9.5pt;">Dr. ' . htmlspecialchars($doctor_name ?: $user_full_name) . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Specialty:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($user_specialty) . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Doctor\'s Phone:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($user_phone ?: $admin_phone) . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Branch:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($doctor_branch_name) . '</span>
    </div>
</div>';

// ================================================================
// 4. VITAL SIGNS - 6 CARDS
// ================================================================
if ($vital_signs) {
    $temp_status = getVitalStatus($vital_signs['temperature'] ?? null, 'temperature');
    $sys = $vital_signs['blood_pressure_systolic'] ?? null;
    $bp_status = getVitalStatus($sys, 'systolic');
    $pulse_status = getVitalStatus($vital_signs['pulse_rate'] ?? null, 'pulse');
    $bmi_status = getVitalStatus($vital_signs['bmi'] ?? null, 'bmi');
    
    $pdf_content .= '
    <div style="font-size: 11pt; font-weight: 700; color: #DC2626; border-bottom: 2px solid #DC2626; padding-bottom: 4px; margin-top: 10px; margin-bottom: 8px;">
        ❤️ VITAL SIGNS
        ' . (!empty($vital_signs['recorded_at']) ? '<span style="font-size: 8pt; font-weight: 400; color: #64748B;">(Recorded: ' . date('d/m/Y h:i A', strtotime($vital_signs['recorded_at'])) . ')</span>' : '') . '
        ' . (!empty($vital_signs['recorded_by_name']) ? '<span style="font-size: 8pt; font-weight: 400; color: #64748B;"> | By: ' . htmlspecialchars($vital_signs['recorded_by_name']) . '</span>' : '') . '
    </div>
    <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; margin-bottom: 12px;">
        <div style="background: #F8FAFC; border-radius: 6px; padding: 6px 4px; border-left: 3px solid #DC2626; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size: 16px;">🌡️</div>
            <div style="font-size: 7px; font-weight: 600; color: #64748B; text-transform: uppercase;">Temperature</div>
            <div style="font-size: 12px; font-weight: 700; color: #DC2626;">' . ($vital_signs['temperature'] ?? '--') . ' <span style="font-size: 7px; color: #64748B;">°C</span></div>
            <div style="font-size: 7px; font-weight: 700; padding: 1px 6px; border-radius: 4px; display: inline-block; background: ' . ($temp_status['class'] === 'normal' ? '#D1FAE5' : ($temp_status['class'] === 'high' ? '#FEE2E2' : '#FEF3C7')) . '; color: ' . ($temp_status['class'] === 'normal' ? '#059669' : ($temp_status['class'] === 'high' ? '#DC2626' : '#D97706')) . ';">' . $temp_status['label'] . '</div>
        </div>
        <div style="background: #F8FAFC; border-radius: 6px; padding: 6px 4px; border-left: 3px solid #0B5ED7; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size: 16px;">❤️</div>
            <div style="font-size: 7px; font-weight: 600; color: #64748B; text-transform: uppercase;">Blood Pressure</div>
            <div style="font-size: 12px; font-weight: 700; color: #0B5ED7;">' . ($vital_signs['blood_pressure_systolic'] ?? '--') . '/' . ($vital_signs['blood_pressure_diastolic'] ?? '--') . ' <span style="font-size: 7px; color: #64748B;">mmHg</span></div>
            <div style="font-size: 7px; font-weight: 700; padding: 1px 6px; border-radius: 4px; display: inline-block; background: ' . ($bp_status['class'] === 'normal' ? '#D1FAE5' : ($bp_status['class'] === 'high' ? '#FEE2E2' : '#FEF3C7')) . '; color: ' . ($bp_status['class'] === 'normal' ? '#059669' : ($bp_status['class'] === 'high' ? '#DC2626' : '#D97706')) . ';">' . $bp_status['label'] . '</div>
        </div>
        <div style="background: #F8FAFC; border-radius: 6px; padding: 6px 4px; border-left: 3px solid #7C3AED; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size: 16px;">💓</div>
            <div style="font-size: 7px; font-weight: 600; color: #64748B; text-transform: uppercase;">Pulse Rate</div>
            <div style="font-size: 12px; font-weight: 700; color: #7C3AED;">' . ($vital_signs['pulse_rate'] ?? '--') . ' <span style="font-size: 7px; color: #64748B;">bpm</span></div>
            <div style="font-size: 7px; font-weight: 700; padding: 1px 6px; border-radius: 4px; display: inline-block; background: ' . ($pulse_status['class'] === 'normal' ? '#D1FAE5' : ($pulse_status['class'] === 'high' ? '#FEE2E2' : '#FEF3C7')) . '; color: ' . ($pulse_status['class'] === 'normal' ? '#059669' : ($pulse_status['class'] === 'high' ? '#DC2626' : '#D97706')) . ';">' . $pulse_status['label'] . '</div>
        </div>
        <div style="background: #F8FAFC; border-radius: 6px; padding: 6px 4px; border-left: 3px solid #D97706; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size: 16px;">⚖️</div>
            <div style="font-size: 7px; font-weight: 600; color: #64748B; text-transform: uppercase;">Weight</div>
            <div style="font-size: 12px; font-weight: 700; color: #D97706;">' . ($vital_signs['weight'] ?? '--') . ' <span style="font-size: 7px; color: #64748B;">kg</span></div>
        </div>
        <div style="background: #F8FAFC; border-radius: 6px; padding: 6px 4px; border-left: 3px solid #0D9488; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size: 16px;">📏</div>
            <div style="font-size: 7px; font-weight: 600; color: #64748B; text-transform: uppercase;">Height</div>
            <div style="font-size: 12px; font-weight: 700; color: #0D9488;">' . ($vital_signs['height'] ?? '--') . ' <span style="font-size: 7px; color: #64748B;">cm</span></div>
        </div>
        <div style="background: #F8FAFC; border-radius: 6px; padding: 6px 4px; border-left: 3px solid #2563EB; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size: 16px;">📊</div>
            <div style="font-size: 7px; font-weight: 600; color: #64748B; text-transform: uppercase;">BMI</div>
            <div style="font-size: 12px; font-weight: 700; color: #2563EB;">' . ($vital_signs['bmi'] ?? '--') . ' <span style="font-size: 7px; color: #64748B;">kg/m²</span></div>
            <div style="font-size: 7px; font-weight: 700; padding: 1px 6px; border-radius: 4px; display: inline-block; background: ' . ($bmi_status['class'] === 'normal' ? '#D1FAE5' : ($bmi_status['class'] === 'high' ? '#FEE2E2' : '#FEF3C7')) . '; color: ' . ($bmi_status['class'] === 'normal' ? '#059669' : ($bmi_status['class'] === 'high' ? '#DC2626' : '#D97706')) . ';">' . $bmi_status['label'] . '</div>
        </div>
    </div>';
}

// ================================================================
// 5. SYMPTOMS, HPI, PHYSICAL EXAMINATION
// ================================================================
$pdf_content .= '
<div style="font-size: 11pt; font-weight: 700; color: #D97706; border-bottom: 2px solid #D97706; padding-bottom: 4px; margin-bottom: 8px;">
    📋 CLINICAL HISTORY
</div>
<table style="width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 12px; border: 1px solid #E2E8F0;">
    <thead>
        <tr>
            <th style="background: #D97706; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 33.33%;">Symptoms</th>
            <th style="background: #D97706; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 33.33%;">HPI</th>
            <th style="background: #D97706; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 33.33%;">Physical Examination</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top; white-space: pre-wrap; word-wrap: break-word; font-size: 9pt; background: #FAFAFA;">' . nl2br(htmlspecialchars($symptoms ?: 'No symptoms recorded')) . '</td>
            <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top; white-space: pre-wrap; word-wrap: break-word; font-size: 9pt; background: #FAFAFA;">' . nl2br(htmlspecialchars($hpi ?: 'No HPI recorded')) . '</td>
            <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top; white-space: pre-wrap; word-wrap: break-word; font-size: 9pt; background: #FAFAFA;">' . nl2br(htmlspecialchars($physical_exam ?: 'No physical exam recorded')) . '</td>
        </tr>
    </tbody>
</table>';

// ================================================================
// 6. LAB TESTS
// ================================================================
if (count($lab_tests) > 0) {
    $pdf_content .= '
    <div style="font-size: 11pt; font-weight: 700; color: #7C3AED; border-bottom: 2px solid #7C3AED; padding-bottom: 4px; margin-bottom: 8px;">
        🧪 LABORATORY TESTS
    </div>
    <table style="width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 12px; border: 1px solid #E2E8F0;">
        <thead>
            <tr>
                <th style="background: #7C3AED; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase;">Test Name</th>
                <th style="background: #7C3AED; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase;">Results</th>
                <th style="background: #7C3AED; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase;">Lab Technician</th>
            </tr>
        </thead>
        <tbody>';
    foreach ($lab_tests as $test) {
        $result_display = !empty($test['results']) ? htmlspecialchars($test['results']) : '⏳ Pending';
        $result_style = !empty($test['results']) ? 'color: #059669; font-weight: 600;' : 'color: #94A3B8;';
        $pdf_content .= '
            <tr>
                <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; font-weight: 600; font-size: 9pt;">' . htmlspecialchars($test['test_name'] ?? 'N/A') . '</td>
                <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; font-size: 9pt; ' . $result_style . '">' . $result_display . '</td>
                <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; font-size: 9pt;">' . htmlspecialchars($test['lab_technician_name'] ?? 'Not assigned') . '</td>
            </tr>';
    }
    $pdf_content .= '
        </tbody>
    </table>';
}

// ================================================================
// 7. DIAGNOSIS
// ================================================================
$pdf_content .= '
<div style="font-size: 11pt; font-weight: 700; color: #059669; border-bottom: 2px solid #059669; padding-bottom: 4px; margin-bottom: 8px;">
    🩺 DIAGNOSIS
</div>
<table style="width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 12px; border: 1px solid #E2E8F0;">
    <thead>
        <tr>
            <th style="background: #059669; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 33.33%;">Disease Name</th>
            <th style="background: #059669; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 33.33%;">Disease Code</th>
            <th style="background: #059669; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 33.33%;">Treatment</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; font-weight: 600; font-size: 9pt; color: #0B5ED7; background: #FAFAFA;">' . htmlspecialchars($disease_name ?: ($diagnosis ?: 'No diagnosis recorded')) . '</td>
            <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; font-size: 9pt; font-family: monospace; background: #FAFAFA;">' . htmlspecialchars($disease_code ?: 'N/A') . '</td>
            <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; font-size: 9pt; word-wrap: break-word; background: #FAFAFA;">' . nl2br(htmlspecialchars($treatment ?: 'No treatment recorded')) . '</td>
        </tr>
    </tbody>
</table>';

// ================================================================
// 8. PROCEDURES - FIXED: Using correct data
// ================================================================
if (count($procedure_list) > 0) {
    $pdf_content .= '
    <div style="font-size: 11pt; font-weight: 700; color: #D97706; border-bottom: 2px solid #D97706; padding-bottom: 4px; margin-bottom: 8px;">
        💉 PROCEDURES
    </div>
    <table style="width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 12px; border: 1px solid #E2E8F0;">
        <thead>
            <tr>
                <th style="background: #D97706; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 50%;">Procedure Name</th>
                <th style="background: #D97706; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 50%;">Status</th>
            </tr>
        </thead>
        <tbody>';
    foreach ($procedure_list as $proc) {
        $status_bg = $proc['status'] === 'completed' ? '#D1FAE5' : '#FEF3C7';
        $status_color = $proc['status'] === 'completed' ? '#059669' : '#D97706';
        $pdf_content .= '
            <tr>
                <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; font-weight: 600; font-size: 9pt; background: #FAFAFA;">' . htmlspecialchars($proc['procedure_name'] ?? 'N/A') . '</td>
                <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; font-size: 9pt; background: #FAFAFA;">
                    <span style="padding: 2px 10px; border-radius: 12px; background: ' . $status_bg . '; color: ' . $status_color . '; font-weight: 600; font-size: 7pt;">' . ucfirst($proc['status'] ?? 'Pending') . '</span>
                </td>
            </tr>';
    }
    $pdf_content .= '
        </tbody>
    </table>';
}

// ================================================================
// 9. REFERRED HOSPITAL INFORMATION
// ================================================================
$pdf_content .= '
<div style="font-size: 11pt; font-weight: 700; color: #0B5ED7; border-bottom: 2px solid #0B5ED7; padding-bottom: 4px; margin-bottom: 8px; margin-top: 10px;">
    🏥 REFERRED HOSPITAL INFORMATION
</div>
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3px 16px; margin-bottom: 10px; border: 1px solid #E2E8F0; border-radius: 6px; padding: 10px 12px; background: #F8FAFC;">
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Hospital Name:</span>
        <span style="font-weight: 700; font-size: 9.5pt; color: #0B5ED7;" id="pdfHospitalName">[Enter Hospital Name]</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Specialist / Expert Type:</span>
        <span style="font-size: 9.5pt;" id="pdfExpertType">[Enter Specialist Type]</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0; grid-column: span 2;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Reason(s) for Referral:</span>
        <span style="font-size: 9.5pt; white-space: pre-wrap; word-wrap: break-word;" id="pdfReason">[Enter Reason for Referral]</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0; grid-column: span 2;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Additional Notes:</span>
        <span style="font-size: 9.5pt; white-space: pre-wrap; word-wrap: break-word;" id="pdfAdditionalNotes">[Enter Additional Notes]</span>
    </div>
</div>';

// ================================================================
// FOOTER WITH OFFICIAL STAMP
// ================================================================
$pdf_content .= '
<div style="border-top: 2px solid #0B5ED7; padding-top: 10px; margin-top: 12px; text-align: center;">
    <div style="font-size: 11px; font-weight: 600; color: #059669;">
        💙 <span style="color: #0B5ED7; font-weight: 800;">BRAICK DISPENSARY</span> - TUNAJALI AFYA YAKO
    </div>
    <div style="font-size: 8px; color: #64748B; margin-top: 3px; display: flex; justify-content: center; gap: 14px; flex-wrap: wrap;">
        <span><strong>Admin:</strong> ' . htmlspecialchars($admin_name) . '</span>
        <span><strong>Phone:</strong> ' . htmlspecialchars($admin_phone ?: $user_phone) . '</span>
        <span><strong>Email:</strong> ' . htmlspecialchars($admin_email ?: 'info@braick.com') . '</span>
        <span><strong>Date:</strong> ' . date('d/m/Y') . '</span>
    </div>
    <div style="margin-top: 6px; padding: 6px 20px; border: 3px solid #0B5ED7; border-radius: 8px; display: inline-block; background: #E8F0FE; font-weight: 600; color: #0B5ED7; min-width: 180px;">
        <div style="font-size: 7px; color: #64748B; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700;">Official Stamp</div>
        <div style="font-size: 13px; font-weight: 800; color: #0B5ED7;">BRAICK DISPENSARY</div>
        <div style="font-size: 7px; color: #64748B; margin-top: 2px;">Approved By: _________________</div>
        <div style="font-size: 7px; color: #64748B;">Date: ' . date('d/m/Y') . '</div>
    </div>
    <div style="font-size: 7px; color: #94A3B8; margin-top: 4px;">This is a computer-generated document. No signature required.</div>
</div>';

$pdf_content .= '</div>'; // End page

// ================================================================
// OUTPUT THE PDF
// ================================================================
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Referral Letter - <?= htmlspecialchars($patient['full_name'] ?? 'Patient') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            margin: 0; 
            padding: 10px; 
            background: #f0f0f0; 
            font-family: Arial, sans-serif;
        }
        .pdf-container {
            max-width: 850px;
            margin: 0 auto;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .pdf-actions {
            text-align: center;
            padding: 12px 0;
            border-bottom: 2px solid #E2E8F0;
            margin-bottom: 12px;
        }
        .pdf-actions button {
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #0B5ED7;
            color: white;
        }
        .btn-primary:hover {
            background: #0A4CA8;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #059669;
            color: white;
        }
        .btn-success:hover {
            background: #047857;
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #DC2626;
            color: white;
        }
        .btn-danger:hover {
            background: #B91C1C;
            transform: translateY(-2px);
        }
        .pdf-content {
            background: white;
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #E2E8F0;
        }
        .pdf-content table {
            width: 100%;
            border-collapse: collapse;
        }
        .pdf-content table td, .pdf-content table th {
            padding: 3px 6px;
        }
        @media print {
            .pdf-actions { display: none !important; }
            body { background: white; padding: 0; }
            .pdf-container { box-shadow: none; padding: 0; border-radius: 0; max-width: 100%; }
            .pdf-content { border: none; padding: 0; }
        }
        @media (max-width: 600px) {
            .pdf-container { padding: 8px; }
            .pdf-actions button { padding: 6px 12px; font-size: 11px; margin: 3px; }
            .pdf-content { font-size: 8pt; }
        }
    </style>
</head>
<body>
    <div class="pdf-container">
        <div class="pdf-actions">
            <button onclick="window.print()" class="btn-primary"><i class="fas fa-print"></i> Print</button>
            <button onclick="downloadPDF()" class="btn-success"><i class="fas fa-file-pdf"></i> Download PDF</button>
            <button onclick="window.close()" class="btn-danger"><i class="fas fa-times"></i> Close</button>
        </div>
        <div class="pdf-content" id="pdfContent">
            <?= $pdf_content ?>
        </div>
    </div>

    <script>
        function downloadPDF() {
            var element = document.getElementById('pdfContent');
            var btn = event.target || document.querySelector('.btn-success');
            var originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            btn.disabled = true;
            
            var opt = {
                margin: [8, 8, 8, 8],
                filename: 'Referral_Letter_<?= htmlspecialchars($patient['full_name'] ?? 'Patient') ?>_<?= date('Ymd') ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2, 
                    useCORS: true, 
                    backgroundColor: '#ffffff', 
                    logging: false,
                    width: 800
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait' 
                },
                pagebreak: { mode: 'avoid-all' }
            };
            
            html2pdf().set(opt).from(element).save().then(function() {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }).catch(function(err) {
                console.error(err);
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert('Failed to generate PDF. Please try again.');
            });
        }
        
        console.log('✅ Referral Letter PDF - <?= htmlspecialchars($patient['full_name'] ?? 'Patient') ?>');
        console.log('✅ Single Logo - Braick Dispensary');
        console.log('✅ Patient Information');
        console.log('✅ Last Visit Information');
        console.log('✅ Doctor Information');
        console.log('✅ Vital Signs (6 cards)');
        console.log('✅ Symptoms, HPI, Physical Examination');
        console.log('✅ Lab Tests');
        console.log('✅ Diagnosis (Disease Name, Code, Treatment)');
        console.log('✅ Procedures');
        console.log('✅ Referred Hospital Information');
        console.log('✅ Official Stamp included');
        console.log('✅ FIXED: Removed equipment_used column error');
    </script>
</body>
</html>