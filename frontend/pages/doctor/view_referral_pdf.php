<?php
// ================================================================
// FILE: frontend/pages/doctor/view_referral_pdf.php
// DOCTOR - VIEW REFERRAL PDF
// Data from referrals table
// PERFECT PDF - NO TEXT CUTOFF
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
$user_phone = $_SESSION['phone'] ?? '';

// ================================================================
// GET REFERRAL ID
// ================================================================
$referral_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($referral_id <= 0) {
    die('Invalid referral ID');
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
// GET REFERRAL DETAILS - FROM referrals TABLE
// ================================================================
try {
    $sql = "
        SELECT 
            r.id,
            r.referral_number,
            r.visit_id,
            r.patient_id,
            r.from_doctor_id,
            r.referral_type,
            r.to_doctor_id,
            r.to_hospital_name,
            r.to_hospital_address,
            r.to_hospital_phone,
            r.reason,
            r.clinical_notes,
            r.diagnosis,
            r.treatment_given,
            r.urgency,
            r.status,
            r.referral_date,
            r.created_by,
            r.branch_id,
            r.created_at,
            r.updated_at,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            p.email as patient_email,
            p.date_of_birth,
            p.gender,
            p.address,
            p.blood_group,
            p.allergies,
            p.emergency_contact,
            u_from.full_name as from_doctor_name,
            u_from.specialty as from_doctor_specialty,
            u_from.phone as from_doctor_phone,
            u_to.full_name as to_doctor_name,
            u_to.specialty as to_doctor_specialty,
            u_to.phone as to_doctor_phone,
            v.visit_number,
            v.diagnosis as visit_diagnosis,
            v.symptoms,
            v.hpi,
            v.physical_exam,
            v.complaint,
            v.treatment,
            v.notes,
            v.created_at as visit_created_at,
            v.status as visit_status,
            v.disease_id,
            v.disease_code,
            b.name as branch_name,
            d.disease_name,
            d.disease_code as disease_code_from_db
        FROM referrals r
        LEFT JOIN patients p ON r.patient_id = p.id
        LEFT JOIN visits v ON r.visit_id = v.id
        LEFT JOIN users u_from ON r.from_doctor_id = u_from.id
        LEFT JOIN users u_to ON r.to_doctor_id = u_to.id
        LEFT JOIN branches b ON r.branch_id = b.id
        LEFT JOIN diseases d ON v.disease_id = d.id
        WHERE r.id = ?
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$referral_id]);
    $referral = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$referral) {
        die('Referral not found');
    }
    
    // Check if doctor has access
    if ($referral['from_doctor_id'] != $user_id && $referral['to_doctor_id'] != $user_id) {
        die('Access denied');
    }
    
} catch (Exception $e) {
    die("Error fetching referral data: " . $e->getMessage());
}

// ================================================================
// GET DISEASE NAME FROM DISEASES TABLE
// ================================================================
$disease_name = $referral['disease_name'] ?? '';
$disease_code = $referral['disease_code_from_db'] ?? $referral['disease_code'] ?? '';

if (empty($disease_name) && !empty($referral['visit_diagnosis'])) {
    $disease_name = $referral['visit_diagnosis'];
}

