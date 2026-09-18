<?php
// ================================================================
// FILE: frontend/pages/reception/edit_patient.php
// RECEPTION - EDIT PATIENT (V13 - COMPACT HEADER)
// ✅ Header ndogo: 56px (badala ya 68px)
// ✅ Search bar ndogo: max 300px (badala ya 500px)
// ✅ Columns zote ni zile zilizopo kwenye DB
// ✅ 7 Vital Signs cards (with SpO2)
// ✅ Allergies + Symptoms chips
// ================================================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$allowed_roles = ['reception', 'cashier', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Receptionist';
$user_role = $_SESSION['role'] ?? 'reception';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? 'reception';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$full_name = $user_full_name;
$user_branch_id = $branch_id;
$selected_branch_id = $branch_id;
$is_admin = ($user_role === 'admin');
$message = '';
$message_type = '';

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
$patient = null;
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
    $stmt->execute([$selected_branch_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($doctors as $doc) {
        if ($doc['is_online'] == 1) { $online_doctors[] = $doc; $online_doctors_count++; }
        else { $offline_doctors[] = $doc; $offline_doctors_count++; }
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
        else { $offline[] = $doc; $offline_count++; }
    }
    
    $options_html = '<option value="">-- Select Doctor --</option>';
    
    if ($online_count > 0) {
        $options_html .= '<optgroup label="🟢 Online Doctors (' . $online_count . ')" style="font-weight:600;color:#059669;">';
        foreach ($online as $doc) {
            $options_html .= '<option value="' . $doc['id'] . '" data-online="1" style="color:#059669;">';
            $options_html .= '🟢 Dr. ' . htmlspecialchars($doc['full_name']);
            if (!empty($doc['specialty'])) $options_html .= ' (' . htmlspecialchars($doc['specialty']) . ')';
            $options_html .= '</option>';
        }
        $options_html .= '</optgroup>';
    }
    
    if ($offline_count > 0) {
        $options_html .= '<optgroup label="⚪ Offline Doctors (' . $offline_count . ')" style="font-weight:600;color:var(--text-secondary);">';
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
        'success' => true,
        'online_count' => $online_count,
        'offline_count' => $offline_count,
        'total_doctors' => count($doctors_list),
        'doctor_options' => $options_html,
        'timestamp' => date('H:i:s')
    ]);
    exit;
}

