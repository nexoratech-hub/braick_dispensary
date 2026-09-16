<?php
// ================================================================
// FILE: frontend/pages/admin/edit_sick_sheet.php
// SUPER ADMIN - EDIT SICK SHEET
// ✅ 7 VITAL SIGNS (with Oxygen Saturation - SpO2)
// ✅ Supports external + internal sick sheets
// ✅ BLUE theme
// ✅ FIXED: Oxygen saturation inakubali thamani yoyote (0 - 100+)
// ✅ FIXED: BMI inasave kwenye database (external_sick_sheets + patient_documents)
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$sick_sheet_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sick_sheet_type = isset($_GET['type']) ? trim($_GET['type']) : 'external';
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';

if ($sick_sheet_id <= 0) {
    header('Location: sick_sheets.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

if (!in_array($sick_sheet_type, ['external', 'internal'])) {
    $sick_sheet_type = 'external';
}

$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FETCH SICK SHEET DATA
// ================================================================
$sick_sheet = null;
$vital_signs = null;

if ($sick_sheet_type === 'external') {
    // ✅ External sick sheet
    $stmt = $db->prepare("
        SELECT ess.*, 
               u.full_name as doctor_name,
               b.name as branch_name
        FROM external_sick_sheets ess
        LEFT JOIN users u ON ess.doctor_id = u.id
        LEFT JOIN branches b ON ess.branch_id = b.id
        WHERE ess.id = ?
    ");
    $stmt->execute([$sick_sheet_id]);
    $sick_sheet = $stmt->fetch(PDO::FETCH_ASSOC);
    
} else {
    // ✅ Internal sick sheet (from patient_documents)
    $stmt = $db->prepare("
        SELECT pd.*,
               p.full_name as patient_full_name,
               p.patient_id as patient_number,
               p.gender,
               p.phone,
               p.date_of_birth,
               p.address,
               p.blood_group,
               p.allergies,
               u.full_name as doctor_name,
               b.name as branch_name
        FROM patient_documents pd
        LEFT JOIN patients p ON pd.patient_id = p.id
        LEFT JOIN users u ON pd.doctor_id = u.id
        LEFT JOIN branches b ON pd.branch_id = b.id
        WHERE pd.id = ? AND pd.document_type = 'sick_sheet'
    ");
    $stmt->execute([$sick_sheet_id]);
    $sick_sheet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sick_sheet) {
        // Map patient data to sick_sheet fields
        $sick_sheet['full_name'] = $sick_sheet['patient_full_name'] ?? '';
        $sick_sheet['patient_id'] = $sick_sheet['patient_number'] ?? '';
        $sick_sheet['diagnosis'] = $sick_sheet['sick_sheet_diagnosis'] ?? '';
        $sick_sheet['sick_days'] = $sick_sheet['sick_sheet_days'] ?? 0;
        $sick_sheet['sick_from'] = $sick_sheet['sick_sheet_from_date'] ?? '';
        $sick_sheet['sick_to'] = $sick_sheet['sick_sheet_to_date'] ?? '';
        $sick_sheet['instructions'] = $sick_sheet['sick_sheet_recommendations'] ?? '';
        $sick_sheet['sick_restrictions'] = $sick_sheet['sick_sheet_restrictions'] ?? '';
    }
}

if (!$sick_sheet) {
    header('Location: sick_sheets.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
    exit;
}

// ================================================================
// ✅ FETCH VITAL SIGNS (7 signs from vital_signs table)
// ================================================================
try {
    if ($sick_sheet_type === 'external') {
        $vital_signs = null;
    } else {
        if (!empty($sick_sheet['visit_id'])) {
            $stmt = $db->prepare("
                SELECT * FROM vital_signs 
                WHERE visit_id = ? 
                ORDER BY recorded_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$sick_sheet['visit_id']]);
            $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$vital_signs && !empty($sick_sheet['patient_id'])) {
            $stmt = $db->prepare("
                SELECT * FROM vital_signs 
                WHERE patient_id = ? 
                ORDER BY recorded_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$sick_sheet['patient_id']]);
            $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    error_log("Vital signs fetch error: " . $e->getMessage());
    $vital_signs = null;
}

// ================================================================
// GET PATIENTS LIST
// ================================================================
$patients_list = [];
try {
    $sql = "SELECT id, full_name, patient_id, phone, gender, date_of_birth, address, blood_group, allergies FROM patients";
    $params = [];
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $sql .= " WHERE branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    $sql .= " ORDER BY full_name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patients_list = [];
}

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Patient info
    $full_name = trim($_POST['full_name'] ?? '');
    $patient_number = trim($_POST['patient_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $blood_group = trim($_POST['blood_group'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');
    
    // Clinical info
    $symptoms = trim($_POST['symptoms'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    
    // ✅ 7 VITAL SIGNS (oxygen inakubali thamani yoyote)
    $temperature = $_POST['temperature'] !== '' ? (float)$_POST['temperature'] : null;
    $bp_systolic = $_POST['bp_systolic'] !== '' ? (int)$_POST['bp_systolic'] : null;
    $bp_diastolic = $_POST['bp_diastolic'] !== '' ? (int)$_POST['bp_diastolic'] : null;
    $pulse_rate = $_POST['pulse_rate'] !== '' ? (int)$_POST['pulse_rate'] : null;
    $oxygen_saturation = $_POST['oxygen_saturation'] !== '' ? (int)$_POST['oxygen_saturation'] : null;
    $weight = $_POST['weight'] !== '' ? (float)$_POST['weight'] : null;
    $height = $_POST['height'] !== '' ? (float)$_POST['height'] : null;
    
    // ✅ FIXED: Auto-calculate BMI (server-side) - LAZIMA ihifadhiwe
    $bmi = null;
    if ($weight && $height && $height > 0) {
        $height_m = $height / 100;
        $bmi = round($weight / ($height_m * $height_m), 1);
    }
    
    // Lab & meds
    $lab_results = trim($_POST['lab_results'] ?? '');
    $medications = trim($_POST['medications'] ?? '');
    $procedures = trim($_POST['procedures'] ?? '');
    
    // Sick leave
    $sick_days = (int)($_POST['sick_days'] ?? 0);
    $sick_from = trim($_POST['sick_from'] ?? '');
    $sick_to = trim($_POST['sick_to'] ?? '');
    $sick_reason = trim($_POST['sick_reason'] ?? '');
    $sick_restrictions = trim($_POST['sick_restrictions'] ?? '');
    
    // Validation
    if (empty($full_name)) $errors[] = "Patient name is required";
    if ($sick_days <= 0) $errors[] = "Sick days must be greater than 0";
    if (empty($sick_from)) $errors[] = "Sick from date is required";
    if (empty($sick_to)) $errors[] = "Sick to date is required";
    if (empty($diagnosis)) $errors[] = "Diagnosis is required";
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            if ($sick_sheet_type === 'external') {
                // ✅ Update external_sick_sheets - ✅ IMEONGEZWA `bmi = ?`
                $stmt = $db->prepare("
                    UPDATE external_sick_sheets SET
                        full_name = ?,
                        patient_id = ?,
                        phone = ?,
                        gender = ?,
                        date_of_birth = ?,
                        address = ?,
                        blood_group = ?,
                        allergies = ?,
                        symptoms = ?,
                        diagnosis = ?,
                        treatment = ?,
                        instructions = ?,
                        temperature = ?,
                        bp_systolic = ?,
                        bp_diastolic = ?,
                        pulse_rate = ?,
                        oxygen_saturation = ?,
                        weight = ?,
                        height = ?,
                        bmi = ?,
                        lab_results = ?,
                        medications = ?,
                        procedures = ?,
                        sick_days = ?,
                        sick_from = ?,
                        sick_to = ?,
                        sick_reason = ?,
                        sick_restrictions = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $full_name,
                    $patient_number,
                    $phone,
                    $gender,
                    !empty($date_of_birth) ? $date_of_birth : null,
                    $address,
                    $blood_group,
                    $allergies,
                    $symptoms,
                    $diagnosis,
                    $treatment,
                    $instructions,
                    $temperature,
                    $bp_systolic,
                    $bp_diastolic,
                    $pulse_rate,
                    $oxygen_saturation,
                    $weight,
                    $height,
                    $bmi,  // ✅ MPYA - BMI inasave
                    $lab_results,
                    $medications,
                    $procedures,
                    $sick_days,
                    !empty($sick_from) ? $sick_from : null,
                    !empty($sick_to) ? $sick_to : null,
                    $sick_reason,
                    $sick_restrictions,
                    $sick_sheet_id
                ]);
                
            } else {
                // ✅ Update patient_documents
                // ✅ FIXED: Imeongezwa vital signs zote pamoja na bmi
                $stmt = $db->prepare("
                    UPDATE patient_documents SET
                        document_title = ?,
                        description = ?,
                        sick_sheet_days = ?,
                        sick_sheet_from_date = ?,
                        sick_sheet_to_date = ?,
                        sick_sheet_diagnosis = ?,
                        sick_sheet_recommendations = ?,
                        sick_sheet_restrictions = ?,
                        temperature = ?,
                        bp_systolic = ?,
                        bp_diastolic = ?,
                        pulse_rate = ?,
                        oxygen_saturation = ?,
                        weight = ?,
                        height = ?,
                        bmi = ?,
                        updated_at = NOW()
                    WHERE id = ? AND document_type = 'sick_sheet'
                ");
                
                $stmt->execute([
                    'Sick Sheet - ' . $full_name,
                    'Sick Sheet for ' . $full_name . ' - ' . $sick_days . ' days',
                    $sick_days,
                    !empty($sick_from) ? $sick_from : null,
                    !empty($sick_to) ? $sick_to : null,
                    $diagnosis,
                    $instructions,
                    $sick_restrictions,
                    $temperature,
                    $bp_systolic,
                    $bp_diastolic,
                    $pulse_rate,
                    $oxygen_saturation,
                    $weight,
                    $height,
                    $bmi,  // ✅ MPYA - BMI inasave
                    $sick_sheet_id
                ]);
            }
            
            // Log activity
            try {
                $log_stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at) 
                    VALUES (?, ?, ?, 'sick_sheet_updated', ?, NOW())
                ");
                $log_stmt->execute([
                    $user_id,
                    $user_branch_id,
                    $sick_sheet['patient_id'] ?? null,
                    "Updated sick sheet: " . ($sick_sheet['document_number'] ?? 'N/A') . " ($sick_sheet_type) - By: $user_full_name"
                ]);
            } catch (Exception $e) {}
            
            $db->commit();
            
            $message = "✅ Sick sheet updated successfully!";
            $message_type = 'success';
            
            echo '<script>
                setTimeout(function(){
                    window.location.href = "sick_sheets.php?branch=' . $selected_branch_id . '";
                }, 2000);
            </script>';
            
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $message_type = 'error';
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
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    --success: #059669;
    --danger: #DC2626;
    --warning: #D97706;
    --purple: #7C3AED;
    --teal: #0D9488;
    --sky: #0EA5E9;
    --pink: #EC4899;
    --indigo: #4F46E5;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
}

[data-theme="dark"] {
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
}

.page-header-custom {
    background: var(--primary-gradient);
    border-radius: 16px;
    padding: 24px 32px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
    position: relative;
    overflow: hidden;
    color: white;
}

.page-header-custom::before {
    content: '';
    position: absolute;
    top: -60%; right: -10%;
    width: 400px; height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.6rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin: 0;
    position: relative;
    z-index: 1;
}

.page-header-custom .page-title i {
    width: 44px; height: 44px;
    background: rgba(255,255,255,0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.page-header-custom .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 8px;
    position: relative;
    z-index: 1;
}

.badge-doc {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    font-family: monospace;
}

.badge-type {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid rgba(255,255,255,0.2);
}

.btn-outline-light {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1.5px solid rgba(255,255,255,0.3);
    padding: 9px 20px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 1;
}

.btn-outline-light:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
    color: white;
}

.message-box {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-weight: 500;
    font-size: 0.9rem;
}

.message-box.success { background: #D1FAE5; color: #065F46; border: 2px solid #6EE7B7; }
.message-box.error { background: #FEE2E2; color: #991B1B; border: 2px solid #FCA5A5; }

[data-theme="dark"] .message-box.success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
[data-theme="dark"] .message-box.error { background: #3A1A1A; color: #F87171; border-color: #F87171; }

.form-card {
    background: var(--bg-card);
    border-radius: 16px;
    padding: 28px 32px;
    border: 2px solid var(--border-color);
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.form-card:hover { border-color: var(--primary); }

.form-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding-bottom: 18px;
    margin-bottom: 22px;
    border-bottom: 2px solid var(--border-color);
}

.form-header-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: white;
    flex-shrink: 0;
    background: var(--primary-gradient);
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}

.form-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.form-header p {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin: 2px 0 0 0;
}

.section-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0B5ED7;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 8px;
    border-bottom: 2px dashed var(--border-color);
}

.section-title.purple { color: #7C3AED; }
.section-title.teal { color: #0D9488; }
.section-title.orange { color: #D97706; }
.section-title.pink { color: #EC4899; }
.section-title.sky { color: #0EA5E9; }

.form-group { margin-bottom: 16px; }

.form-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 6px;
    display: block;
}

.form-label i { width: 18px; text-align: center; }
.form-label .required { color: #EF4444; margin-left: 2px; }
.form-label .optional { font-weight: 400; color: var(--text-secondary); font-size: 0.7rem; }
.form-label .vital-icon { font-size: 0.9rem; margin-right: 2px; }
.form-label .normal-range { font-size: 0.65rem; color: var(--text-secondary); font-weight: 400; margin-left: 4px; }

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    font-size: 0.85rem;
    background: var(--bg-card);
    color: var(--text-primary);
    outline: none;
    transition: all 0.3s;
    font-family: inherit;
}

.form-control:focus {
    border-color: #0B5ED7;
    box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
}

/* Oxygen field special style */
.form-control.oxygen-input {
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.05), rgba(14, 165, 233, 0.12));
    border-color: #0EA5E9;
    font-weight: 600;
    color: #0284C7;
}

.form-control.oxygen-input:focus {
    border-color: #0284C7;
    box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.15);
}

.form-control:disabled { opacity: 0.6; cursor: not-allowed; }
.form-control::placeholder { color: var(--text-secondary); opacity: 0.6; }
textarea.form-control { resize: vertical; min-height: 70px; }
select.form-control { cursor: pointer; appearance: auto; }

.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.full-width { grid-column: 1 / -1; }

/* ✅ VITAL SIGNS CARD GRID - 7 SIGNS */
.vital-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.vital-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 12px 10px;
    text-align: center;
    border: 2px solid var(--border-color);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.vital-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 12px 12px 0 0;
}

.vital-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

/* VITAL CARD COLORS */
.vital-card.temp-card::before { background: linear-gradient(90deg, #EF4444, #F87171); }
.vital-card.temp-card .vital-icon { color: #EF4444; }

.vital-card.bp-card::before { background: linear-gradient(90deg, #0B5ED7, #1A73E8); }
.vital-card.bp-card .vital-icon { color: #0B5ED7; }

.vital-card.pulse-card::before { background: linear-gradient(90deg, #EC4899, #F472B6); }
.vital-card.pulse-card .vital-icon { color: #EC4899; }

/* ✅ OXYGEN CARD - Sky Blue */
.vital-card.oxygen-card::before { background: linear-gradient(90deg, #0EA5E9, #38BDF8); }
.vital-card.oxygen-card .vital-icon { color: #0284C7; }
.vital-card.oxygen-card {
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.05), rgba(14, 165, 233, 0.12));
    border-color: #0EA5E9;
}
.vital-card.oxygen-card:hover {
    border-color: #0284C7;
    box-shadow: 0 6px 20px rgba(14, 165, 233, 0.25);
}

.vital-card.weight-card::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
.vital-card.weight-card .vital-icon { color: #7C3AED; }

.vital-card.height-card::before { background: linear-gradient(90deg, #059669, #10B981); }
.vital-card.height-card .vital-icon { color: #059669; }

.vital-card.bmi-card::before { background: linear-gradient(90deg, #D97706, #F59E0B); }
.vital-card.bmi-card .vital-icon { color: #D97706; }

.vital-card .vital-icon {
    font-size: 1.2rem;
    margin-bottom: 4px;
    display: block;
}

.vital-card .vital-label-small {
    font-size: 0.6rem;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
    display: block;
}

.vital-card .vital-input {
    width: 100%;
    padding: 8px 10px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 700;
    text-align: center;
    background: var(--bg-card);
    color: var(--text-primary);
    outline: none;
    transition: all 0.3s;
}

.vital-card .vital-input:focus {
    border-color: #0B5ED7;
    box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
}

.vital-card.oxygen-card .vital-input {
    color: #0284C7;
    border-color: #0EA5E9;
}

.vital-card.temp-card .vital-input { color: #EF4444; }
.vital-card.bp-card .vital-input { color: #0B5ED7; }
.vital-card.pulse-card .vital-input { color: #EC4899; }
.vital-card.weight-card .vital-input { color: #7C3AED; }
.vital-card.height-card .vital-input { color: #059669; }
.vital-card.bmi-card .vital-input { color: #D97706; }

.vital-card .vital-unit-small {
    font-size: 0.6rem;
    color: var(--text-secondary);
    margin-top: 4px;
    display: block;
}

.vital-card .bp-inputs {
    display: flex;
    gap: 4px;
    align-items: center;
}

.vital-card .bp-inputs span {
    font-weight: 700;
    color: var(--text-secondary);
}

/* Vital info footer */
.vital-info-footer {
    margin-top: 12px;
    padding: 8px 14px;
    background: linear-gradient(135deg, #F0F9FF, #E0F2FE);
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    font-size: 0.7rem;
    border: 1px dashed #0EA5E9;
}

[data-theme="dark"] .vital-info-footer {
    background: #0C2A3A;
    border-color: #0EA5E9;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
    text-decoration: none;
    font-family: inherit;
    min-height: 44px;
}

.btn-primary {
    background: var(--primary-gradient);
    color: white;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0A4CA8, #1557B0);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.4);
    color: white;
}

.btn-outline {
    background: transparent;
    color: var(--text-secondary);
    border: 2px solid var(--border-color);
}

.btn-outline:hover {
    border-color: #0B5ED7;
    color: #0B5ED7;
    background: rgba(11, 94, 215, 0.05);
}

.btn-danger {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

.btn-danger:hover {
    background: linear-gradient(135deg, #B91C1C, #991B1B);
    transform: translateY(-2px);
    color: white;
}

.form-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding-top: 22px;
    margin-top: 22px;
    border-top: 2px solid var(--border-color);
}

.info-box {
    padding: 12px 16px;
    border-radius: 10px;
    background: #E8F0FE;
    color: #0B5ED7;
    border: 1px solid #BFDBFE;
    font-size: 0.78rem;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
}

[data-theme="dark"] .info-box { background: #1E3A5F; color: #6EA8FE; border-color: #1E3A5F; }

.footer {
    padding: 14px 0;
    border-top: 1px solid var(--border-color);
    margin-top: 24px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand { color: #0B5ED7; font-weight: 600; }

@media (max-width: 992px) {
    .vital-grid { grid-template-columns: repeat(3, 1fr); }
    .grid-3 { grid-template-columns: 1fr 1fr; }
    .grid-4 { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 768px) {
    .page-header-custom { padding: 16px 18px; }
    .page-header-custom .page-title { font-size: 1.2rem; }
    .form-card { padding: 18px 20px; }
    .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
    .vital-grid { grid-template-columns: repeat(2, 1fr); }
    .form-actions { flex-direction: column; }
    .form-actions .btn { width: 100%; }
}

@media (max-width: 480px) {
    .vital-grid { grid-template-columns: 1fr 1fr; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-medical"></i>
                Edit Sick Sheet
                <span class="badge-type">
                    <i class="fas <?= $sick_sheet_type === 'external' ? 'fa-globe-africa' : 'fa-user-check' ?>"></i>
                    <?= $sick_sheet_type === 'external' ? 'External' : 'Internal' ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i>
                <span class="badge-doc"><?= htmlspecialchars($sick_sheet['document_number'] ?? 'N/A') ?></span>
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($sick_sheet['full_name'] ?? 'N/A') ?></span>
                <span><i class="fas fa-store-alt"></i> <?= htmlspecialchars($sick_sheet['branch_name'] ?? 'N/A') ?></span>
            </p>
        </div>
        <a href="sick_sheets.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.3rem;flex-shrink:0;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- FORM -->
    <form method="POST" action="" id="editSickSheetForm">
        <div class="form-card">
            
            <div class="form-header">
                <div class="form-header-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div>
                    <h3>Sick Sheet Information</h3>
                    <p>Update the sick sheet details below</p>
                </div>
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <span>
                    <strong>Document #:</strong> <?= htmlspecialchars($sick_sheet['document_number'] ?? 'N/A') ?>
                    <span style="margin:0 8px;">|</span>
                    <strong>Type:</strong> <?= ucfirst($sick_sheet_type) ?>
                </span>
            </div>
            
            <!-- ============================================================ -->
            <!-- PATIENT INFORMATION -->
            <!-- ============================================================ -->
            <div class="section-title">
                <i class="fas fa-user-circle"></i> Patient Information
            </div>
            
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user" style="color:#0B5ED7;"></i>
                        Full Name <span class="required">*</span>
                    </label>
                    <input type="text" name="full_name" class="form-control" 
                           value="<?= htmlspecialchars($sick_sheet['full_name'] ?? '') ?>" 
                           placeholder="Enter full name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-id-card" style="color:#0B5ED7;"></i>
                        Patient ID
                    </label>
                    <input type="text" name="patient_number" class="form-control" 
                           value="<?= htmlspecialchars($sick_sheet['patient_id'] ?? '') ?>" 
                           placeholder="Enter patient ID">
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-phone" style="color:#059669;"></i>
                        Phone
                    </label>
                    <input type="text" name="phone" class="form-control" 
                           value="<?= htmlspecialchars($sick_sheet['phone'] ?? '') ?>" 
                           placeholder="Enter phone number">
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-venus-mars" style="color:#0B5ED7;"></i>
                        Gender
                    </label>
                    <select name="gender" class="form-control">
                        <option value="">-- Select --</option>
                        <option value="Male" <?= ($sick_sheet['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($sick_sheet['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= ($sick_sheet['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar" style="color:#0B5ED7;"></i>
                        Date of Birth
                    </label>
                    <input type="date" name="date_of_birth" class="form-control" 
                           value="<?= htmlspecialchars($sick_sheet['date_of_birth'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-tint" style="color:#DC2626;"></i>
                        Blood Group
                    </label>
                    <select name="blood_group" class="form-control">
                        <option value="">-- Select --</option>
                        <?php 
                        $bg_options = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                        foreach ($bg_options as $bg): 
                        ?>
                            <option value="<?= $bg ?>" <?= ($sick_sheet['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group full-width">
                    <label class="form-label">
                        <i class="fas fa-map-marker-alt" style="color:#0B5ED7;"></i>
                        Address
                    </label>
                    <input type="text" name="address" class="form-control" 
                           value="<?= htmlspecialchars($sick_sheet['address'] ?? '') ?>" 
                           placeholder="Enter address">
                </div>
                
                <div class="form-group full-width">
                    <label class="form-label">
                        <i class="fas fa-exclamation-triangle" style="color:#D97706;"></i>
                        Allergies
                    </label>
                    <input type="text" name="allergies" class="form-control" 
                           value="<?= htmlspecialchars($sick_sheet['allergies'] ?? '') ?>" 
                           placeholder="Enter allergies (e.g. Penicillin)">
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- ✅ VITAL SIGNS - 7 CARDS (WITH OXYGEN) -->
            <!-- ============================================================ -->
            <div class="section-title sky" style="margin-top:12px;">
                <i class="fas fa-heartbeat"></i> Vital Signs (7 Signs)
                <span style="font-size:0.7rem;font-weight:400;color:#0284C7;margin-left:8px;">🫁 SpO2 Normal: 95-100%</span>
            </div>
            
            <div class="vital-grid">
                
                <!-- 1. TEMPERATURE -->
                <div class="vital-card temp-card">
                    <span class="vital-icon">🌡️</span>
                    <span class="vital-label-small">Temperature</span>
                    <input type="number" step="0.1" name="temperature" 
                           class="vital-input" 
                           value="<?= htmlspecialchars($sick_sheet['temperature'] ?? '') ?>" 
                           placeholder="36.5">
                    <span class="vital-unit-small">°C</span>
                </div>
                
                <!-- 2. BLOOD PRESSURE -->
                <div class="vital-card bp-card">
                    <span class="vital-icon">💓</span>
                    <span class="vital-label-small">Blood Pressure</span>
                    <div class="bp-inputs">
                        <input type="number" name="bp_systolic" class="vital-input" 
                               value="<?= htmlspecialchars($sick_sheet['bp_systolic'] ?? '') ?>" 
                               placeholder="120">
                        <span>/</span>
                        <input type="number" name="bp_diastolic" class="vital-input" 
                               value="<?= htmlspecialchars($sick_sheet['bp_diastolic'] ?? '') ?>" 
                               placeholder="80">
                    </div>
                    <span class="vital-unit-small">mmHg</span>
                </div>
                
                <!-- 3. PULSE RATE -->
                <div class="vital-card pulse-card">
                    <span class="vital-icon">💗</span>
                    <span class="vital-label-small">Pulse Rate</span>
                    <input type="number" name="pulse_rate" class="vital-input" 
                           value="<?= htmlspecialchars($sick_sheet['pulse_rate'] ?? '') ?>" 
                           placeholder="72">
                    <span class="vital-unit-small">bpm</span>
                </div>
                
                <!-- 4. ✅ OXYGEN SATURATION (SpO2) - 7th VITAL SIGN -->
                <!-- ✅ FIXED: Hakuna max limit - inakubali thamani yoyote -->
                <div class="vital-card oxygen-card">
                    <span class="vital-icon">🫁</span>
                    <span class="vital-label-small">Oxygen (SpO2)</span>
                    <input type="number" name="oxygen_saturation" 
                           class="vital-input oxygen-input" 
                           min="0"
                           value="<?= htmlspecialchars($sick_sheet['oxygen_saturation'] ?? '') ?>" 
                           placeholder="98">
                    <span class="vital-unit-small">%</span>
                </div>
                
                <!-- 5. WEIGHT -->
                <div class="vital-card weight-card">
                    <span class="vital-icon">⚖️</span>
                    <span class="vital-label-small">Weight</span>
                    <input type="number" step="0.1" name="weight" 
                           class="vital-input" 
                           id="weightInput"
                           value="<?= htmlspecialchars($sick_sheet['weight'] ?? '') ?>" 
                           placeholder="65">
                    <span class="vital-unit-small">kg</span>
                </div>
                
                <!-- 6. HEIGHT -->
                <div class="vital-card height-card">
                    <span class="vital-icon">📏</span>
                    <span class="vital-label-small">Height</span>
                    <input type="number" step="0.1" name="height" 
                           class="vital-input" 
                           id="heightInput"
                           value="<?= htmlspecialchars($sick_sheet['height'] ?? '') ?>" 
                           placeholder="170">
                    <span class="vital-unit-small">cm</span>
                </div>
                
                <!-- 7. BMI (Auto-calculated) -->
                <div class="vital-card bmi-card">
                    <span class="vital-icon">📊</span>
                    <span class="vital-label-small">BMI</span>
                    <input type="text" name="bmi" 
                           class="vital-input" 
                           id="bmiInput"
                           value="<?= htmlspecialchars($sick_sheet['bmi'] ?? '') ?>" 
                           placeholder="Auto" readonly
                           style="cursor:not-allowed;opacity:0.9;">
                    <span class="vital-unit-small">kg/m²</span>
                </div>
                
                <!-- Empty slot for alignment -->
                <div style="visibility:hidden;"></div>
                
            </div>
            
            <!-- Vital Info Footer -->
            <div class="vital-info-footer">
                <i class="fas fa-lungs" style="color:#0EA5E9;"></i>
                <span style="color:#0284C7;">SpO2 (Oxygen Saturation) Normal Range: <strong>95-100%</strong></span>
                <span style="color:#64748B;"> • 7 Vital Signs Tracked</span>
                <span style="color:#64748B;"> • BMI auto-calculated</span>
            </div>
            
            <!-- ============================================================ -->
            <!-- CLINICAL INFORMATION -->
            <!-- ============================================================ -->
            <div class="section-title purple" style="margin-top:16px;">
                <i class="fas fa-stethoscope"></i> Clinical Information
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-notes-medical" style="color:#0B5ED7;"></i>
                        Symptoms
                    </label>
                    <textarea name="symptoms" class="form-control" rows="2" 
                              placeholder="Enter symptoms..."><?= htmlspecialchars($sick_sheet['symptoms'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-diagnoses" style="color:#7C3AED;"></i>
                        Diagnosis <span class="required">*</span>
                    </label>
                    <textarea name="diagnosis" class="form-control" rows="2" 
                              placeholder="Enter diagnosis..." required><?= htmlspecialchars($sick_sheet['diagnosis'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-pills" style="color:#059669;"></i>
                        Treatment
                    </label>
                    <textarea name="treatment" class="form-control" rows="2" 
                              placeholder="Enter treatment..."><?= htmlspecialchars($sick_sheet['treatment'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-prescription-bottle" style="color:#0B5ED7;"></i>
                        Instructions
                    </label>
                    <textarea name="instructions" class="form-control" rows="2" 
                              placeholder="Enter instructions..."><?= htmlspecialchars($sick_sheet['instructions'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- LAB & MEDICATIONS -->
            <!-- ============================================================ -->
            <div class="section-title teal" style="margin-top:12px;">
                <i class="fas fa-flask"></i> Lab & Medications
            </div>
            
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-flask" style="color:#7C3AED;"></i>
                        Lab Results
                    </label>
                    <textarea name="lab_results" class="form-control" rows="3" 
                              placeholder="Enter lab results..."><?= htmlspecialchars($sick_sheet['lab_results'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-pills" style="color:#059669;"></i>
                        Medications
                    </label>
                    <textarea name="medications" class="form-control" rows="3" 
                              placeholder="Enter medications..."><?= htmlspecialchars($sick_sheet['medications'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-syringe" style="color:#0D9488;"></i>
                        Procedures
                    </label>
                    <textarea name="procedures" class="form-control" rows="3" 
                              placeholder="Enter procedures..."><?= htmlspecialchars($sick_sheet['procedures'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- SICK LEAVE DETAILS -->
            <!-- ============================================================ -->
            <div class="section-title orange" style="margin-top:12px;">
                <i class="fas fa-calendar-check"></i> Sick Leave Details
            </div>
            
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar-day" style="color:#D97706;"></i>
                        Sick Days <span class="required">*</span>
                    </label>
                    <input type="number" name="sick_days" class="form-control" 
                           value="<?= htmlspecialchars($sick_sheet['sick_days'] ?? 3) ?>" 
                           min="1" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar-alt" style="color:#D97706;"></i>
                        From Date <span class="required">*</span>
                    </label>
                    <input type="date" name="sick_from" class="form-control" 
                           value="<?= htmlspecialchars($sick_sheet['sick_from'] ?? date('Y-m-d')) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar-alt" style="color:#D97706;"></i>
                        To Date <span class="required">*</span>
                    </label>
                    <input type="date" name="sick_to" class="form-control" 
                           value="<?= htmlspecialchars($sick_sheet['sick_to'] ?? date('Y-m-d', strtotime('+3 days'))) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-comment-medical" style="color:#D97706;"></i>
                        Reason
                    </label>
                    <input type="text" name="sick_reason" class="form-control" 
                           value="<?= htmlspecialchars($sick_sheet['sick_reason'] ?? 'Medical condition requiring rest') ?>" 
                           placeholder="Enter reason">
                </div>
                
                <div class="form-group full-width">
                    <label class="form-label">
                        <i class="fas fa-ban" style="color:#DC2626;"></i>
                        Restrictions
                    </label>
                    <input type="text" name="sick_restrictions" class="form-control" 
                           value="<?= htmlspecialchars($sick_sheet['sick_restrictions'] ?? 'No heavy lifting, complete rest') ?>" 
                           placeholder="Enter restrictions">
                </div>
            </div>
            
            <!-- FORM ACTIONS -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="view_sick_sheet.php?id=<?= $sick_sheet_id ?>&type=<?= $sick_sheet_type ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="sick_sheets.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <a href="delete_sick_sheet.php?id=<?= $sick_sheet_id ?>&type=<?= $sick_sheet_type ?>&branch=<?= $selected_branch_id ?>" 
                   class="btn btn-danger"
                   onclick="return confirm('⚠️ Delete this sick sheet?\n\nDocument #: <?= htmlspecialchars(addslashes($sick_sheet['document_number'] ?? 'N/A')) ?>\nPatient: <?= htmlspecialchars(addslashes($sick_sheet['full_name'] ?? 'N/A')) ?>\n\nThis action CANNOT be undone!');">
                    <i class="fas fa-trash"></i> Delete
                </a>
            </div>
            
        </div>
    </form>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span>
            Edit Sick Sheet - <?= htmlspecialchars($sick_sheet['document_number'] ?? 'N/A') ?>
            <span>|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
// ================================================================
// AUTO-CALCULATE BMI
// ================================================================
function calculateBMI() {
    var weight = parseFloat(document.getElementById('weightInput')?.value) || 0;
    var height = parseFloat(document.getElementById('heightInput')?.value) || 0;
    var bmiInput = document.getElementById('bmiInput');
    
    if (!bmiInput) return;
    
    if (weight > 0 && height > 0) {
        var heightM = height / 100;
        var bmi = weight / (heightM * heightM);
        bmiInput.value = bmi.toFixed(1);
    } else {
        bmiInput.value = '';
    }
}

document.getElementById('weightInput')?.addEventListener('input', calculateBMI);
document.getElementById('heightInput')?.addEventListener('input', calculateBMI);

// Calculate on page load
document.addEventListener('DOMContentLoaded', calculateBMI);

// ================================================================
// AUTO-CALCULATE SICK DAYS
// ================================================================
document.querySelector('input[name="sick_from"]')?.addEventListener('change', function() {
    var from = new Date(this.value);
    var to = new Date(document.querySelector('input[name="sick_to"]').value);
    if (from && to && to >= from) {
        var days = Math.ceil((to - from) / (1000 * 60 * 60 * 24)) + 1;
        document.querySelector('input[name="sick_days"]').value = days;
    }
});

document.querySelector('input[name="sick_to"]')?.addEventListener('change', function() {
    var to = new Date(this.value);
    var from = new Date(document.querySelector('input[name="sick_from"]').value);
    if (from && to && to >= from) {
        var days = Math.ceil((to - from) / (1000 * 60 * 60 * 24)) + 1;
        document.querySelector('input[name="sick_days"]').value = days;
    }
});

// ================================================================
// ✅ OXYGEN SATURATION VALIDATION
// ✅ FIXED: Inakubali thamani YOYOTE (0 - 100 na ZAIDI)
// ✅ Haizuii kuandika, inaonyesha warning tu kwa rangi
// ================================================================
var oxygenInput = document.querySelector('input[name="oxygen_saturation"]');
if (oxygenInput) {
    oxygenInput.addEventListener('input', function() {
        if (this.value === '' || this.value === '-') {
            this.style.color = '';
            this.style.borderColor = '';
            this.title = '';
            return;
        }
        
        var val = parseFloat(this.value);
        
        if (isNaN(val)) {
            this.style.color = '';
            this.style.borderColor = '';
            this.title = '';
            return;
        }
        
        if (val < 0) {
            this.style.color = '#DC2626';
            this.style.borderColor = '#DC2626';
            this.title = '❌ SpO2 haiwezi kuwa hasi! Thamani si sahihi.';
        } else if (val < 70) {
            this.style.color = '#DC2626';
            this.style.borderColor = '#DC2626';
            this.title = '⚠️ SpO2 chini sana - Hatari ya kifo!';
        } else if (val < 95) {
            this.style.color = '#D97706';
            this.style.borderColor = '#D97706';
            this.title = '⚠️ SpO2 chini ya kawaida (Normal: 95-100%)';
        } else if (val <= 100) {
            this.style.color = '#059669';
            this.style.borderColor = '#0EA5E9';
            this.title = '✅ SpO2 nzuri';
        } else {
            this.style.color = '#D97706';
            this.style.borderColor = '#D97706';
            this.title = '⚠️ SpO2 imezidi 100%! Kawaida ni 95-100%. Angalia tena.';
        }
    });
    
    if (oxygenInput.value !== '') {
        oxygenInput.dispatchEvent(new Event('input'));
    }
}

// ================================================================
// FOOTER TIME
// ================================================================
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

// ================================================================
// FORM VALIDATION
// ================================================================
document.getElementById('editSickSheetForm')?.addEventListener('submit', function(e) {
    var fullName = document.querySelector('input[name="full_name"]').value.trim();
    var diagnosis = document.querySelector('textarea[name="diagnosis"]').value.trim();
    var sickDays = parseInt(document.querySelector('input[name="sick_days"]').value);
    
    if (!fullName) {
        e.preventDefault();
        alert('Patient name is required');
        return false;
    }
    if (!diagnosis) {
        e.preventDefault();
        alert('Diagnosis is required');
        return false;
    }
    if (sickDays <= 0) {
        e.preventDefault();
        alert('Sick days must be greater than 0');
        return false;
    }
    
    // ✅ Warning tu (sio kuzuia) kama oxygen > 100 au < 0
    var oxygenVal = parseFloat(document.querySelector('input[name="oxygen_saturation"]')?.value);
    if (!isNaN(oxygenVal)) {
        if (oxygenVal > 100) {
            var proceed = confirm('⚠️ SpO2 imezidi 100% (' + oxygenVal + '%)!\n\nKawaida ni 95-100%.\n\nUna uhakika unataka kuendelea?');
            if (!proceed) {
                e.preventDefault();
                return false;
            }
        } else if (oxygenVal < 0) {
            var proceed2 = confirm('❌ SpO2 haiwezi kuwa hasi (' + oxygenVal + '%)!\n\nUna uhakika unataka kuendelea?');
            if (!proceed2) {
                e.preventDefault();
                return false;
            }
        }
    }
    
    var submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }
    return true;
});

console.log('%c✏️ Admin - Edit Sick Sheet (7 Vital Signs)', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ 7 Vital Signs: Temp, BP, Pulse, SpO2, Weight, Height, BMI', 'font-size:13px;color:#EC4899;');
console.log('%c🫁 Oxygen Saturation (SpO2) - Inakubali thamani YOYOTE (0 - 100+)', 'font-size:13px;color:#0EA5E9;');
console.log('%c✅ BMI inasave kwenye database (external + internal)', 'font-size:13px;color:#34D399;font-weight:bold;');
console.log('%c📋 Type: <?= $sick_sheet_type ?>', 'font-size:13px;color:#059669;');
</script>

</body>
</html>