// ================================================================
// GET VITAL SIGNS FOR THIS PATIENT
// ================================================================
$vital_signs = null;
try {
    if (!empty($referral['visit_id'])) {
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
        $stmt->execute([$referral['visit_id']]);
        $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $vital_signs = null;
}

// ================================================================
// GET LAB TESTS FOR THIS VISIT
// ================================================================
$lab_tests = [];
try {
    if (!empty($referral['visit_id'])) {
        $stmt = $db->prepare("
            SELECT lt.test_name, lt.results, lt.status, lt.test_price, lt.created_at,
                   u.full_name as lab_technician_name
            FROM lab_tests lt
            LEFT JOIN users u ON lt.performed_by = u.id
            WHERE lt.visit_id = ?
            ORDER BY lt.created_at DESC
        ");
        $stmt->execute([$referral['visit_id']]);
        $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $lab_tests = [];
}

// ================================================================
// GET PRESCRIPTIONS FOR THIS VISIT
// ================================================================
$medications = [];
try {
    if (!empty($referral['visit_id'])) {
        $stmt = $db->prepare("
            SELECT pi.medication_name, pi.dosage, pi.frequency, pi.quantity, pi.instructions, pi.total_price,
                   pi.duration, pi.route, pi.unit_price, pi.created_at
            FROM prescriptions p
            JOIN prescription_items pi ON p.id = pi.prescription_id
            WHERE p.visit_id = ?
            ORDER BY pi.created_at DESC
        ");
        $stmt->execute([$referral['visit_id']]);
        $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $medications = [];
}

// ================================================================
// GET PROCEDURES FOR THIS VISIT
// ================================================================
$procedures = [];
try {
    if (!empty($referral['visit_id'])) {
        $stmt = $db->prepare("
            SELECT p.id, p.procedure_name, p.status, p.procedure_price,
                   p.category, p.created_at
            FROM procedures p
            WHERE p.visit_id = ? AND p.status != 'cancelled'
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$referral['visit_id']]);
        $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $procedures = [];
}

// ================================================================
// GET BRANCH NAME
// ================================================================
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

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('M d, Y h:i A', strtotime($datetime));
}

function formatDateShort($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('M d, Y', strtotime($datetime));
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'accepted': return 'badge-success';
        case 'completed': return 'badge-info';
        case 'rejected': return 'badge-danger';
        case 'pending': return 'badge-warning';
        case 'cancelled': return 'badge-danger';
        default: return 'badge-warning';
    }
}

function getReferralTypeLabel($type) {
    if ($type === 'internal') {
        return '🏥 Internal Referral';
    } else {
        return '🌍 External Referral';
    }
}

function getUrgencyLabel($urgency) {
    switch ($urgency) {
        case 'emergency': return '🔴 Emergency';
        case 'urgent': return '🟡 Urgent';
        case 'routine': return '🟢 Routine';
        default: return '🟢 Routine';
    }
}

// ================================================================
// GET LOGO
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// BUILD PDF CONTENT
// ================================================================
$pdf_content = '';

// ================================================================
// PAGE 1 - ALL CONTENT WITH NO TEXT CUTOFF
// ================================================================
$pdf_content .= '<div style="font-family: Arial, sans-serif; font-size: 10pt; color: #1E293B; line-height: 1.6; max-width: 100%; margin: 0 auto; padding: 20px; page-break-after: avoid;">';

// ================================================================
// HEADER
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
        📋 REFERRAL LETTER
    </div>
    <div style="font-size: 8.5px; color: #64748B; margin-top: 5px; display: flex; justify-content: center; gap: 18px; flex-wrap: wrap;">
        <span>📞 ' . htmlspecialchars($admin_phone ?: $user_phone) . '</span>
        <span>📍 ' . htmlspecialchars($doctor_branch_name) . '</span>
        <span>📅 ' . date('d/m/Y') . '</span>
        ' . (!empty($admin_email) ? '<span>✉️ ' . htmlspecialchars($admin_email) . '</span>' : '') . '
    </div>
</div>';

// ================================================================
// REFERRAL SUMMARY - CARD
// ================================================================
$pdf_content .= '
<div style="background: #F8FAFC; border-radius: 8px; padding: 12px 16px; border: 1px solid #E2E8F0; margin-bottom: 14px; page-break-inside: avoid;">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px 16px;">
        <div style="display: flex; padding: 2px 0;">
            <span style="font-weight: 600; color: #64748B; width: 120px; flex-shrink: 0; font-size: 9pt;">Referral #:</span>
            <span style="font-weight: 700; color: #0B5ED7; font-size: 9.5pt;">' . htmlspecialchars($referral['referral_number'] ?? 'N/A') . '</span>
        </div>
        <div style="display: flex; padding: 2px 0;">
            <span style="font-weight: 600; color: #64748B; width: 120px; flex-shrink: 0; font-size: 9pt;">Type:</span>
            <span style="font-size: 9.5pt;">' . getReferralTypeLabel($referral['referral_type']) . '</span>
        </div>
        <div style="display: flex; padding: 2px 0;">
            <span style="font-weight: 600; color: #64748B; width: 120px; flex-shrink: 0; font-size: 9pt;">Status:</span>
            <span style="font-size: 9.5pt; padding: 2px 10px; border-radius: 12px; background: ' . (($referral['status'] ?? 'pending') === 'accepted' ? '#D1FAE5' : (($referral['status'] ?? 'pending') === 'rejected' ? '#FEE2E2' : (($referral['status'] ?? 'pending') === 'completed' ? '#E8F0FE' : '#FEF3C7'))) . '; color: ' . (($referral['status'] ?? 'pending') === 'accepted' ? '#059669' : (($referral['status'] ?? 'pending') === 'rejected' ? '#DC2626' : (($referral['status'] ?? 'pending') === 'completed' ? '#0B5ED7' : '#D97706'))) . '; font-weight: 600;">' . ucfirst($referral['status'] ?? 'Pending') . '</span>
        </div>
        <div style="display: flex; padding: 2px 0;">
            <span style="font-weight: 600; color: #64748B; width: 120px; flex-shrink: 0; font-size: 9pt;">Urgency:</span>
            <span style="font-size: 9.5pt; padding: 2px 10px; border-radius: 12px; background: ' . (($referral['urgency'] ?? 'routine') === 'emergency' ? '#FEE2E2' : (($referral['urgency'] ?? 'routine') === 'urgent' ? '#FEF3C7' : '#D1FAE5')) . '; color: ' . (($referral['urgency'] ?? 'routine') === 'emergency' ? '#DC2626' : (($referral['urgency'] ?? 'routine') === 'urgent' ? '#D97706' : '#059669')) . '; font-weight: 600;">' . getUrgencyLabel($referral['urgency'] ?? 'routine') . '</span>
        </div>
        <div style="display: flex; padding: 2px 0;">
            <span style="font-weight: 600; color: #64748B; width: 120px; flex-shrink: 0; font-size: 9pt;">Created:</span>
            <span style="font-size: 9.5pt;">' . formatDate($referral['created_at'] ?? '') . '</span>
        </div>
        <div style="display: flex; padding: 2px 0;">
            <span style="font-weight: 600; color: #64748B; width: 120px; flex-shrink: 0; font-size: 9pt;">Updated:</span>
            <span style="font-size: 9.5pt;">' . formatDate($referral['updated_at'] ?? '') . '</span>
        </div>
    </div>
</div>';

// ================================================================
// 1. PATIENT INFORMATION
// ================================================================
$pdf_content .= '
<div style="page-break-inside: avoid;">
<div style="font-size: 11pt; font-weight: 700; color: #0B5ED7; border-bottom: 2px solid #0B5ED7; padding-bottom: 4px; margin-bottom: 8px;">
    👤 PATIENT INFORMATION
</div>
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3px 16px; margin-bottom: 10px;">
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Patient Name:</span>
        <span style="font-weight: 700; font-size: 9.5pt;">' . htmlspecialchars($referral['patient_name'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Patient ID:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($referral['patient_code'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Date of Birth:</span>
        <span style="font-size: 9.5pt;">' . (!empty($referral['date_of_birth']) ? date('d/m/Y', strtotime($referral['date_of_birth'])) . ' (' . calculateAge($referral['date_of_birth']) . ' yrs)' : 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Gender:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($referral['gender'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Phone:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($referral['patient_phone'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Emergency Contact:</span>
        <span style="font-weight: 700; font-size: 9.5pt; color: #DC2626;">' . htmlspecialchars($referral['emergency_contact'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Blood Group:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($referral['blood_group'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Allergies:</span>
        <span style="font-size: 9.5pt; color: #DC2626;">' . htmlspecialchars($referral['allergies'] ?? 'None') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Address:</span>
        <span style="font-size: 9pt;">' . htmlspecialchars($referral['address'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Branch:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($referral['branch_name'] ?? $doctor_branch_name) . '</span>
    </div>
</div>
</div>';

// ================================================================
// ROW MOJA - SPACER
// ================================================================
$pdf_content .= '<div style="height: 8px;"></div>';

// ================================================================
// 2. DOCTORS INFORMATION
// ================================================================
$pdf_content .= '
<div style="page-break-inside: avoid;">
<div style="font-size: 11pt; font-weight: 700; color: #7C3AED; border-bottom: 2px solid #7C3AED; padding-bottom: 4px; margin-bottom: 8px;">
    👨‍⚕️ DOCTORS INFORMATION
</div>
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3px 16px; margin-bottom: 10px;">
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">From Doctor:</span>
        <span style="font-weight: 700; font-size: 9.5pt;">Dr. ' . htmlspecialchars($referral['from_doctor_name'] ?? 'Unknown') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">To Doctor:</span>
        <span style="font-weight: 700; font-size: 9.5pt;">Dr. ' . htmlspecialchars($referral['to_doctor_name'] ?? 'Unknown') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">From Specialty:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($referral['from_doctor_specialty'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">To Specialty:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($referral['to_doctor_specialty'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">From Phone:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($referral['from_doctor_phone'] ?? 'N/A') . '</span>
    </div>
    <div style="display: flex; padding: 3px 0; border-bottom: 1px solid #E2E8F0;">
        <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">To Phone:</span>
        <span style="font-size: 9.5pt;">' . htmlspecialchars($referral['to_doctor_phone'] ?? 'N/A') . '</span>
    </div>
</div>
</div>';

// ================================================================
// ROW MOJA - SPACER
// ================================================================
$pdf_content .= '<div style="height: 8px;"></div>';

// ================================================================
// 3. SYMPTOMS, HPI & PHYSICAL EXAMINATION
// ================================================================
$pdf_content .= '
<div style="page-break-inside: avoid;">
<div style="font-size: 11pt; font-weight: 700; color: #D97706; border-bottom: 2px solid #D97706; padding-bottom: 4px; margin-bottom: 8px;">
    📋 SYMPTOMS, HPI & PHYSICAL EXAMINATION
</div>
<table style="width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 10px; border: 1px solid #E2E8F0;">
    <thead>
        <tr>
            <th style="background: #D97706; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 33.33%;">Symptoms</th>
            <th style="background: #D97706; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 33.33%;">HPI</th>
            <th style="background: #D97706; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 33.33%;">Physical Examination</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top; white-space: pre-wrap; word-wrap: break-word; font-size: 9pt; background: #FAFAFA;">' . nl2br(htmlspecialchars($referral['symptoms'] ?? 'No symptoms recorded')) . '</td>
            <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top; white-space: pre-wrap; word-wrap: break-word; font-size: 9pt; background: #FAFAFA;">' . nl2br(htmlspecialchars($referral['hpi'] ?? 'No HPI recorded')) . '</td>
            <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top; white-space: pre-wrap; word-wrap: break-word; font-size: 9pt; background: #FAFAFA;">' . nl2br(htmlspecialchars($referral['physical_exam'] ?? 'No physical exam recorded')) . '</td>
        </tr>
    </tbody>
</table>
</div>';

// ================================================================
// ROW MOJA - SPACER
// ================================================================
$pdf_content .= '<div style="height: 8px;"></div>';

// ================================================================
// 4. LAB TESTS - Table with Test Name, Results, Lab Technician
// ================================================================
if (count($lab_tests) > 0) {
    $pdf_content .= '
    <div style="page-break-inside: avoid;">
    <div style="font-size: 11pt; font-weight: 700; color: #7C3AED; border-bottom: 2px solid #7C3AED; padding-bottom: 4px; margin-bottom: 8px;">
        🧪 LABORATORY TESTS
    </div>
    <table style="width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 10px; border: 1px solid #E2E8F0;">
        <thead>
            <tr>
                <th style="background: #7C3AED; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 40%;">Test Name</th>
                <th style="background: #7C3AED; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 40%;">Results</th>
                <th style="background: #7C3AED; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 20%;">Lab Technician</th>
            </tr>
        </thead>
        <tbody>';
    foreach ($lab_tests as $test) {
        $result_display = !empty($test['results']) ? htmlspecialchars($test['results']) : '⏳ Pending';
        $result_style = !empty($test['results']) ? 'color: #059669; font-weight: 600;' : 'color: #94A3B8;';
        $pdf_content .= '
            <tr>
                <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; font-weight: 600; font-size: 9pt; background: #FAFAFA;">' . htmlspecialchars($test['test_name'] ?? 'N/A') . '</td>
                <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; font-size: 9pt; ' . $result_style . ' background: #FAFAFA;">' . $result_display . '</td>
                <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; font-size: 9pt; background: #FAFAFA;">' . htmlspecialchars($test['lab_technician_name'] ?? 'Not assigned') . '</td>
            </tr>';
    }
    $pdf_content .= '
        </tbody>
    </table>
    </div>';
    
    // ROW MOJA - SPACER
    $pdf_content .= '<div style="height: 8px;"></div>';
}

// ================================================================
// 5. DIAGNOSIS - Table with Disease Name, Disease Code, Treatment
// ================================================================
$pdf_content .= '
<div style="page-break-inside: avoid;">
<div style="font-size: 11pt; font-weight: 700; color: #059669; border-bottom: 2px solid #059669; padding-bottom: 4px; margin-bottom: 8px;">
    🩺 DIAGNOSIS
</div>
<table style="width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 10px; border: 1px solid #E2E8F0;">
    <thead>
        <tr>
            <th style="background: #059669; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 33.33%;">Disease Name</th>
            <th style="background: #059669; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 33.33%;">Disease Code</th>
            <th style="background: #059669; color: white; padding: 5px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; width: 33.33%;">Treatment</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; font-weight: 600; font-size: 9pt; color: #0B5ED7; background: #FAFAFA;">' . htmlspecialchars($disease_name ?: ($referral['visit_diagnosis'] ?? 'No diagnosis recorded')) . '</td>
            <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; font-size: 9pt; font-family: monospace; background: #FAFAFA;">' . htmlspecialchars($disease_code ?: $referral['disease_code'] ?? 'N/A') . '</td>
            <td style="padding: 5px 8px; border-bottom: 1px solid #E2E8F0; font-size: 9pt; word-wrap: break-word; background: #FAFAFA;">' . nl2br(htmlspecialchars($referral['treatment'] ?? $referral['treatment_given'] ?? 'No treatment recorded')) . '</td>
        </tr>
    </tbody>
</table>
</div>';

// ================================================================
// ROW MOJA - SPACER
// ================================================================
$pdf_content .= '<div style="height: 8px;"></div>';

// ================================================================
// 6. REASON FOR REFERRAL - CARD
// ================================================================
$pdf_content .= '
<div style="page-break-inside: avoid;">
<div style="font-size: 11pt; font-weight: 700; color: #0B5ED7; border-bottom: 2px solid #0B5ED7; padding-bottom: 4px; margin-bottom: 8px;">
    📝 REASON FOR REFERRAL
</div>
<div style="padding: 10px 12px; background: #F8FAFC; border-radius: 6px; border: 1px solid #E2E8F0; margin-bottom: 10px;">
    <div style="font-size: 9.5pt; white-space: pre-wrap; word-wrap: break-word;">' . nl2br(htmlspecialchars($referral['reason'] ?? 'No reason provided')) . '</div>
</div>
</div>';

// ================================================================
// ROW MOJA - SPACER
// ================================================================
$pdf_content .= '<div style="height: 8px;"></div>';

// ================================================================
// 7. EXTERNAL REFERRAL DETAILS - Card
// ================================================================
if ($referral['referral_type'] === 'external') {
    $pdf_content .= '
    <div style="page-break-inside: avoid;">
    <div style="font-size: 11pt; font-weight: 700; color: #0B5ED7; border-bottom: 2px solid #0B5ED7; padding-bottom: 4px; margin-bottom: 8px;">
        🏥 EXTERNAL REFERRAL DETAILS
    </div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3px 16px; padding: 10px 12px; background: #F8FAFC; border-radius: 6px; border: 1px solid #E2E8F0; margin-bottom: 10px;">
        <div style="display: flex; padding: 2px 0;">
            <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Facility Name:</span>
            <span style="font-weight: 700; font-size: 9.5pt; color: #0B5ED7;">' . htmlspecialchars($referral['to_hospital_name'] ?? 'N/A') . '</span>
        </div>
        <div style="display: flex; padding: 2px 0;">
            <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Phone:</span>
            <span style="font-size: 9.5pt;">' . htmlspecialchars($referral['to_hospital_phone'] ?? 'N/A') . '</span>
        </div>
        <div style="display: flex; padding: 2px 0; grid-column: span 2;">
            <span style="font-weight: 600; color: #64748B; width: 130px; flex-shrink: 0; font-size: 9pt;">Address:</span>
            <span style="font-size: 9.5pt;">' . htmlspecialchars($referral['to_hospital_address'] ?? 'N/A') . '</span>
        </div>
    </div>
    </div>';
}

// ================================================================
// FOOTER WITH OFFICIAL STAMP
// ================================================================
$pdf_content .= '
<div style="border-top: 2px solid #0B5ED7; padding-top: 10px; margin-top: 12px; text-align: center; page-break-inside: avoid;">
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
    <title>Referral Letter - <?= htmlspecialchars($referral['referral_number'] ?? 'Referral') ?></title>
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
        .btn-primary { background: #0B5ED7; color: white; }
        .btn-primary:hover { background: #0A4CA8; transform: translateY(-2px); }
        .btn-success { background: #059669; color: white; }
        .btn-success:hover { background: #047857; transform: translateY(-2px); }
        .btn-danger { background: #DC2626; color: white; }
        .btn-danger:hover { background: #B91C1C; transform: translateY(-2px); }
        .pdf-content { background: white; padding: 5px; border-radius: 4px; border: 1px solid #E2E8F0; }
        .pdf-content table { width: 100%; border-collapse: collapse; }
        .pdf-content table td, .pdf-content table th { padding: 3px 6px; }
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
                filename: 'Referral_<?= htmlspecialchars($referral['referral_number'] ?? 'referral') ?>_<?= date('Ymd') ?>.pdf',
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
        
        console.log('✅ Referral PDF - <?= htmlspecialchars($referral['referral_number'] ?? 'Referral') ?>');
        console.log('✅ Patient: <?= htmlspecialchars($referral['patient_name'] ?? 'N/A') ?>');
        console.log('✅ Status: <?= ucfirst($referral['status'] ?? 'Pending') ?>');
        console.log('✅ Type: <?= ucfirst($referral['referral_type'] ?? 'N/A') ?>');
        console.log('✅ Flow: Header → Summary → Patient Information → [ROW] → Doctors Information → [ROW] → Symptoms/HPI/Physical → [ROW] → Lab Tests → [ROW] → Diagnosis → [ROW] → Reason for Referral → [ROW] → External Details → Stamp');
        console.log('✅ NO TEXT CUTOFF between pages');
        console.log('✅ Using data from referrals table');
    </script>
</body>
</html>