// HANDLE UPDATE
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
        
        // Update last visit (symptoms, complaint, notes)
        if ($last_visit && isset($last_visit['id'])) {
            $symptoms = trim($_POST['symptoms'] ?? '');
            $complaint = trim($_POST['complaint'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            $stmt = $db->prepare("UPDATE visits SET symptoms = ?, complaint = ?, notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$symptoms, $complaint, $notes, $last_visit['id']]);
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
        
        $message = "✅ Patient updated successfully!";
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

$profile_pic_url = !empty($profile_pic) ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$dark_mode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] : 'false';

include_once __DIR__ . '/../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $dark_mode === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3D2E0A;
            --purple-bg: #2A1A3A;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ✅ COMPACT TOP NAV - 56px height */
        .top-nav {
            position: fixed;
            top: 0; left: 270px; right: 0;
            height: 56px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        /* ✅ COMPACT SEARCH - max 300px */
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 8px;
            border: 2px solid var(--border-color);
            flex: 1;
            max-width: 300px;
            height: 36px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: #0B5ED7;
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 6px 10px;
            width: 100%;
            font-size: 0.78rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            font-size: 0.72rem;
        }
        
        .top-nav .search-wrapper .search-btn {
            background: #0B5ED7;
            color: white;
            border: none;
            padding: 0 12px;
            height: 100%;
            border-radius: 0 6px 6px 0;
            cursor: pointer;
            font-size: 0.72rem;
            white-space: nowrap;
        }
        
        .top-nav .branch-badge {
            background: var(--success-bg);
            color: var(--success);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .top-nav .datetime {
            font-size: 0.68rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .top-nav .avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
        }
        
        .top-nav .icon-btn {
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
            font-size: 0.85rem;
        }
        
        .notif-dot {
            position: absolute;
            top: 5px; right: 5px;
            width: 7px; height: 7px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
        }
        
        .notif-dot.has-notif { background: #EF4444; }
        .notif-dot.no-notif { background: #94A3B8; }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 4px 10px;
            cursor: pointer;
            font-size: 0.72rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 4px;
            height: 32px;
        }
        
        /* ✅ MAIN CONTENT - Top adjusted for 56px nav */
        .main-content {
            margin-left: 270px;
            margin-top: 56px;
            padding: 20px 24px;
            min-height: calc(100vh - 56px);
        }
        
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: var(--radius-lg);
            padding: 16px 24px;
            margin-bottom: 18px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.58rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 3px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.68rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            color: white;
        }
        
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
        
        .form-header .form-title {
            font-size: 1rem;
            font-weight: 700;
        }
        
        .form-header .form-subtitle {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
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
            width: 52px;
            height: 52px;
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
        
        .patient-avatar-info h3 {
            font-size: 0.92rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 3px 0;
        }
        
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
            padding: 8px 18px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            border: none;
            text-decoration: none;
            min-height: 38px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), #083C8A);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-purple {
            background: linear-gradient(135deg, #7C3AED, #5B21B6);
            color: white;
        }
        
        .btn-purple:hover { color: white; }
        
        /* Vital Cards */
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
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .vital-signs-section .section-title i { color: #DC2626; }
        
        .vital-signs-section .section-badge {
            font-size: 0.58rem;
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
            font-size: 0.58rem;
            font-weight: 700;
            display: block;
            text-transform: uppercase;
        }
        
        .vital-card .vital-sublabel {
            font-size: 0.48rem;
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
        }
        
        .vital-card .vital-input-wrap input:focus {
            border-color: var(--primary);
        }
        
        .vital-card .vital-unit {
            font-size: 0.52rem;
            color: var(--text-secondary);
            font-weight: 600;
            padding: 2px 5px;
            background: var(--gray-200);
            border-radius: 5px;
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
            font-size: 0.48rem;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 6px;
            display: inline-block;
            margin-top: 3px;
            background: var(--gray-200);
            color: var(--text-secondary);
        }
        
        .vital-bmi-category.normal { background: rgba(5, 150, 105, 0.15); color: #059669; }
        .vital-bmi-category.underweight { background: rgba(217, 119, 6, 0.15); color: #D97706; }
        .vital-bmi-category.overweight { background: rgba(217, 119, 6, 0.15); color: #D97706; }
        .vital-bmi-category.obese { background: rgba(220, 38, 38, 0.15); color: #DC2626; }
        .vital-bmi-category.spo2-normal { background: rgba(5, 150, 105, 0.15); color: #059669; }
        .vital-bmi-category.spo2-low { background: rgba(217, 119, 6, 0.15); color: #D97706; }
        .vital-bmi-category.spo2-critical { background: rgba(220, 38, 38, 0.3); color: #DC2626; font-weight: 700; }
        
        .toast-custom {
            position: fixed;
            bottom: 24px; right: 24px;
            padding: 14px 20px;
            border-radius: var(--radius);
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s;
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 14px; }
            .form-card-modern { padding: 18px; }
            .vital-grid-4 { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .form-card-modern { padding: 12px; }
            .grid-2 { grid-template-columns: 1fr; }
            .vital-grid-6, .vital-grid-4 { grid-template-columns: repeat(2, 1fr); gap: 6px; }
            .vital-card { padding: 9px; min-height: 88px; }
            .page-header { flex-direction: column; align-items: flex-start; padding: 14px 18px; }
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .dark-toggle-btn span { display: none; }
        }
    </style>
</head>
<body>

<!-- ✅ COMPACT TOP NAV -->
<nav class="top-nav">
    <div style="display:flex;align-items:center;gap:12px;flex:1;">
        <button id="sidebarToggle" style="background:transparent;border:none;color:var(--text-secondary);cursor:pointer;display:none;">
            <i class="fas fa-bars" style="font-size:1.1rem;"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search" style="color:var(--text-secondary);margin-left:10px;font-size:0.75rem;"></i>
            <input type="text" id="searchInput" placeholder="Search patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>
    
    <div style="display:flex;align-items:center;gap:10px;">
        <span class="branch-badge">
            <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime">
            <i class="fas fa-clock"></i>
            <span id="clockDisplay"><?= date('d M • h:i A') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar">
        </a>
    </div>
</nav>

<main class="main-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-edit"></i>
                Edit Patient
                <span class="header-badge" style="background:rgba(255,255,255,0.2);text-transform:uppercase;font-weight:600;">
                    RECEPTION
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-id-card"></i>
                <strong><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i> <strong><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></strong>
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <strong><?= htmlspecialchars($branch_name) ?></strong>
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
        <div style="max-width:1200px;margin:0 auto 14px;padding:12px 16px;border-radius:var(--radius);background:<?= $message_type === 'success' ? 'var(--success-bg)' : 'var(--danger-bg)' ?>;color:<?= $message_type === 'success' ? 'var(--success-dark)' : 'var(--danger-dark)' ?>;border:1px solid <?= $message_type === 'success' ? 'var(--success)' : 'var(--danger)' ?>;display:flex;align-items:flex-start;gap:10px;font-size:0.8rem;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1rem;margin-top:2px;"></i>
            <div style="flex:1;"><?= $message ?></div>
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
                    <span style="color:var(--purple);font-weight:600;margin-left:6px;">
                        <i class="fas fa-user-tie"></i> Editing as: <?= htmlspecialchars($user_full_name) ?>
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
                    <?php if (!empty($patient['registered_by_name'])): ?>
                        <span style="color:var(--text-secondary);">•</span>
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
                        <span style="font-size:0.58rem;color:var(--success);">(Optional)</span>
                    </label>
                    <input type="tel" name="phone" class="form-control" 
                           placeholder="e.g. 0759 154 160" 
                           value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-envelope label-icon"></i> Email
                        <span style="font-size:0.58rem;color:var(--success);">(Optional)</span>
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
                               style="display:inline-flex;align-items:center;gap:3px;padding:2px 10px 2px 7px;border-radius:20px;border:2px solid <?= $is_checked ? 'var(--danger)' : 'var(--border-color)' ?>;background:<?= $is_checked ? 'var(--danger-bg)' : 'var(--bg-body)' ?>;cursor:pointer;font-size:0.65rem;color:<?= $is_checked ? 'var(--danger-dark)' : 'var(--text-secondary)' ?>;user-select:none;">
                            <input type="checkbox" value="<?= htmlspecialchars($label) ?>" class="allergy-checkbox" style="display:none;" <?= $is_checked ? 'checked' : '' ?>>
                            <span>⚠️</span> <?= htmlspecialchars($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <textarea name="allergies" id="allergiesTextarea" class="form-control" style="margin-top:8px;" placeholder="List any known allergies..." rows="2"><?= htmlspecialchars($patient['allergies'] ?? '') ?></textarea>
            </div>
            
            <?php if ($last_visit): ?>
            <div style="margin-top:18px;padding-top:14px;border-top:2px solid var(--border-color);">
                <div style="font-size:0.8rem;font-weight:700;display:flex;align-items:center;gap:8px;margin-bottom:12px;">
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
                        <textarea name="symptoms" class="form-control" placeholder="Patient symptoms..." rows="2" id="symptomsTextarea" style="font-size:0.68rem;margin-top:8px;"><?= htmlspecialchars($last_visit['symptoms'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label"><i class="fas fa-comment-medical label-icon"></i> Complaint / Reason</label>
                        <textarea name="complaint" class="form-control" placeholder="Main complaint..." rows="2" style="font-size:0.68rem;"><?= htmlspecialchars($last_visit['complaint'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="form-row" style="margin-top:10px;">
                    <label class="form-label"><i class="fas fa-sticky-note label-icon"></i> Visit Notes</label>
                    <textarea name="notes" class="form-control" placeholder="Any additional notes..." rows="2" style="font-size:0.68rem;"><?= htmlspecialchars($last_visit['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="vital-signs-section">
                <div class="section-header">
                    <div class="section-title">
                        <i class="fas fa-heartbeat"></i>
                        Vital Signs
                        <span style="font-size:0.65rem;font-weight:400;color:var(--text-secondary);">(Update patient vitals)</span>
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
                           style="font-size:0.68rem;padding:6px 10px;">
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
            
            <div style="margin-top:14px;padding-top:10px;font-size:0.58rem;color:var(--text-secondary);text-align:center;border-top:1px solid var(--border-color);">
                <i class="fas fa-info-circle"></i>
                Patient ID: <strong><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></strong>
                <span style="margin:0 6px;">|</span>
                <span style="color:var(--success);"><i class="fas fa-user-tie"></i> Editing as: <strong><?= htmlspecialchars($user_full_name) ?></strong></span>
                <span style="margin:0 6px;">|</span>
                <span style="color:var(--primary);"><i class="fas fa-heartbeat"></i> 7 Vital signs (with SpO₂)</span>
            </div>
        </form>
    </div>

    <footer style="padding:12px 0;border-top:1px solid var(--border-color);margin-top:20px;text-align:center;font-size:0.62rem;color:var(--text-secondary);">
        <p>
            <span style="color:var(--primary);font-weight:600;">Braick Dispensary</span> Management System
            <span style="margin:0 6px;">|</span>
            Edit Patient
            <span style="margin:0 6px;">|</span>
            Logged in as: <strong><?= htmlspecialchars($full_name) ?></strong>
            <span style="margin:0 6px;">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
        </p>
    </footer>

</main>

<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    var selectedBranchId = <?= (int)$selected_branch_id ?>;

    // DARK MODE
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    if (localStorage.getItem('darkMode') === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        if (darkIcon) darkIcon.className = 'fas fa-sun';
        if (darkText) darkText.textContent = 'Light';
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
                chip.style.color = 'var(--danger-dark)';
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
            });
    }
    
    function startDoctorUpdate() {
        if (doctorInterval) clearInterval(doctorInterval);
        setTimeout(fetchDoctorStatus, 1500);
        doctorInterval = setInterval(fetchDoctorStatus, 5000);
    }

    function updateDateTime() {
        var now = new Date();
        var d = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        var t = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
        var el = document.getElementById('clockDisplay');
        if (el) el.textContent = d + ' • ' + t;
        var ft = document.getElementById('footerTimestamp');
        if (ft) ft.textContent = 'Last updated: ' + t + ':00';
    }
    setInterval(updateDateTime, 60000);
    updateDateTime();

    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var t = document.getElementById('toastTitle');
        var m = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        t.textContent = title;
        m.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(() => toast.style.display = 'none', 400);
        }, 3500);
    }

    document.getElementById('editPatientForm')?.addEventListener('submit', function(e) {
        var name = document.querySelector('input[name="full_name"]').value.trim();
        var gender = document.querySelector('select[name="gender"]').value;
        
        if (!name) { e.preventDefault(); showToast('Error', 'Please enter patient full name', 'error'); return false; }
        if (!gender) { e.preventDefault(); showToast('Error', 'Please select gender', 'error'); return false; }
        
        var btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    });

    document.addEventListener('DOMContentLoaded', function() {
        calculateBMI();
        calculateSpO2Category();
        startDoctorUpdate();
        
        document.getElementById('spo2Input')?.addEventListener('input', calculateSpO2Category);
        
        var searchBtn = document.getElementById('searchBtn');
        var searchInput = document.getElementById('searchInput');
        function doSearch() {
            var q = searchInput.value.trim();
            if (q) window.location.href = 'patients.php?search=' + encodeURIComponent(q);
        }
        searchBtn?.addEventListener('click', doSearch);
        searchInput?.addEventListener('keypress', e => { if (e.key === 'Enter') doSearch(); });
        
        console.log('%c👤 Braick - Edit Patient V13 (COMPACT)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
        console.log('%c✅ Header: 56px (badala ya 68px)', 'font-size:13px; color:#059669; font-weight:bold;');
        console.log('%c✅ Search bar: 300px (badala ya 500px)', 'font-size:13px; color:#34D399;');
    });
</script>

</body>
</html>