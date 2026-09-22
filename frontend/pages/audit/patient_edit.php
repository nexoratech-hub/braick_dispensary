<?php
// ================================================================
// FILE: frontend/pages/audit/patient_edit.php
// AUDIT - EDIT PATIENT (with Vital Signs) - V2
// ✅ Branch ya aliye login TU
// ✅ Inatumia audit_header.php + audit_sidebar.php
// ✅ AUDIT ROLE TU
// ✅ Ina vital signs zote 7 (Temperature, BP, Pulse, Weight, Height, BMI, SpO2)
// ✅ Ina allergy chips + symptom chips
// ✅ Ina doctor dropdown (online/offline)
// ✅ Blue theme + dark mode support
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ✅ AUDIT TU
if ($_SESSION['role'] !== 'audit') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: /dispensary_system/frontend/pages/admin/dashboard.php'); break;
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_is_online = $_SESSION['is_online'] ?? 1;

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ✅ AUDIT anaona branch yake TU
$selected_branch_id = (int)$user_branch_id;

if ($patient_id <= 0) {
    header('Location: patients.php?error=invalid_id');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$message = '';
$message_type = '';

// ================================================================
// ✅ GET PATIENT - LAZIMA branch ya mtumiaji
// ================================================================
$patient = null;
try {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as assigned_doctor_name, b.name as branch_name
        FROM patients p
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$patient_id, $user_branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patient = null;
}

if (!$patient) {
    // ✅ Patient hayupo kwenye branch yake - redirect
    header('Location: patients.php?error=notfound');
    exit;
}

$patient_branch_id = (int)$patient['branch_id'];

// GET LAST VISIT
$last_visit = null;
try {
    $stmt = $db->prepare("SELECT * FROM visits WHERE patient_id = ? AND branch_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$patient_id, $patient_branch_id]);
    $last_visit = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $last_visit = null; }

// GET VITAL SIGNS
$vital_signs = null;
try {
    $stmt = $db->prepare("SELECT * FROM vital_signs WHERE patient_id = ? AND branch_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$patient_id, $patient_branch_id]);
    $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $vital_signs = null; }

// GET DOCTORS
$doctors = [];
$online_doctors = [];
$offline_doctors = [];
$online_doctors_count = 0;
$offline_doctors_count = 0;

try {
    $stmt = $db->prepare("
        SELECT id, full_name, specialty, is_online 
        FROM users 
        WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
        ORDER BY is_online DESC, full_name
    ");
    $stmt->execute([$patient_branch_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($doctors as $doc) {
        if ($doc['is_online'] == 1) { $online_doctors[] = $doc; $online_doctors_count++; }
        else { $offline_doctors[] = $doc; $offline_doctors_count++; }
    }
} catch (Exception $e) {}

// ================================================================
// HANDLE UPDATE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_patient'])) {
    try {
        $db->beginTransaction();
        
        $full_name_new = trim($_POST['full_name'] ?? '');
        $gender = $_POST['gender'] ?? null;
        $date_of_birth = $_POST['date_of_birth'] ?? null;
        $marital_status = $_POST['marital_status'] ?? null;
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');
        $blood_group = $_POST['blood_group'] ?? null;
        $allergies = trim($_POST['allergies'] ?? '');
        $assigned_doctor_id = !empty($_POST['assigned_doctor_id']) ? (int)$_POST['assigned_doctor_id'] : null;
        
        if (empty($full_name_new)) throw new Exception("Patient name is required");
        if (empty($gender)) throw new Exception("Gender is required");
        
        // ✅ Update - LAZIMA branch ya mtumiaji
        $stmt = $db->prepare("
            UPDATE patients SET 
                full_name = ?, gender = ?, date_of_birth = ?, marital_status = ?,
                phone = ?, email = ?, address = ?, emergency_contact = ?,
                blood_group = ?, allergies = ?, assigned_doctor_id = ?,
                updated_at = NOW()
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([
            $full_name_new, $gender, $date_of_birth, $marital_status,
            $phone, $email, $address, $emergency_contact,
            $blood_group, $allergies, $assigned_doctor_id,
            $patient_id, $patient_branch_id
        ]);
        
        // Update last visit
        if ($last_visit && isset($last_visit['id'])) {
            $symptoms = trim($_POST['symptoms'] ?? '');
            $complaint = trim($_POST['complaint'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            $stmt = $db->prepare("UPDATE visits SET symptoms = ?, complaint = ?, notes = ?, updated_at = NOW() WHERE id = ? AND branch_id = ?");
            $stmt->execute([$symptoms, $complaint, $notes, $last_visit['id'], $patient_branch_id]);
        }
        
        // Vital signs
        $temperature = !empty($_POST['temperature']) ? (float)$_POST['temperature'] : null;
        $bp_systolic = !empty($_POST['bp_systolic']) ? (int)$_POST['bp_systolic'] : null;
        $bp_diastolic = !empty($_POST['bp_diastolic']) ? (int)$_POST['bp_diastolic'] : null;
        $pulse_rate = !empty($_POST['pulse_rate']) ? (int)$_POST['pulse_rate'] : null;
        $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
        $height = !empty($_POST['height']) ? (float)$_POST['height'] : null;
        $oxygen_saturation = !empty($_POST['oxygen_saturation']) ? (int)$_POST['oxygen_saturation'] : null;
        $vital_notes = trim($_POST['vital_notes'] ?? '');
        
        $bmi = null;
        if ($weight && $height && $height > 0) {
            $height_m = $height / 100;
            $bmi = round($weight / ($height_m * $height_m), 1);
        }
        
        if ($vital_signs && isset($vital_signs['id'])) {
            // ✅ Update - LAZIMA branch ya mtumiaji
            $stmt = $db->prepare("
                UPDATE vital_signs SET 
                    temperature = ?, blood_pressure_systolic = ?, blood_pressure_diastolic = ?,
                    pulse_rate = ?, weight = ?, height = ?, bmi = ?, 
                    oxygen_saturation = ?, notes = ?, updated_at = NOW()
                WHERE id = ? AND branch_id = ?
            ");
            $stmt->execute([
                $temperature, $bp_systolic, $bp_diastolic, $pulse_rate,
                $weight, $height, $bmi, $oxygen_saturation, $vital_notes ?: null,
                $vital_signs['id'], $patient_branch_id
            ]);
        } elseif ($last_visit && ($temperature || $bp_systolic || $pulse_rate || $weight || $height || $oxygen_saturation)) {
            $stmt = $db->prepare("
                INSERT INTO vital_signs (
                    patient_id, visit_id, recorded_by, branch_id,
                    temperature, blood_pressure_systolic, blood_pressure_diastolic,
                    pulse_rate, weight, height, bmi, oxygen_saturation, notes, recorded_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $patient_id, $last_visit['id'], $user_id, $patient_branch_id,
                $temperature, $bp_systolic, $bp_diastolic, $pulse_rate,
                $weight, $height, $bmi, $oxygen_saturation, $vital_notes ?: null
            ]);
        }
        
        try {
            $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) VALUES (?, ?, 'edit_patient', ?, ?, NOW())")
                ->execute([$user_id, $patient_branch_id, "Audit edited patient: {$patient['full_name']} (ID: {$patient['patient_id']})", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        } catch (Exception $e) {}
        
        $db->commit();
        
        $message = "✅ Patient updated successfully!";
        $message_type = 'success';
        
        // Re-fetch
        $stmt = $db->prepare("SELECT p.*, u.full_name as assigned_doctor_name, b.name as branch_name FROM patients p LEFT JOIN users u ON p.assigned_doctor_id = u.id LEFT JOIN branches b ON p.branch_id = b.id WHERE p.id = ? AND p.branch_id = ?");
        $stmt->execute([$patient_id, $user_branch_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("SELECT * FROM vital_signs WHERE patient_id = ? AND branch_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$patient_id, $patient_branch_id]);
        $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// COMMON LISTS
$common_symptoms = [
    'Fever' => 'Fever', 'Headache' => 'Headache', 'Cough' => 'Cough',
    'Sore Throat' => 'Sore Throat', 'Body Pain' => 'Body Pain', 'Fatigue' => 'Fatigue',
    'Nausea' => 'Nausea', 'Vomiting' => 'Vomiting', 'Diarrhea' => 'Diarrhea',
    'Chest Pain' => 'Chest Pain', 'Shortness of Breath' => 'Shortness of Breath',
    'Abdominal Pain' => 'Abdominal Pain', 'Dizziness' => 'Dizziness', 'Rash' => 'Rash', 'Swelling' => 'Swelling'
];

$common_allergies = [
    'Penicillin' => 'Penicillin', 'Sulfa Drugs' => 'Sulfa Drugs', 'Aspirin' => 'Aspirin',
    'Ibuprofen' => 'Ibuprofen', 'Codeine' => 'Codeine', 'Latex' => 'Latex',
    'Peanuts' => 'Peanuts', 'Shellfish' => 'Shellfish', 'Eggs' => 'Eggs',
    'Milk' => 'Milk', 'Wheat' => 'Wheat', 'Soy' => 'Soy',
    'Dust' => 'Dust', 'Pollen' => 'Pollen', 'Animal Dander' => 'Animal Dander'
];

$marital_statuses = ['Single' => 'Single', 'Married' => 'Married', 'Divorced' => 'Divorced', 'Widowed' => 'Widowed', 'Separated' => 'Separated'];

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ✅ AUDIT HEADER + SIDEBAR
include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>

<style>
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #6EA8FE;
    --primary-bg: #E8F0FE;
    --success: #059669;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --purple: #7C3AED;
    --purple-bg: #EDE9FE;
    --cyan: #0891B2;
    --cyan-bg: #CFFAFE;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    --radius: 12px;
    --radius-lg: 18px;
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary-bg: #1E3A5F;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3D2E0A;
    --purple-bg: #2A1A3A;
    --cyan-bg: #0A2E3A;
}

/* PAGE HEADER */
.page-header-audit {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-radius: var(--radius-lg);
    padding: 18px 26px;
    margin-bottom: 18px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
    position: relative;
    overflow: hidden;
}
.page-header-audit::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 350px; height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.page-header-audit .page-title {
    color: white;
    font-size: 1.3rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin: 0;
}
.page-header-audit .page-title i { font-size: 1.4rem; color: #93C5FD; }
.page-header-audit .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.72rem;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 5px;
}
.page-header-audit .header-badge {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 500;
    backdrop-filter: blur(4px);
    display: inline-flex;
    align-items: center;
    gap: 3px;
    border: 1px solid rgba(255,255,255,0.1);
}
.page-header-audit .audit-tag {
    background: linear-gradient(135deg, #0EA5E9, #0284C7);
    color: white;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.page-header-audit .btn-outline-light {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 6px 14px;
    border-radius: var(--radius);
    font-weight: 500;
    font-size: 0.7rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    position: relative;
    z-index: 1;
}
.page-header-audit .btn-outline-light:hover {
    background: rgba(255,255,255,0.25);
    color: white;
    transform: translateY(-2px);
}

/* FORM CARD */
.form-card-modern {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 24px 28px;
    border: 1px solid var(--border-color);
    max-width: 1200px;
    margin: 0 auto;
    box-shadow: var(--shadow-md);
}

.form-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 2px solid var(--border-color);
}
.form-header .form-icon {
    width: 42px; height: 42px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
}
.form-header .form-title { font-size: 1rem; font-weight: 700; margin: 0; }
.form-header .form-subtitle { font-size: 0.7rem; color: var(--text-secondary); margin: 2px 0 0 0; }

.patient-avatar-display {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 16px;
    background: linear-gradient(135deg, var(--primary-bg), #DBEAFE);
    border-radius: var(--radius);
    border: 2px solid var(--primary-light);
    margin-bottom: 18px;
}
[data-theme="dark"] .patient-avatar-display {
    background: linear-gradient(135deg, #1E3A5F, #1E40AF);
}
.patient-avatar-circle {
    width: 52px; height: 52px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1.3rem;
    flex-shrink: 0;
    box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
    text-transform: uppercase;
}
.patient-avatar-info h3 { font-size: 0.92rem; font-weight: 800; color: var(--text-primary); margin: 0 0 3px 0; }
.patient-avatar-info p {
    font-size: 0.68rem;
    color: var(--text-secondary);
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
}

.form-label {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
    display: block;
}
.form-label .required { color: var(--danger); margin-left: 2px; }
.form-label .label-icon { margin-right: 4px; color: var(--primary); }

.form-control {
    width: 100%;
    padding: 8px 11px;
    border: 2px solid var(--border-color);
    border-radius: var(--radius);
    font-size: 0.78rem;
    outline: none;
    background: var(--bg-card);
    color: var(--text-primary);
    font-family: inherit;
    transition: all 0.2s ease;
}
.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.08);
}
.form-row { margin-bottom: 14px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.grid-full { grid-column: 1 / -1; }

.form-actions {
    display: flex;
    gap: 8px;
    padding-top: 18px;
    margin-top: 18px;
    border-top: 2px solid var(--border-color);
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 18px;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.78rem;
    cursor: pointer;
    border: none;
    text-decoration: none;
    min-height: 38px;
    transition: all 0.2s ease;
}
.btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); color: white; }
.btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
.btn-outline:hover { border-color: var(--primary); color: var(--primary); }
.btn-purple { background: linear-gradient(135deg, #7C3AED, #5B21B6); color: white; }
.btn-purple:hover { color: white; transform: translateY(-2px); }

/* VITAL SIGNS */
.vital-signs-section {
    margin-top: 18px;
    padding-top: 14px;
    border-top: 2px solid var(--border-color);
}
.vital-signs-section .section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
}
.vital-signs-section .section-title {
    font-size: 0.85rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}
.vital-signs-section .section-title i { color: #DC2626; }
.vital-signs-section .section-badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 12px;
    background: var(--warning-bg);
    color: var(--warning);
}

.vital-grid-6 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 10px;
}
.vital-grid-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 10px;
}

.vital-card {
    background: var(--bg-body);
    border-radius: var(--radius);
    padding: 12px 14px;
    border: 2px solid var(--border-color);
    position: relative;
    overflow: hidden;
    min-height: 100px;
    display: flex;
    flex-direction: column;
}
.vital-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
}
.vital-card .vital-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}
.vital-card .vital-icon {
    width: 30px; height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
}
.vital-card .vital-label {
    font-size: 0.6rem;
    font-weight: 700;
    display: block;
    text-transform: uppercase;
    color: var(--text-primary);
}
.vital-card .vital-sublabel {
    font-size: 0.5rem;
    color: var(--text-secondary);
}
.vital-card .vital-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: auto;
}
.vital-card .vital-input-wrap input {
    width: 100%;
    padding: 7px 10px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 0.88rem;
    font-weight: 700;
    background: var(--bg-card);
    color: var(--text-primary);
    outline: none;
    font-family: inherit;
}
.vital-card .vital-input-wrap input:focus { border-color: var(--primary); }
.vital-card .vital-unit {
    font-size: 0.55rem;
    color: var(--text-secondary);
    font-weight: 600;
    padding: 2px 5px;
    background: var(--border-color);
    border-radius: 5px;
    white-space: nowrap;
}

.vital-card.temperature::before { background: linear-gradient(135deg, #DC2626, #B91C1C); }
.vital-card.temperature .vital-icon { color: #DC2626; background: rgba(220, 38, 38, 0.1); }
.vital-card.bp::before { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
.vital-card.bp .vital-icon { color: #0B5ED7; background: rgba(11, 94, 215, 0.1); }
.vital-card.pulse::before { background: linear-gradient(135deg, #059669, #047857); }
.vital-card.pulse .vital-icon { color: #059669; background: rgba(5, 150, 105, 0.1); }
.vital-card.weight::before { background: linear-gradient(135deg, #D97706, #B45309); }
.vital-card.weight .vital-icon { color: #D97706; background: rgba(217, 119, 6, 0.1); }
.vital-card.height::before { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
.vital-card.height .vital-icon { color: #7C3AED; background: rgba(124, 58, 237, 0.1); }
.vital-card.bmi::before { background: linear-gradient(135deg, #0D9488, #0F766E); }
.vital-card.bmi .vital-icon { color: #0D9488; background: rgba(13, 148, 136, 0.1); }
.vital-card.bmi input { background: var(--primary-bg); color: var(--primary); font-weight: 800; }
.vital-card.spo2::before { background: linear-gradient(135deg, #0891B2, #0E7490); }
.vital-card.spo2 .vital-icon { color: #0891B2; background: rgba(8, 145, 178, 0.1); }
.vital-card.spo2 input { background: rgba(8, 145, 178, 0.05); color: #0891B2; font-weight: 700; }

.vital-bmi-category {
    font-size: 0.5rem;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 6px;
    display: inline-block;
    margin-top: 3px;
    background: var(--border-color);
    color: var(--text-secondary);
}
.vital-bmi-category.normal { background: rgba(5, 150, 105, 0.15); color: #059669; }
.vital-bmi-category.underweight { background: rgba(217, 119, 6, 0.15); color: #D97706; }
.vital-bmi-category.overweight { background: rgba(217, 119, 6, 0.15); color: #D97706; }
.vital-bmi-category.obese { background: rgba(220, 38, 38, 0.15); color: #DC2626; }
.vital-bmi-category.spo2-normal { background: rgba(5, 150, 105, 0.15); color: #059669; }
.vital-bmi-category.spo2-low { background: rgba(217, 119, 6, 0.15); color: #D97706; }
.vital-bmi-category.spo2-critical { background: rgba(220, 38, 38, 0.3); color: #DC2626; font-weight: 700; }

.message-box {
    padding: 12px 18px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 0.82rem;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}
.message-box.success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.message-box.error { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }

@media (max-width: 1024px) {
    .vital-grid-4 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .form-card-modern { padding: 14px; }
    .grid-2 { grid-template-columns: 1fr; }
    .vital-grid-6, .vital-grid-4 { grid-template-columns: repeat(2, 1fr); gap: 6px; }
    .vital-card { padding: 9px; min-height: 88px; }
    .page-header-audit { flex-direction: column; align-items: flex-start; padding: 14px 18px; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-audit">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-edit"></i>
                Edit Patient
                <span class="header-badge audit-tag">
                    <i class="fas fa-shield-alt"></i> AUDIT
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-id-card"></i>
                <strong><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-calendar"></i> <?= date('d M Y') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_patient.php?id=<?= $patient_id ?>" class="btn-outline-light">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="patients.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.1rem;margin-top:1px;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <div class="form-card-modern">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-user-edit"></i>
            </div>
            <div>
                <h3 class="form-title">Edit Patient Information</h3>
                <p class="form-subtitle">
                    Update the patient details below
                    <span style="color:var(--cyan);font-weight:600;margin-left:6px;">
                        <i class="fas fa-shield-alt"></i> Editing as: <?= htmlspecialchars($user_full_name) ?> (Audit)
                    </span>
                </p>
            </div>
        </div>
        
        <div class="patient-avatar-display">
            <div class="patient-avatar-circle">
                <?= strtoupper(substr($patient['full_name'] ?? 'P', 0, 1)) ?>
            </div>
            <div class="patient-avatar-info">
                <h3><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></h3>
                <p>
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                    <?php if (!empty($patient['created_at'])): ?>
                        <span style="color:var(--text-secondary);">•</span>
                        <i class="fas fa-calendar-plus"></i> Registered: <?= date('d M Y', strtotime($patient['created_at'])) ?>
                    <?php endif; ?>
                    <?php if (!empty($patient['branch_name'])): ?>
                        <span style="color:var(--text-secondary);">•</span>
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <form method="POST" action="" id="editPatientForm" autocomplete="off">
            
            <div class="form-row grid-full">
                <label class="form-label">
                    <i class="fas fa-user label-icon"></i> Full Name <span class="required">*</span>
                </label>
                <input type="text" name="full_name" class="form-control" 
                       value="<?= htmlspecialchars($patient['full_name'] ?? '') ?>" 
                       placeholder="Enter patient full name" required>
            </div>
            
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-venus-mars label-icon"></i> Gender <span class="required">*</span>
                    </label>
                    <select name="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?= ($patient['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>👨 Male</option>
                        <option value="Female" <?= ($patient['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>👩 Female</option>
                        <option value="Other" <?= ($patient['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>⚧ Other</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <label class="form-label"><i class="fas fa-calendar label-icon"></i> Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" 
                           value="<?= htmlspecialchars($patient['date_of_birth'] ?? '') ?>">
                </div>
            </div>
            
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label"><i class="fas fa-ring label-icon"></i> Marital Status</label>
                    <select name="marital_status" class="form-control">
                        <option value="">Select Marital Status</option>
                        <?php foreach ($marital_statuses as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($patient['marital_status'] ?? '') === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <label class="form-label"><i class="fas fa-tint label-icon"></i> Blood Group</label>
                    <select name="blood_group" class="form-control">
                        <option value="">Select Blood Group</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                            <option value="<?= $bg ?>" <?= ($patient['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-phone label-icon"></i> Phone Number
                    </label>
                    <input type="tel" name="phone" class="form-control" 
                           placeholder="e.g. 0759 154 160" 
                           value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-envelope label-icon"></i> Email
                    </label>
                    <input type="email" name="email" class="form-control" 
                           placeholder="e.g. john@example.com" 
                           value="<?= htmlspecialchars($patient['email'] ?? '') ?>">
                </div>
            </div>
            
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label"><i class="fas fa-phone-alt label-icon"></i> Emergency Contact</label>
                    <input type="tel" name="emergency_contact" class="form-control" 
                           placeholder="e.g. 0755 123 456" 
                           value="<?= htmlspecialchars($patient['emergency_contact'] ?? '') ?>">
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-user-md label-icon"></i> Assigned Doctor
                        <span style="font-size:0.58rem;color:var(--text-secondary);font-weight:400;">
                            (<?= $online_doctors_count ?> online, <?= $offline_doctors_count ?> offline)
                        </span>
                    </label>
                    <select name="assigned_doctor_id" class="form-control" id="doctorSelect">
                        <option value="">-- No Doctor Assigned --</option>
                        <?php if (!empty($online_doctors)): ?>
                            <optgroup label="🟢 Online Doctors (<?= $online_doctors_count ?>)">
                                <?php foreach ($online_doctors as $doctor): ?>
                                    <option value="<?= $doctor['id'] ?>" data-online="1"
                                            <?= ($patient['assigned_doctor_id'] ?? '') == $doctor['id'] ? 'selected' : '' ?>>
                                        🟢 Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                        <?php if (!empty($doctor['specialty'])): ?>(<?= htmlspecialchars($doctor['specialty']) ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($offline_doctors)): ?>
                            <optgroup label="⚪ Offline Doctors (<?= $offline_doctors_count ?>)">
                                <?php foreach ($offline_doctors as $doctor): ?>
                                    <option value="<?= $doctor['id'] ?>" data-online="0"
                                            <?= ($patient['assigned_doctor_id'] ?? '') == $doctor['id'] ? 'selected' : '' ?>>
                                        ⚪ Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                        <?php if (!empty($doctor['specialty'])): ?>(<?= htmlspecialchars($doctor['specialty']) ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row grid-full">
                <label class="form-label"><i class="fas fa-home label-icon"></i> Address</label>
                <textarea name="address" class="form-control" placeholder="Enter full address..." rows="2"><?= htmlspecialchars($patient['address'] ?? '') ?></textarea>
            </div>
            
            <div class="form-row grid-full">
                <label class="form-label">
                    <i class="fas fa-allergies label-icon"></i> Allergies
                    <span style="font-size:0.58rem;color:var(--text-secondary);font-weight:400;">(Click to select)</span>
                </label>
                <div id="allergyCheckboxGroup" style="display:flex;flex-wrap:wrap;gap:5px;margin-top:6px;">
                    <?php 
                    $patient_allergies = array_map('trim', explode(',', $patient['allergies'] ?? ''));
                    foreach ($common_allergies as $key => $label): 
                        $is_checked = in_array($label, $patient_allergies);
                    ?>
                        <label class="allergy-chip" data-allergy="<?= htmlspecialchars($label) ?>" 
                               style="display:inline-flex;align-items:center;gap:3px;padding:2px 10px 2px 7px;border-radius:20px;border:2px solid <?= $is_checked ? 'var(--danger)' : 'var(--border-color)' ?>;background:<?= $is_checked ? 'var(--danger-bg)' : 'var(--bg-body)' ?>;cursor:pointer;font-size:0.65rem;color:<?= $is_checked ? 'var(--danger)' : 'var(--text-secondary)' ?>;user-select:none;">
                            <input type="checkbox" value="<?= htmlspecialchars($label) ?>" class="allergy-checkbox" style="display:none;" <?= $is_checked ? 'checked' : '' ?>>
                            <span>⚠️</span> <?= htmlspecialchars($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <textarea name="allergies" id="allergiesTextarea" class="form-control" style="margin-top:8px;" placeholder="List any known allergies..." rows="2"><?= htmlspecialchars($patient['allergies'] ?? '') ?></textarea>
            </div>
            
            <?php if ($last_visit): ?>
            <div style="margin-top:18px;padding-top:14px;border-top:2px solid var(--border-color);">
                <div style="font-size:0.85rem;font-weight:700;display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                    <i class="fas fa-notes-medical" style="color:#7C3AED;"></i>
                    Visit Information (Last: <?= htmlspecialchars($last_visit['visit_number'] ?? 'N/A') ?>)
                </div>
                
                <div class="grid-2">
                    <div class="form-row">
                        <label class="form-label"><i class="fas fa-notes-medical label-icon"></i> Symptoms</label>
                        <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px;" id="symptomSelector">
                            <?php 
                            $visit_symptoms = array_map('trim', explode(',', $last_visit['symptoms'] ?? ''));
                            foreach ($common_symptoms as $symptom): 
                                $is_sel = in_array($symptom, $visit_symptoms);
                            ?>
                                <span class="symptom-chip" data-symptom="<?= htmlspecialchars($symptom) ?>" 
                                      onclick="toggleSymptom(this)"
                                      style="display:inline-flex;align-items:center;gap:3px;padding:2px 9px 2px 6px;border-radius:16px;border:2px solid <?= $is_sel ? 'var(--primary)' : 'var(--border-color)' ?>;background:<?= $is_sel ? 'var(--primary-bg)' : 'var(--bg-body)' ?>;cursor:pointer;font-size:0.6rem;color:<?= $is_sel ? 'var(--primary)' : 'var(--text-secondary)' ?>;user-select:none;">
                                    <span>🩺</span> <?= htmlspecialchars($symptom) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <textarea name="symptoms" class="form-control" placeholder="Patient symptoms..." rows="2" id="symptomsTextarea" style="font-size:0.7rem;margin-top:8px;"><?= htmlspecialchars($last_visit['symptoms'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label"><i class="fas fa-comment-medical label-icon"></i> Complaint / Reason</label>
                        <textarea name="complaint" class="form-control" placeholder="Main complaint..." rows="2" style="font-size:0.7rem;"><?= htmlspecialchars($last_visit['complaint'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="form-row" style="margin-top:10px;">
                    <label class="form-label"><i class="fas fa-sticky-note label-icon"></i> Visit Notes</label>
                    <textarea name="notes" class="form-control" placeholder="Any additional notes..." rows="2" style="font-size:0.7rem;"><?= htmlspecialchars($last_visit['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- VITAL SIGNS -->
            <div class="vital-signs-section">
                <div class="section-header">
                    <div class="section-title">
                        <i class="fas fa-heartbeat"></i>
                        Vital Signs
                        <span style="font-size:0.68rem;font-weight:400;color:var(--text-secondary);">(Update patient vitals)</span>
                    </div>
                    <span class="section-badge">
                        <i class="fas fa-info-circle"></i> 7 Vital Signs
                    </span>
                </div>
                
                <div class="vital-grid-6">
                    <div class="vital-card temperature">
                        <div class="vital-header">
                            <span class="vital-icon">🌡️</span>
                            <div>
                                <span class="vital-label">Temperature</span>
                                <span class="vital-sublabel">Body temp</span>
                            </div>
                        </div>
                        <div class="vital-input-wrap">
                            <input type="number" name="temperature" step="0.1" min="30" max="45" placeholder="36.5" 
                                   value="<?= htmlspecialchars($vital_signs['temperature'] ?? '') ?>">
                            <span class="vital-unit">°C</span>
                        </div>
                    </div>
                    
                    <div class="vital-card bp">
                        <div class="vital-header">
                            <span class="vital-icon">💓</span>
                            <div>
                                <span class="vital-label">Blood Pressure</span>
                                <span class="vital-sublabel">Sys / Dia</span>
                            </div>
                        </div>
                        <div class="vital-input-wrap">
                            <input type="number" name="bp_systolic" placeholder="120" 
                                   value="<?= htmlspecialchars($vital_signs['blood_pressure_systolic'] ?? '') ?>" style="text-align:center;">
                            <span style="font-weight:700;">/</span>
                            <input type="number" name="bp_diastolic" placeholder="80" 
                                   value="<?= htmlspecialchars($vital_signs['blood_pressure_diastolic'] ?? '') ?>" style="text-align:center;">
                            <span class="vital-unit">mmHg</span>
                        </div>
                    </div>
                    
                    <div class="vital-card pulse">
                        <div class="vital-header">
                            <span class="vital-icon">❤️</span>
                            <div>
                                <span class="vital-label">Pulse Rate</span>
                                <span class="vital-sublabel">Heart beats</span>
                            </div>
                        </div>
                        <div class="vital-input-wrap">
                            <input type="number" name="pulse_rate" placeholder="72" 
                                   value="<?= htmlspecialchars($vital_signs['pulse_rate'] ?? '') ?>">
                            <span class="vital-unit">bpm</span>
                        </div>
                    </div>
                </div>
                
                <div class="vital-grid-4">
                    <div class="vital-card weight">
                        <div class="vital-header">
                            <span class="vital-icon">⚖️</span>
                            <div>
                                <span class="vital-label">Weight</span>
                                <span class="vital-sublabel">Body mass</span>
                            </div>
                        </div>
                        <div class="vital-input-wrap">
                            <input type="number" name="weight" step="0.1" placeholder="65" id="weightInput" 
                                   oninput="calculateBMI()" 
                                   value="<?= htmlspecialchars($vital_signs['weight'] ?? '') ?>">
                            <span class="vital-unit">kg</span>
                        </div>
                    </div>
                    
                    <div class="vital-card height">
                        <div class="vital-header">
                            <span class="vital-icon">📏</span>
                            <div>
                                <span class="vital-label">Height</span>
                                <span class="vital-sublabel">Body length</span>
                            </div>
                        </div>
                        <div class="vital-input-wrap">
                            <input type="number" name="height" step="0.1" placeholder="170" id="heightInput" 
                                   oninput="calculateBMI()" 
                                   value="<?= htmlspecialchars($vital_signs['height'] ?? '') ?>">
                            <span class="vital-unit">cm</span>
                        </div>
                    </div>
                    
                    <div class="vital-card bmi">
                        <div class="vital-header">
                            <span class="vital-icon">📊</span>
                            <div>
                                <span class="vital-label">BMI</span>
                                <span class="vital-sublabel">Auto-calc</span>
                            </div>
                        </div>
                        <div class="vital-input-wrap">
                            <input type="number" name="bmi" id="bmiOutput" readonly step="0.1" placeholder="22.5" 
                                   value="<?= htmlspecialchars($vital_signs['bmi'] ?? '') ?>">
                            <span class="vital-unit">kg/m²</span>
                        </div>
                        <span class="vital-bmi-category" id="bmiCategory">Auto</span>
                    </div>
                    
                    <div class="vital-card spo2">
                        <div class="vital-header">
                            <span class="vital-icon">🫁</span>
                            <div>
                                <span class="vital-label">Oxygen Sat.</span>
                                <span class="vital-sublabel">SpO₂ level</span>
                            </div>
                        </div>
                        <div class="vital-input-wrap">
                            <input type="number" name="oxygen_saturation" step="1" min="0" placeholder="98" id="spo2Input" 
                                   value="<?= htmlspecialchars($vital_signs['oxygen_saturation'] ?? '') ?>">
                            <span class="vital-unit">%</span>
                        </div>
                        <span class="vital-bmi-category" id="spo2Category">Auto</span>
                    </div>
                </div>
                
                <div style="margin-top:10px;">
                    <input type="text" name="vital_notes" class="form-control" placeholder="Vital signs notes (optional)" 
                           value="<?= htmlspecialchars($vital_signs['notes'] ?? '') ?>" 
                           style="font-size:0.7rem;padding:6px 10px;">
                </div>
            </div>
            
            <input type="hidden" name="branch_id" value="<?= $patient_branch_id ?>">
            <input type="hidden" name="update_patient" value="1">
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="saveBtn">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="view_patient.php?id=<?= $patient_id ?>" class="btn btn-purple">
                    <i class="fas fa-eye"></i> View Patient
                </a>
                <a href="patients.php" class="btn btn-outline" style="margin-left:auto;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
            
            <div style="margin-top:14px;padding-top:10px;font-size:0.6rem;color:var(--text-secondary);text-align:center;border-top:1px solid var(--border-color);">
                <i class="fas fa-info-circle"></i>
                Patient ID: <strong><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></strong>
                <span style="margin:0 6px;">|</span>
                <span style="color:var(--cyan);"><i class="fas fa-shield-alt"></i> Editing as: <strong><?= htmlspecialchars($user_full_name) ?></strong> (Audit)</span>
                <span style="margin:0 6px;">|</span>
                <span style="color:var(--primary);"><i class="fas fa-heartbeat"></i> 7 Vital signs (with SpO₂)</span>
            </div>
        </form>
    </div>

</main>

<script>
// ================================================================
// BMI CALCULATION
// ================================================================
function calculateBMI() {
    var weight = parseFloat(document.getElementById('weightInput')?.value);
    var height = parseFloat(document.getElementById('heightInput')?.value);
    var output = document.getElementById('bmiOutput');
    var category = document.getElementById('bmiCategory');
    
    if (weight && height && height > 0) {
        var bmi = Math.round((weight / ((height/100) * (height/100))) * 10) / 10;
        if (output) output.value = bmi;
        
        var cat = '', cls = '';
        if (bmi < 18.5) { cat = 'Underweight'; cls = 'underweight'; }
        else if (bmi < 25) { cat = 'Normal'; cls = 'normal'; }
        else if (bmi < 30) { cat = 'Overweight'; cls = 'overweight'; }
        else { cat = 'Obese'; cls = 'obese'; }
        
        if (category) {
            category.textContent = cat;
            category.className = 'vital-bmi-category ' + cls;
        }
    } else if (output) {
        output.value = '';
        if (category) { category.textContent = 'Auto'; category.className = 'vital-bmi-category'; }
    }
}

// ================================================================
// SpO2 CATEGORY
// ================================================================
function calculateSpO2Category() {
    var input = document.getElementById('spo2Input');
    var category = document.getElementById('spo2Category');
    if (!input || !category) return;
    
    var spo2 = parseFloat(input.value);
    if (!spo2 || spo2 <= 0) {
        category.textContent = 'Auto';
        category.className = 'vital-bmi-category';
        return;
    }
    
    var cat = '', cls = '';
    if (spo2 >= 95) { cat = 'Normal'; cls = 'spo2-normal'; }
    else if (spo2 >= 90) { cat = 'Low'; cls = 'spo2-low'; }
    else { cat = 'Critical'; cls = 'spo2-critical'; }
    
    category.textContent = cat;
    category.className = 'vital-bmi-category ' + cls;
}

// ================================================================
// ALLERGY CHIPS
// ================================================================
var allergyChips = document.querySelectorAll('.allergy-chip');
var allergiesTextarea = document.getElementById('allergiesTextarea');

function syncAllergyChips() {
    if (!allergiesTextarea) return;
    var list = allergiesTextarea.value.split(',').map(s => s.trim()).filter(s => s);
    
    allergyChips.forEach(function(chip) {
        var name = chip.dataset.allergy;
        var cb = chip.querySelector('.allergy-checkbox');
        if (list.includes(name)) {
            chip.style.borderColor = 'var(--danger)';
            chip.style.background = 'var(--danger-bg)';
            chip.style.color = 'var(--danger)';
            if (cb) cb.checked = true;
        } else {
            chip.style.borderColor = 'var(--border-color)';
            chip.style.background = 'var(--bg-body)';
            chip.style.color = 'var(--text-secondary)';
            if (cb) cb.checked = false;
        }
    });
}

allergyChips.forEach(function(chip) {
    chip.addEventListener('click', function(e) {
        e.preventDefault();
        var cb = this.querySelector('.allergy-checkbox');
        var name = this.dataset.allergy;
        if (cb) cb.checked = !cb.checked;
        
        var list = allergiesTextarea.value.split(',').map(s => s.trim()).filter(s => s);
        if (cb && cb.checked) {
            if (!list.includes(name)) list.push(name);
        } else {
            list = list.filter(i => i !== name);
        }
        allergiesTextarea.value = list.join(', ');
        syncAllergyChips();
    });
});

allergiesTextarea?.addEventListener('input', syncAllergyChips);
syncAllergyChips();

// ================================================================
// SYMPTOM CHIPS
// ================================================================
function toggleSymptom(el) {
    var symptom = el.dataset.symptom;
    var textarea = document.getElementById('symptomsTextarea');
    if (!textarea) return;
    var list = textarea.value.split(',').map(s => s.trim()).filter(s => s);
    
    if (list.includes(symptom)) {
        list = list.filter(i => i !== symptom);
        el.style.borderColor = 'var(--border-color)';
        el.style.background = 'var(--bg-body)';
        el.style.color = 'var(--text-secondary)';
    } else {
        list.push(symptom);
        el.style.borderColor = 'var(--primary)';
        el.style.background = 'var(--primary-bg)';
        el.style.color = 'var(--primary)';
    }
    textarea.value = list.join(', ');
}

// ================================================================
// FORM VALIDATION
// ================================================================
document.getElementById('editPatientForm')?.addEventListener('submit', function(e) {
    var name = document.querySelector('input[name="full_name"]').value.trim();
    var gender = document.querySelector('select[name="gender"]').value;
    
    if (!name) { 
        e.preventDefault(); 
        alert('Please enter patient full name'); 
        return false; 
    }
    if (!gender) { 
        e.preventDefault(); 
        alert('Please select gender'); 
        return false; 
    }
    
    var btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
});

// ================================================================
// INIT
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    calculateBMI();
    calculateSpO2Category();
    
    document.getElementById('spo2Input')?.addEventListener('input', calculateSpO2Category);
    
    console.log('%c🔍 Audit - Edit Patient', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Branch ya mtumiaji TU', 'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#10B981; font-weight:bold;');
    console.log('%c✅ Uses audit_header + audit_sidebar', 'font-size:13px; color:#059669; font-weight:bold;');
    console.log('%c✅ 7 Vital Signs (with SpO2)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ AUDIT ROLE', 'font-size:13px; color:#0EA5E9; font-weight:bold;');
});
</script>

</body>
</html>