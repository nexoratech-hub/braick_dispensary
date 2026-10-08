<?php
// ================================================================
// FILE: frontend/pages/reception/edit_patient.php
// RECEPTION - EDIT PATIENT (V14 - SHARED HEADER/SIDEBAR)
// ✅ Using shared reception_header.php & reception_sidebar.php
// ✅ 7 Vital Signs cards (with SpO2)
// ✅ Allergies + Symptoms chips
// ✅ AJAX doctor status
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$allowed_roles = ['reception', 'cashier', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id        = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name']   ?? 'Receptionist';
$user_role      = $_SESSION['role']        ?? 'reception';
$branch_id      = $_SESSION['branch_id']   ?? 1;
$branch_name    = $_SESSION['branch_name'] ?? 'Dodoma';
$username       = $_SESSION['username']    ?? 'reception';
$profile_pic    = $_SESSION['profile_pic'] ?? '';

$full_name           = $user_full_name;
$user_branch_id      = $branch_id;
$selected_branch_id  = $branch_id;
$is_admin            = ($user_role === 'admin');
$message             = '';
$message_type        = '';

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($patient_id <= 0) {
    header('Location: patients.php?error=invalid_id');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    $unread_notifications = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $unread_notifications = 0;
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// GET PATIENT
$patient    = null;
$last_visit = null;

try {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as assigned_doctor_name, b.name as branch_name
        FROM patients p
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$patient_id, $branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patient = null;
}

if (!$patient) {
    header('Location: patients.php?error=notfound');
    exit;
}

try {
    $stmt = $db->prepare("SELECT * FROM visits WHERE patient_id = ? AND branch_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$patient_id, $branch_id]);
    $last_visit = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $last_visit = null; }

// GET VITAL SIGNS
$vital_signs = null;
try {
    $stmt = $db->prepare("SELECT * FROM vital_signs WHERE patient_id = ? AND branch_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$patient_id, $branch_id]);
    $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $vital_signs = null; }

// GET DOCTORS
$doctors              = [];
$online_doctors       = [];
$offline_doctors      = [];
$online_doctors_count = 0;
$offline_doctors_count = 0;

try {
    $stmt = $db->prepare("
        SELECT id, full_name, specialty, is_online
        FROM users
        WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
        ORDER BY is_online DESC, full_name
    ");
    $stmt->execute([$selected_branch_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($doctors as $doc) {
        if ($doc['is_online'] == 1) { $online_doctors[] = $doc; $online_doctors_count++; }
        else                        { $offline_doctors[] = $doc; $offline_doctors_count++; }
    }
} catch (Exception $e) {}

// AJAX: DOCTOR STATUS
if (isset($_POST['action']) && $_POST['action'] === 'get_doctor_status') {
    header('Content-Type: application/json');
    $bid = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : $selected_branch_id;

    $stmt = $db->prepare("SELECT id, full_name, specialty, is_online FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ? ORDER BY is_online DESC, full_name");
    $stmt->execute([$bid]);
    $doctors_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $online = []; $offline = [];
    $online_count = 0; $offline_count = 0;

    foreach ($doctors_list as $doc) {
        if ($doc['is_online'] == 1) { $online[] = $doc; $online_count++; }
        else                        { $offline[] = $doc; $offline_count++; }
    }

    $options_html = '<option value="">-- Select Doctor --</option>';

    if ($online_count > 0) {
        $options_html .= '<optgroup label="🟢 Online Doctors (' . $online_count . ')">';
        foreach ($online as $doc) {
            $options_html .= '<option value="' . $doc['id'] . '" data-online="1">';
            $options_html .= '🟢 Dr. ' . htmlspecialchars($doc['full_name']);
            if (!empty($doc['specialty'])) $options_html .= ' (' . htmlspecialchars($doc['specialty']) . ')';
            $options_html .= '</option>';
        }
        $options_html .= '</optgroup>';
    }

    if ($offline_count > 0) {
        $options_html .= '<optgroup label="⚪ Offline Doctors (' . $offline_count . ')">';
        foreach ($offline as $doc) {
            $options_html .= '<option value="' . $doc['id'] . '" data-online="0">';
            $options_html .= '⚪ Dr. ' . htmlspecialchars($doc['full_name']);
            if (!empty($doc['specialty'])) $options_html .= ' (' . htmlspecialchars($doc['specialty']) . ')';
            $options_html .= '</option>';
        }
        $options_html .= '</optgroup>';
    }

    if (empty($doctors_list)) $options_html .= '<option value="" disabled>No doctors available</option>';

    echo json_encode([
        'success'         => true,
        'online_count'    => $online_count,
        'offline_count'   => $offline_count,
        'total_doctors'   => count($doctors_list),
        'doctor_options'  => $options_html,
        'timestamp'       => date('H:i:s')
    ]);
    exit;
}

// HANDLE UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_patient'])) {
    try {
        $db->beginTransaction();

        $full_name_new       = trim($_POST['full_name'] ?? '');
        $gender              = $_POST['gender'] ?? null;
        $date_of_birth       = $_POST['date_of_birth'] ?? null;
        $marital_status      = $_POST['marital_status'] ?? null;
        $phone               = trim($_POST['phone'] ?? '');
        $email               = trim($_POST['email'] ?? '');
        $address             = trim($_POST['address'] ?? '');
        $emergency_contact   = trim($_POST['emergency_contact'] ?? '');
        $blood_group         = $_POST['blood_group'] ?? null;
        $allergies           = trim($_POST['allergies'] ?? '');
        $assigned_doctor_id  = !empty($_POST['assigned_doctor_id']) ? (int)$_POST['assigned_doctor_id'] : null;

        if (empty($full_name_new)) throw new Exception("Patient name is required");
        if (empty($gender))        throw new Exception("Gender is required");

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
            $patient_id, $branch_id
        ]);

        if ($last_visit && isset($last_visit['id'])) {
            $symptoms  = trim($_POST['symptoms'] ?? '');
            $complaint = trim($_POST['complaint'] ?? '');
            $notes     = trim($_POST['notes'] ?? '');

            $stmt = $db->prepare("UPDATE visits SET symptoms = ?, complaint = ?, notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$symptoms, $complaint, $notes, $last_visit['id']]);
        }

        $temperature        = !empty($_POST['temperature'])        ? (float)$_POST['temperature']        : null;
        $bp_systolic        = !empty($_POST['bp_systolic'])        ? (int)$_POST['bp_systolic']          : null;
        $bp_diastolic       = !empty($_POST['bp_diastolic'])       ? (int)$_POST['bp_diastolic']         : null;
        $pulse_rate         = !empty($_POST['pulse_rate'])         ? (int)$_POST['pulse_rate']           : null;
        $weight             = !empty($_POST['weight'])             ? (float)$_POST['weight']             : null;
        $height             = !empty($_POST['height'])             ? (float)$_POST['height']             : null;
        $oxygen_saturation  = !empty($_POST['oxygen_saturation'])  ? (int)$_POST['oxygen_saturation']    : null;
        $vital_notes        = trim($_POST['vital_notes'] ?? '');

        $bmi = null;
        if ($weight && $height && $height > 0) {
            $height_m = $height / 100;
            $bmi = round($weight / ($height_m * $height_m), 1);
        }

        if ($vital_signs && isset($vital_signs['id'])) {
            $stmt = $db->prepare("
                UPDATE vital_signs SET
                    temperature = ?, blood_pressure_systolic = ?, blood_pressure_diastolic = ?,
                    pulse_rate = ?, weight = ?, height = ?, bmi = ?,
                    oxygen_saturation = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $temperature, $bp_systolic, $bp_diastolic, $pulse_rate,
                $weight, $height, $bmi, $oxygen_saturation, $vital_notes ?: null,
                $vital_signs['id']
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
                $patient_id, $last_visit['id'], $user_id, $branch_id,
                $temperature, $bp_systolic, $bp_diastolic, $pulse_rate,
                $weight, $height, $bmi, $oxygen_saturation, $vital_notes ?: null
            ]);
        }

        try {
            $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) VALUES (?, ?, 'edit_patient', ?, ?, NOW())")
                ->execute([$user_id, $branch_id, "Edited patient: {$patient['full_name']} (ID: {$patient['patient_id']})", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        } catch (Exception $e) {}

        $db->commit();

        $message = "Patient updated successfully!";
        $message_type = 'success';

        // Re-fetch
        $stmt = $db->prepare("SELECT p.*, u.full_name as assigned_doctor_name, b.name as branch_name FROM patients p LEFT JOIN users u ON p.assigned_doctor_id = u.id LEFT JOIN branches b ON p.branch_id = b.id WHERE p.id = ? AND p.branch_id = ?");
        $stmt->execute([$patient_id, $branch_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT * FROM vital_signs WHERE patient_id = ? AND branch_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$patient_id, $branch_id]);
        $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $message = "Error: " . $e->getMessage();
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

$profile_pic_url = !empty($profile_pic) ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$logo_path       = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// NOTE: time_ago() is provided by reception_header.php
// DO NOT redeclare here.
// ================================================================

include_once __DIR__ . '/../../components/reception_header.php';
include_once __DIR__ . '/../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ================================================================
           EDIT PATIENT PAGE - SPECIFIC STYLES ONLY
           (Base styles, variables, .card, .footer, .toast-custom,
            .main-content are provided by reception_header.php)
           ================================================================ */

        /* ---------- PAGE HEADER ---------- */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083B8A 100%);
            border-radius: 20px;
            padding: 22px 28px;
            margin-bottom: 22px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            box-shadow: 0 10px 40px rgba(11, 94, 215, 0.3);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -80%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header .page-title {
            color: white;
            font-size: 1.5rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            letter-spacing: -0.5px;
        }

        .page-header .page-title i { font-size: 1.6rem; opacity: 0.95; }

        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 6px;
        }

        .page-header .page-subtitle strong { color: white; font-weight: 700; }

        .page-header .header-badge {
            background: rgba(255,255,255,0.18);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.62rem;
            font-weight: 600;
            backdrop-filter: blur(8px);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(255,255,255,0.15);
        }

        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 7px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(8px);
            position: relative;
            z-index: 1;
            cursor: pointer;
        }

        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            color: white;
        }

        /* ---------- ALERT MESSAGES ---------- */
        .alert-message {
            max-width: 1200px;
            margin: 0 auto 18px;
            padding: 14px 18px;
            border-radius: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            animation: slideDown 0.4s ease;
        }

        .alert-message.success {
            background: #D1FAE5;
            color: #059669;
            border: 1px solid #34D399;
        }

        .alert-message.error {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #F87171;
        }

        [data-theme="dark"] .alert-message.success { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .alert-message.error   { background: #3A1A1A; color: #F87171; }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ---------- FORM CARD ---------- */
        .form-card-modern {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 28px 32px;
            border: 1px solid var(--border-color);
            max-width: 1200px;
            margin: 0 auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }

        .form-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 22px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
        }

        .form-header .form-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }

        .form-header .form-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0;
        }

        .form-header .form-subtitle {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 3px;
        }

        /* ---------- PATIENT AVATAR DISPLAY ---------- */
        .patient-avatar-display {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 20px;
            background: linear-gradient(135deg, #E8F0FE, #DBEAFE);
            border-radius: 16px;
            border: 2px solid #6EA8FE;
            margin-bottom: 22px;
        }

        [data-theme="dark"] .patient-avatar-display {
            background: linear-gradient(135deg, #1E3A5F, #1E40AF);
            border-color: #3B82F6;
        }

        .patient-avatar-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.5rem;
            flex-shrink: 0;
            box-shadow: 0 6px 20px rgba(11, 94, 215, 0.35);
            text-transform: uppercase;
        }

        .patient-avatar-info h3 {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 4px 0;
        }

        .patient-avatar-info p {
            font-size: 0.72rem;
            color: var(--text-secondary);
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        /* ---------- FORM CONTROLS ---------- */
        .form-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .form-label .required { color: var(--danger); margin-left: 3px; }
        .form-label .label-icon { margin-right: 5px; color: var(--primary); }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 0.82rem;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
        }

        .form-row  { margin-bottom: 16px; }
        .grid-2    { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .grid-full { grid-column: 1 / -1; }

        /* ---------- FORM ACTIONS ---------- */
        .form-actions {
            display: flex;
            gap: 10px;
            padding-top: 22px;
            margin-top: 22px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 10px 22px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.8rem;
            cursor: pointer;
            border: none;
            text-decoration: none;
            min-height: 42px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(11, 94, 215, 0.4);
            color: white;
        }
        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .btn-purple {
            background: linear-gradient(135deg, #7C3AED, #5B21B6);
            color: white;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
        }
        .btn-purple:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(124, 58, 237, 0.4);
            color: white;
        }

        /* ---------- VITAL SIGNS SECTION ---------- */
        .vital-signs-section {
            margin-top: 22px;
            padding-top: 18px;
            border-top: 2px solid var(--border-color);
        }

        .vital-signs-section .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }

        .vital-signs-section .section-title {
            font-size: 0.95rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
        }

        .vital-signs-section .section-title i { color: #DC2626; font-size: 1.1rem; }

        .vital-signs-section .section-badge {
            font-size: 0.62rem;
            padding: 4px 12px;
            border-radius: 20px;
            background: #FEF3C7;
            color: #D97706;
            font-weight: 700;
        }

        [data-theme="dark"] .vital-signs-section .section-badge { background: #3D2E0A; color: #FBBF24; }

        .vital-grid-6 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 12px;
        }

        .vital-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 12px;
        }

        /* ---------- VITAL CARDS ---------- */
        .vital-card {
            background: var(--bg-body);
            border-radius: 14px;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            position: relative;
            overflow: hidden;
            min-height: 110px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .vital-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
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
            gap: 10px;
            margin-bottom: 8px;
        }

        .vital-card .vital-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .vital-card .vital-label {
            font-size: 0.62rem;
            font-weight: 800;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: var(--text-primary);
        }

        .vital-card .vital-sublabel {
            font-size: 0.52rem;
            color: var(--text-secondary);
            font-weight: 500;
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
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 700;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
        }

        .vital-card .vital-input-wrap input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }

        .vital-card .vital-unit {
            font-size: 0.55rem;
            color: var(--text-secondary);
            font-weight: 700;
            padding: 3px 7px;
            background: var(--bg-card);
            border-radius: 6px;
            white-space: nowrap;
            border: 1px solid var(--border-color);
        }

        /* Vital card color variants */
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
        .vital-card.bmi input { background: #E8F0FE; color: #0B5ED7; font-weight: 800; }
        [data-theme="dark"] .vital-card.bmi input { background: #1E3A5F; color: #6EA8FE; }

        .vital-card.spo2::before { background: linear-gradient(135deg, #0891B2, #0E7490); }
        .vital-card.spo2 .vital-icon { color: #0891B2; background: rgba(8, 145, 178, 0.1); }
        .vital-card.spo2 input { background: rgba(8, 145, 178, 0.08); color: #0891B2; font-weight: 700; }

        /* BMI / SpO2 Category badges */
        .vital-bmi-category {
            font-size: 0.55rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 8px;
            display: inline-block;
            margin-top: 4px;
            background: var(--bg-card);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            align-self: flex-start;
        }

        .vital-bmi-category.normal       { background: #D1FAE5; color: #059669; border-color: #34D399; }
        .vital-bmi-category.underweight  { background: #FEF3C7; color: #D97706; border-color: #FBBF24; }
        .vital-bmi-category.overweight   { background: #FEF3C7; color: #D97706; border-color: #FBBF24; }
        .vital-bmi-category.obese        { background: #FEE2E2; color: #DC2626; border-color: #F87171; }
        .vital-bmi-category.spo2-normal  { background: #D1FAE5; color: #059669; border-color: #34D399; }
        .vital-bmi-category.spo2-low     { background: #FEF3C7; color: #D97706; border-color: #FBBF24; }
        .vital-bmi-category.spo2-critical{ background: #FEE2E2; color: #DC2626; border-color: #F87171; }

        [data-theme="dark"] .vital-bmi-category.normal        { background: #1A3A2A; color: #34D399; border-color: #34D399; }
        [data-theme="dark"] .vital-bmi-category.underweight   { background: #3D2E0A; color: #FBBF24; border-color: #FBBF24; }
        [data-theme="dark"] .vital-bmi-category.overweight    { background: #3D2E0A; color: #FBBF24; border-color: #FBBF24; }
        [data-theme="dark"] .vital-bmi-category.obese         { background: #3A1A1A; color: #F87171; border-color: #F87171; }
        [data-theme="dark"] .vital-bmi-category.spo2-normal   { background: #1A3A2A; color: #34D399; border-color: #34D399; }
        [data-theme="dark"] .vital-bmi-category.spo2-low      { background: #3D2E0A; color: #FBBF24; border-color: #FBBF24; }
        [data-theme="dark"] .vital-bmi-category.spo2-critical { background: #3A1A1A; color: #F87171; border-color: #F87171; }

        /* ---------- ALLERGY / SYMPTOM CHIPS ---------- */
        .allergy-chip,
        .symptom-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px 4px 8px;
            border-radius: 20px;
            border: 2px solid var(--border-color);
            background: var(--bg-body);
            cursor: pointer;
            font-size: 0.68rem;
            color: var(--text-secondary);
            user-select: none;
            font-weight: 600;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .allergy-chip:hover,
        .symptom-chip:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .allergy-chip.active {
            border-color: var(--danger);
            background: #FEE2E2;
            color: #DC2626;
        }

        [data-theme="dark"] .allergy-chip.active { background: #3A1A1A; color: #F87171; }

        .symptom-chip.active {
            border-color: var(--primary);
            background: #E8F0FE;
            color: #0B5ED7;
        }

        [data-theme="dark"] .symptom-chip.active { background: #1E3A5F; color: #6EA8FE; }

        /* ---------- VISIT INFO SECTION ---------- */
        .visit-info-section {
            margin-top: 22px;
            padding-top: 18px;
            border-top: 2px solid var(--border-color);
        }

        .visit-info-section .section-title {
            font-size: 0.95rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            color: var(--text-primary);
        }

        .visit-info-section .section-title i { color: #7C3AED; font-size: 1.1rem; }

        /* ---------- HELPER INFO ---------- */
        .helper-info {
            font-size: 0.62rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .helper-info.success { color: #059669; }
        .helper-info.purple  { color: #7C3AED; }

        /* ---------- FOOTER INFO ---------- */
        .footer-info {
            margin-top: 16px;
            padding-top: 12px;
            font-size: 0.62rem;
            color: var(--text-secondary);
            text-align: center;
            border-top: 1px solid var(--border-color);
            font-weight: 500;
        }

        .footer-info .separator { margin: 0 8px; opacity: 0.4; }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 1024px) {
            .form-card-modern { padding: 20px 22px; }
            .vital-grid-4 { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .form-card-modern { padding: 16px; border-radius: 16px; }
            .grid-2 { grid-template-columns: 1fr; gap: 12px; }
            .vital-grid-6 { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .vital-grid-4 { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .vital-card { padding: 11px; min-height: 96px; }
            .page-header { padding: 18px 20px; flex-direction: column; align-items: flex-start; }
            .page-header .page-title { font-size: 1.2rem; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; }
        }

        @media (max-width: 640px) {
            .form-card-modern { padding: 14px; }
            .vital-grid-6, .vital-grid-4 { grid-template-columns: 1fr; }
            .patient-avatar-circle { width: 48px; height: 48px; font-size: 1.2rem; }
            .patient-avatar-info h3 { font-size: 0.9rem; }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SHARED HEADER & SIDEBAR (INCLUDED ABOVE) -->
<!-- ================================================================ -->

<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-edit"></i>
                Edit Patient
                <span class="header-badge" style="text-transform:uppercase;">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-id-card"></i>
                <strong><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></strong>

                <span class="header-badge">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                </span>

                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>

                <span class="header-badge">
                    <i class="fas fa-calendar"></i> <?= date('d M Y') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_patient.php?id=<?= $patient_id ?>" class="btn-outline-light">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="patients.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ALERT MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="alert-message <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.1rem;margin-top:1px;"></i>
            <div style="flex:1;"><?= htmlspecialchars($message) ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FORM CARD -->
    <!-- ================================================================ -->
    <div class="form-card-modern animate-fade-in-up">

        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-user-edit"></i>
            </div>
            <div>
                <h3 class="form-title">Edit Patient Information</h3>
                <p class="form-subtitle">
                    Update the patient details below
                    <span style="color:#7C3AED;font-weight:700;margin-left:8px;">
                        <i class="fas fa-user-tie"></i> Editing as: <?= htmlspecialchars($user_full_name) ?>
                    </span>
                </p>
            </div>
        </div>

        <!-- Patient Avatar Display -->
        <div class="patient-avatar-display">
            <div class="patient-avatar-circle">
                <?= strtoupper(substr($patient['full_name'] ?? 'P', 0, 1)) ?>
            </div>
            <div class="patient-avatar-info">
                <h3><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></h3>
                <p>
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                    <?php if (!empty($patient['created_at'])): ?>
                        <span style="opacity:0.5;">•</span>
                        <i class="fas fa-calendar-plus"></i> Registered: <?= date('d M Y', strtotime($patient['created_at'])) ?>
                    <?php endif; ?>
                    <?php if (!empty($patient['registered_by_name'])): ?>
                        <span style="opacity:0.5;">•</span>
                        <i class="fas fa-user-tie"></i> By: <?= htmlspecialchars($patient['registered_by_name']) ?>
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
                        <option value="Male"   <?= ($patient['gender'] ?? '') === 'Male'   ? 'selected' : '' ?>>👨 Male</option>
                        <option value="Female" <?= ($patient['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>👩 Female</option>
                        <option value="Other"  <?= ($patient['gender'] ?? '') === 'Other'  ? 'selected' : '' ?>>⚧ Other</option>
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
                        <span class="helper-info success">(Optional)</span>
                    </label>
                    <input type="tel" name="phone" class="form-control"
                           placeholder="e.g. 0759 154 160"
                           value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
                </div>

                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-envelope label-icon"></i> Email
                        <span class="helper-info success">(Optional)</span>
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
                        <span class="helper-info">
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
                                        <?php if (!empty($doctor['specialty'])): ?> (<?= htmlspecialchars($doctor['specialty']) ?>)<?php endif; ?>
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
                                        <?php if (!empty($doctor['specialty'])): ?> (<?= htmlspecialchars($doctor['specialty']) ?>)<?php endif; ?>
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
                    <span class="helper-info">(Click to select)</span>
                </label>
                <div id="allergyCheckboxGroup" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
                    <?php
                    $patient_allergies = array_map('trim', explode(',', $patient['allergies'] ?? ''));
                    foreach ($common_allergies as $key => $label):
                        $is_checked = in_array($label, $patient_allergies);
                    ?>
                        <label class="allergy-chip <?= $is_checked ? 'active' : '' ?>" data-allergy="<?= htmlspecialchars($label) ?>">
                            <input type="checkbox" value="<?= htmlspecialchars($label) ?>" class="allergy-checkbox" style="display:none;" <?= $is_checked ? 'checked' : '' ?>>
                            <span>⚠️</span> <?= htmlspecialchars($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <textarea name="allergies" id="allergiesTextarea" class="form-control" style="margin-top:10px;" placeholder="List any known allergies..." rows="2"><?= htmlspecialchars($patient['allergies'] ?? '') ?></textarea>
            </div>

            <!-- ============================================================ -->
            <!-- VISIT INFO SECTION -->
            <!-- ============================================================ -->
            <?php if ($last_visit): ?>
            <div class="visit-info-section">
                <div class="section-title">
                    <i class="fas fa-notes-medical"></i>
                    Visit Information
                    <span class="helper-info purple">(Last: <?= htmlspecialchars($last_visit['visit_number'] ?? 'N/A') ?>)</span>
                </div>

                <div class="grid-2">
                    <div class="form-row">
                        <label class="form-label"><i class="fas fa-notes-medical label-icon"></i> Symptoms</label>
                        <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:6px;" id="symptomSelector">
                            <?php
                            $visit_symptoms = array_map('trim', explode(',', $last_visit['symptoms'] ?? ''));
                            foreach ($common_symptoms as $symptom):
                                $is_sel = in_array($symptom, $visit_symptoms);
                            ?>
                                <span class="symptom-chip <?= $is_sel ? 'active' : '' ?>"
                                      data-symptom="<?= htmlspecialchars($symptom) ?>"
                                      onclick="toggleSymptom(this)">
                                    <span>🩺</span> <?= htmlspecialchars($symptom) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <textarea name="symptoms" class="form-control" placeholder="Patient symptoms..." rows="2" id="symptomsTextarea" style="margin-top:10px;"><?= htmlspecialchars($last_visit['symptoms'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
                        <label class="form-label"><i class="fas fa-comment-medical label-icon"></i> Complaint / Reason</label>
                        <textarea name="complaint" class="form-control" placeholder="Main complaint..." rows="4"><?= htmlspecialchars($last_visit['complaint'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-row" style="margin-top:12px;">
                    <label class="form-label"><i class="fas fa-sticky-note label-icon"></i> Visit Notes</label>
                    <textarea name="notes" class="form-control" placeholder="Any additional notes..." rows="2"><?= htmlspecialchars($last_visit['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================================ -->
            <!-- VITAL SIGNS SECTION -->
            <!-- ============================================================ -->
            <div class="vital-signs-section">
                <div class="section-header">
                    <div class="section-title">
                        <i class="fas fa-heartbeat"></i>
                        Vital Signs
                        <span class="helper-info" style="font-weight:500;">(Update patient vitals)</span>
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
                            <span style="font-weight:800;color:var(--text-secondary);">/</span>
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

                <div style="margin-top:12px;">
                    <input type="text" name="vital_notes" class="form-control" placeholder="Vital signs notes (optional)"
                           value="<?= htmlspecialchars($vital_signs['notes'] ?? '') ?>">
                </div>
            </div>

            <input type="hidden" name="branch_id" value="<?= $selected_branch_id ?>">
            <input type="hidden" name="update_patient" value="1">

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="saveBtn">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="view_patient.php?id=<?= $patient_id ?>" class="btn btn-purple">
                    <i class="fas fa-eye"></i> View Patient
                </a>
                <a href="assign_doctor.php?patient_id=<?= $patient_id ?>&change=1" class="btn btn-outline">
                    <i class="fas fa-user-md"></i> Change Doctor
                </a>
                <a href="patients.php" class="btn btn-outline" style="margin-left:auto;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>

            <div class="footer-info">
                <i class="fas fa-info-circle"></i>
                Patient ID: <strong><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></strong>
                <span class="separator">|</span>
                <span style="color:#059669;">
                    <i class="fas fa-user-tie"></i> Editing as: <strong><?= htmlspecialchars($user_full_name) ?></strong>
                </span>
                <span class="separator">|</span>
                <span style="color:#0B5ED7;">
                    <i class="fas fa-heartbeat"></i> 7 Vital signs (with SpO₂)
                </span>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            Edit Patient
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($full_name) ?></strong>
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    var selectedBranchId = <?= (int)$selected_branch_id ?>;

    // ================================================================
    // BMI CALCULATION
    // ================================================================
    function calculateBMI() {
        var weight = parseFloat(document.getElementById('weightInput')?.value);
        var height = parseFloat(document.getElementById('heightInput')?.value);
        var output = document.getElementById('bmiOutput');
        var category = document.getElementById('bmiCategory');

        if (weight && height && height > 0) {
            var bmi = Math.round((weight / ((height / 100) * (height / 100))) * 10) / 10;
            if (output) output.value = bmi;

            var cat = '', cls = '';
            if (bmi < 18.5)      { cat = 'Underweight'; cls = 'underweight'; }
            else if (bmi < 25)   { cat = 'Normal';      cls = 'normal'; }
            else if (bmi < 30)   { cat = 'Overweight';  cls = 'overweight'; }
            else                 { cat = 'Obese';       cls = 'obese'; }

            if (category) {
                category.textContent = cat;
                category.className = 'vital-bmi-category ' + cls;
            }
        } else if (output) {
            output.value = '';
            if (category) {
                category.textContent = 'Auto';
                category.className = 'vital-bmi-category';
            }
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
        if (spo2 >= 95)      { cat = 'Normal';   cls = 'spo2-normal'; }
        else if (spo2 >= 90) { cat = 'Low';      cls = 'spo2-low'; }
        else                 { cat = 'Critical'; cls = 'spo2-critical'; }

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
                chip.classList.add('active');
                if (cb) cb.checked = true;
            } else {
                chip.classList.remove('active');
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
            el.classList.remove('active');
        } else {
            list.push(symptom);
            el.classList.add('active');
        }
        textarea.value = list.join(', ');
    }

    // ================================================================
    // AJAX: DOCTOR STATUS
    // ================================================================
    var doctorInterval = null;
    function fetchDoctorStatus() {
        var fd = new FormData();
        fd.append('action', 'get_doctor_status');
        fd.append('branch_id', selectedBranchId);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(function(data) {
                if (data.success) {
                    var sel = document.getElementById('doctorSelect');
                    if (sel && data.doctor_options) {
                        var current = sel.value;
                        sel.innerHTML = data.doctor_options;
                        if (current) sel.value = current;
                    }
                }
            })
            .catch(function() {});
    }

    function startDoctorUpdate() {
        if (doctorInterval) clearInterval(doctorInterval);
        setTimeout(fetchDoctorStatus, 1500);
        doctorInterval = setInterval(fetchDoctorStatus, 5000);
    }

    // ================================================================
    // CLOCK
    // ================================================================
    function updateClock() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ft = document.getElementById('footerTimestamp');
        if (ft) ft.textContent = 'Last updated: ' + timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ================================================================
    // TOAST (fallback if not defined by header)
    // ================================================================
    if (typeof window.showToast !== 'function') {
        window.showToast = function(title, message, type) {
            var toast = document.getElementById('toast');
            if (!toast) return;
            var t = document.getElementById('toastTitle');
            var m = document.getElementById('toastMessage');
            toast.className = 'toast-custom ' + (type || 'info');
            t.textContent = title;
            m.textContent = message;
            toast.style.display = 'flex';
            toast.classList.add('show');
            clearTimeout(toast.timeout);
            toast.timeout = setTimeout(function() {
                toast.classList.remove('show');
                setTimeout(() => toast.style.display = 'none', 400);
            }, 3500);
        };
    }

    // ================================================================
    // FORM SUBMIT VALIDATION
    // ================================================================
    document.getElementById('editPatientForm')?.addEventListener('submit', function(e) {
        var name = document.querySelector('input[name="full_name"]').value.trim();
        var gender = document.querySelector('select[name="gender"]').value;

        if (!name)   { e.preventDefault(); showToast('Error', 'Please enter patient full name', 'error'); return false; }
        if (!gender) { e.preventDefault(); showToast('Error', 'Please select gender', 'error'); return false; }

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
        startDoctorUpdate();

        document.getElementById('spo2Input')?.addEventListener('input', calculateSpO2Category);

        console.log('%c👤 Braick - Edit Patient V14 (SHARED HEADER)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
        console.log('%c✅ Using shared reception_header.php & reception_sidebar.php', 'font-size:13px; color:#059669; font-weight:bold;');
        console.log('%c✅ 7 Vital Signs + SpO2 + Allergy/Symptom chips', 'font-size:13px; color:#7C3AED;');
    });
</script>

</body>
</html>