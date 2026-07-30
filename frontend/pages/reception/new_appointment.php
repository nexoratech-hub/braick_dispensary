<?php
// ================================================================
// FILE: frontend/pages/reception/new_appointment.php
// RECEPTION - NEW APPOINTMENT WITH 6 VITAL SIGNS
// WITH AJAX AUTO-UPDATE FOR DOCTOR STATUS (EVERY 3 SECONDS)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Rose Mwangi (Reception)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['is_admin'] = false;
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$message = '';
$message_type = '';

try {
    $db = getDB();
    
    // Get patients in this branch
    $stmt = $db->prepare("SELECT id, full_name, patient_id FROM patients WHERE branch_id = ? ORDER BY full_name");
    $stmt->execute([$selected_branch_id]);
    $patients = $stmt->fetchAll();
    
    // Get doctors in this branch with status
    $stmt = $db->prepare("SELECT id, full_name, specialty, is_online, profile_pic FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ? ORDER BY is_online DESC, full_name");
    $stmt->execute([$selected_branch_id]);
    $doctors = $stmt->fetchAll();
    
    // Get online doctors count
    $online_doctors = 0;
    $total_doctors = count($doctors);
    
    foreach ($doctors as $doc) {
        if ($doc['is_online'] == 1) {
            $online_doctors++;
        }
    }
    
    // ================================================================
    // GET LATEST VITAL SIGNS FOR SELECTED PATIENT (6 signs only)
    // ================================================================
    $latest_vital_signs = null;
    if ($patient_id > 0) {
        $stmt = $db->prepare("
            SELECT temperature, blood_pressure_systolic, blood_pressure_diastolic,
                   pulse_rate, weight, height, bmi, notes, recorded_at
            FROM vital_signs 
            WHERE patient_id = ? 
            ORDER BY recorded_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$patient_id]);
        $latest_vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // HANDLE FORM SUBMISSION
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $patient_id = (int)($_POST['patient_id'] ?? 0);
        $doctor_id = (int)($_POST['doctor_id'] ?? 0);
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_hour = $_POST['appointment_hour'] ?? '09';
        $appointment_minute = $_POST['appointment_minute'] ?? '00';
        $appointment_ampm = $_POST['appointment_ampm'] ?? 'AM';
        $purpose = trim($_POST['purpose'] ?? '');
        $status = $_POST['status'] ?? 'scheduled';
        $visit_type = $_POST['visit_type'] ?? 'new';
        
        // 6 Vital Signs
        $temperature = $_POST['temperature'] ?? null;
        $bp_systolic = $_POST['bp_systolic'] ?? null;
        $bp_diastolic = $_POST['bp_diastolic'] ?? null;
        $pulse_rate = $_POST['pulse_rate'] ?? null;
        $weight = $_POST['weight'] ?? null;
        $height = $_POST['height'] ?? null;
        $vital_notes = trim($_POST['vital_notes'] ?? '');
        
        // Calculate BMI
        $bmi = null;
        if ($weight && $height && $height > 0) {
            $height_m = $height / 100;
            $bmi = round($weight / ($height_m * $height_m), 1);
        }
        
        $errors = [];
        if ($patient_id <= 0) $errors[] = 'Please select a patient';
        if ($doctor_id <= 0) $errors[] = 'Please select a doctor';
        if (empty($appointment_date)) $errors[] = 'Please select a date';
        if (empty($appointment_hour)) $errors[] = 'Please select an hour';
        if (empty($appointment_ampm)) $errors[] = 'Please select AM/PM';
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Build time string from hour, minute, ampm
                $hour_12 = (int)$appointment_hour;
                if ($appointment_ampm == 'PM' && $hour_12 != 12) {
                    $hour_24 = $hour_12 + 12;
                } elseif ($appointment_ampm == 'AM' && $hour_12 == 12) {
                    $hour_24 = 0;
                } else {
                    $hour_24 = $hour_12;
                }
                
                $time_str = str_pad($hour_24, 2, '0', STR_PAD_LEFT) . ':' . $appointment_minute . ':00';
                $datetime = $appointment_date . ' ' . $time_str;
                
                // Insert appointment
                $stmt = $db->prepare("
                    INSERT INTO appointments (patient_id, doctor_id, appointment_date, purpose, status, branch_id, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$patient_id, $doctor_id, $datetime, $purpose, $status, $selected_branch_id, $_SESSION['user_id']]);
                $appointment_id = $db->lastInsertId();
                
                // ================================================================
                // INSERT VITAL SIGNS (6 signs only)
                // ================================================================
                $has_vital = $temperature !== null && $temperature !== '' || 
                             $bp_systolic !== null && $bp_systolic !== '' || 
                             $bp_diastolic !== null && $bp_diastolic !== '' || 
                             $pulse_rate !== null && $pulse_rate !== '' || 
                             $weight !== null && $weight !== '' || 
                             $height !== null && $height !== '';
                
                if ($has_vital) {
                    $stmt = $db->prepare("
                        INSERT INTO vital_signs (
                            patient_id, appointment_id, recorded_by, branch_id,
                            temperature, blood_pressure_systolic, blood_pressure_diastolic,
                            pulse_rate, weight, height, bmi, notes, recorded_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $patient_id,
                        $appointment_id,
                        $_SESSION['user_id'],
                        $selected_branch_id,
                        $temperature ?: null,
                        $bp_systolic ?: null,
                        $bp_diastolic ?: null,
                        $pulse_rate ?: null,
                        $weight ?: null,
                        $height ?: null,
                        $bmi,
                        $vital_notes ?: null
                    ]);
                }
                
                // Log activity
                try {
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'appointment_created', ?, NOW())");
                    $stmt->execute([$_SESSION['user_id'], "New appointment created for patient ID: $patient_id with doctor ID: $doctor_id"]);
                } catch (Exception $e) {}
                
                $db->commit();
                
                $message = "✅ Appointment scheduled successfully!";
                $message_type = 'success';
                
                echo '<script>
                    showToast("✅ Success", "Appointment scheduled successfully!", "success");
                    setTimeout(function(){ 
                        window.location.href = "appointments.php?date=' . $appointment_date . '"; 
                    }, 2000);
                </script>';
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
            
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $patients = [];
    $doctors = [];
    $online_doctors = 0;
    $total_doctors = 0;
    $latest_vital_signs = null;
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Appointment - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
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
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --white: #FFFFFF;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
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
            --table-stripe: #1E293B;
            --table-hover: #1A3A2A;
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
        
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .header-badge .online-count {
            color: #34D399;
            font-weight: 700;
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .update-badge-light {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.8);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        .form-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 32px 36px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 950px;
            margin: 0 auto;
            box-shadow: var(--shadow-md);
        }
        
        .form-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
        }
        
        .form-card .form-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .form-card .form-header .form-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .form-card .form-header .form-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .form-card .form-header .form-subtitle {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: block;
        }
        
        .form-label .required {
            color: var(--danger);
            margin-left: 2px;
        }
        
        .form-label .label-icon {
            margin-right: 4px;
            color: var(--primary);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.08);
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        .form-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .form-row {
            margin-bottom: 18px;
        }
        
        .form-row:last-child {
            margin-bottom: 0;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        select.form-control {
            appearance: auto;
            cursor: pointer;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 60px;
        }
        
        .vital-signs-section {
            background: var(--bg-body);
            border-radius: 14px;
            padding: 20px 24px;
            margin: 20px 0;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .vital-signs-section:hover {
            border-color: var(--primary);
        }
        
        .vital-signs-section .vital-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        
        .vital-signs-section .vital-title i {
            color: #DC2626;
            font-size: 1.2rem;
        }
        
        .vital-signs-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        
        .vital-sign-item {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .vital-sign-item:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.06);
        }
        
        .vital-sign-item .vital-label {
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }
        
        .vital-sign-item .vital-input {
            border: none;
            background: transparent;
            padding: 4px 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            outline: none;
            width: 100%;
        }
        
        .vital-sign-item .vital-input:focus {
            color: var(--primary);
        }
        
        .vital-sign-item .vital-input::placeholder {
            color: var(--text-secondary);
            opacity: 0.4;
            font-weight: 400;
        }
        
        .vital-sign-item .vital-unit {
            font-size: 0.55rem;
            color: var(--text-secondary);
            display: block;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.35);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
        
        .role-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
        }
        
        [data-theme="dark"] .role-badge-display {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge-display {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .stat-card .stat-number.primary { color: var(--primary); }
        .stat-card .stat-number.green { color: var(--success); }
        .stat-card .stat-number.purple { color: #7C3AED; }
        .stat-card .stat-number.red { color: var(--danger); }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .stat-card .stat-icon {
            font-size: 1.4rem;
            margin-bottom: 4px;
        }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        
        .grid-full {
            grid-column: 1 / -1;
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .form-card { padding: 20px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .form-card { padding: 14px; }
            .form-card .form-header .form-title { font-size: 1rem; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .vital-signs-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .form-card { padding: 12px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .stat-card .stat-number { font-size: 1.4rem; }
            .vital-signs-grid {
                grid-template-columns: 1fr 1fr;
            }
            .vital-signs-section {
                padding: 12px 14px;
            }
            .grid-2 {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .alert-success {
            background: var(--success-bg);
            color: var(--success-dark);
            border: 1px solid var(--success);
        }
        
        .alert-error {
            background: var(--danger-bg);
            color: var(--danger-dark);
            border: 1px solid var(--danger);
        }
        
        .alert i {
            font-size: 1.1rem;
            margin-top: 2px;
        }
        
        .alert .alert-content {
            flex: 1;
        }

        /* Time select styling - can type or select */
        .time-select-group {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .time-select-group .time-input {
            flex: 1;
            min-width: 60px;
            max-width: 80px;
            text-align: center;
            padding: 10px 8px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
        }
        
        .time-select-group .time-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.08);
        }
        
        .time-select-group .time-input::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        .time-select-group .time-separator {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-secondary);
            padding: 0 2px;
        }
        
        .time-select-group .ampm-select {
            flex: 0.6;
            min-width: 80px;
            max-width: 100px;
        }
        
        .time-select-group .time-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        @media (max-width: 640px) {
            .time-select-group {
                flex-wrap: wrap;
            }
            .time-select-group .time-input {
                flex: 1 1 30%;
                min-width: 50px;
            }
            .time-select-group .ampm-select {
                flex: 1 1 100%;
                max-width: 100%;
            }
        }

        /* Doctor status info - simple count only */
        .doctor-status-info {
            background: var(--bg-body);
            border-radius: 10px;
            padding: 10px 16px;
            margin-top: 8px;
            border: 1px solid var(--border-color);
            font-size: 0.8rem;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .doctor-status-info .online-doctors {
            color: var(--success);
            font-weight: 600;
        }
        
        .doctor-status-info .offline-doctors {
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .doctor-status-info .status-icon {
            margin-right: 4px;
        }
        
        .doctor-status-info .status-count {
            font-weight: 700;
        }
    </style>
</head>
<body>

<!-- TOP NAVIGATION -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- MAIN CONTENT -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-calendar-plus"></i>
                New Appointment
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">RECEPTION</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Schedule a new appointment with <strong>6 Vital Signs</strong> in <?= htmlspecialchars($branch_name) ?>
                
                <span class="header-badge" id="onlineDoctorBadge">
                    <i class="fas fa-user-md"></i>
                    <span class="online-count" id="onlineDoctorCount"><?= $online_doctors ?></span> Online
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-user-md"></i>
                    <?= $total_doctors ?> Total Doctors
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-users"></i>
                    <?= count($patients) ?> Patients
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="appointments.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Appointments
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>" style="max-width:950px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div class="alert-content"><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- APPOINTMENT FORM -->
    <div class="form-card animate-fade-in-up">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-calendar-plus"></i>
            </div>
            <div>
                <h3 class="form-title">Schedule New Appointment</h3>
                <p class="form-subtitle">Fill in the details below to schedule an appointment with 6 vital signs</p>
            </div>
        </div>
        
        <form method="POST" action="" id="appointmentForm">
            <!-- ROW 1: Patient + Doctor -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-user label-icon"></i> Patient <span class="required">*</span>
                    </label>
                    <select name="patient_id" class="form-control" required id="patientSelect">
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= $patient['id'] ?>" <?= $patient_id == $patient['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($patient['full_name']) ?> (<?= htmlspecialchars($patient['patient_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($patients)): ?>
                        <p class="text-xs text-yellow-500 mt-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i> 
                            No patients registered. <a href="new_patient.php" class="text-primary hover:underline">Register a patient</a>
                        </p>
                    <?php else: ?>
                        <p class="text-xs text-gray-400 mt-1">
                            <i class="fas fa-info-circle mr-1"></i> 
                            <?= count($patients) ?> patient(s) available
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-user-md label-icon"></i> Doctor <span class="required">*</span>
                    </label>
                    <select name="doctor_id" class="form-control" required id="doctorSelect">
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?= $doctor['id'] ?>" data-online="<?= $doctor['is_online'] ?>" data-name="<?= htmlspecialchars($doctor['full_name']) ?>" data-specialty="<?= htmlspecialchars($doctor['specialty'] ?? '') ?>">
                                Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                <?php if (!empty($doctor['specialty'])): ?>
                                    (<?= htmlspecialchars($doctor['specialty']) ?>)
                                <?php endif; ?>
                                <?php if ($doctor['is_online'] == 1): ?>
                                    🟢 Online
                                <?php else: ?>
                                    ⚪ Offline
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($doctors)): ?>
                        <p class="text-xs text-red-500 mt-1">
                            <i class="fas fa-exclamation-circle mr-1"></i> 
                            No doctors available.
                        </p>
                    <?php else: ?>
                        <p class="text-xs text-gray-400 mt-1" id="doctorStatusText">
                            <i class="fas fa-info-circle mr-1"></i> 
                            <?= $total_doctors ?> doctor(s) available 
                            <span class="text-green-500" id="onlineDoctorsText">(<?= $online_doctors ?> online)</span>
                        </p>
                        
                        <!-- DOCTOR STATUS INFO - Simple counts only -->
                        <div class="doctor-status-info" id="doctorStatusInfo">
                            <span class="online-doctors">
                                <i class="fas fa-circle status-icon" style="color:#059669;font-size:0.5rem;"></i>
                                Online: <span class="status-count" id="onlineCountDisplay"><?= $online_doctors ?></span>
                            </span>
                            <span class="offline-doctors">
                                <i class="fas fa-circle status-icon" style="color:#94A3B8;font-size:0.5rem;"></i>
                                Offline: <span class="status-count" id="offlineCountDisplay"><?= $total_doctors - $online_doctors ?></span>
                            </span>
                            <span class="text-gray-400" style="font-weight:400;">
                                Total: <?= $total_doctors ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ROW 2: Date + Time (12 HOUR FORMAT with manual input) -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-calendar-day label-icon"></i> Date <span class="required">*</span>
                    </label>
                    <input type="date" name="appointment_date" class="form-control" 
                           value="<?= date('Y-m-d') ?>" required>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-clock label-icon"></i> Time <span class="required">*</span>
                    </label>
                    <div class="time-select-group">
                        <!-- Hour Input (1-12) - Can type or select -->
                        <input type="number" name="appointment_hour" 
                               class="time-input" id="hourInput" 
                               value="09" min="1" max="12" 
                               placeholder="HH" required>
                        <span class="time-label">Hour</span>
                        
                        <span class="time-separator">:</span>
                        
                        <!-- Minute Input (00-59) - Can type or select -->
                        <input type="number" name="appointment_minute" 
                               class="time-input" id="minuteInput" 
                               value="00" min="0" max="59" 
                               placeholder="MM" required>
                        <span class="time-label">Min</span>
                        
                        <!-- AM/PM Select -->
                        <select name="appointment_ampm" class="form-control ampm-select" id="ampmSelect" required>
                            <option value="AM" selected>AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                        <i class="fas fa-info-circle mr-1"></i> 
                        Enter hour (1-12) and minutes (00-59) or use the dropdowns
                    </p>
                </div>
            </div>
            
            <!-- ROW 3: Visit Type + Status -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-tag label-icon"></i> Visit Type
                    </label>
                    <select name="visit_type" class="form-control">
                        <option value="new">🆕 New Patient</option>
                        <option value="follow-up">🔄 Follow-up</option>
                        <option value="emergency">🚨 Emergency</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-flag label-icon"></i> Status
                    </label>
                    <select name="status" class="form-control">
                        <option value="scheduled">📅 Scheduled</option>
                        <option value="confirmed">✅ Confirmed</option>
                        <option value="pending">⏳ Pending</option>
                    </select>
                </div>
            </div>
            
            <!-- ROW 4: Purpose (Full Width) -->
            <div class="form-row grid-full">
                <label class="form-label">
                    <i class="fas fa-notes-medical label-icon"></i> Purpose
                </label>
                <textarea name="purpose" class="form-control" placeholder="Reason for appointment..." rows="3"></textarea>
            </div>
            
            <!-- 6 VITAL SIGNS SECTION -->
            <div class="vital-signs-section">
                <div class="vital-title">
                    <i class="fas fa-heartbeat"></i>
                    6 Vital Signs
                    <span class="text-sm font-normal text-gray-400">(Record patient vital signs - any value accepted)</span>
                    <?php if ($patient_id > 0 && $latest_vital_signs): ?>
                        <span class="text-xs text-green-500 ml-auto">
                            <i class="fas fa-check-circle"></i> Last recorded: <?= date('d/m/Y H:i', strtotime($latest_vital_signs['recorded_at'])) ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="vital-signs-grid">
                    
                    <!-- 1. Temperature -->
                    <div class="vital-sign-item">
                        <label class="vital-label">🌡️ Temperature</label>
                        <input type="number" name="temperature" class="vital-input" 
                               step="0.1" placeholder="e.g. 36.5" 
                               value="<?= $latest_vital_signs['temperature'] ?? '' ?>">
                        <span class="vital-unit">°C</span>
                    </div>
                    
                    <!-- 2. Blood Pressure -->
                    <div class="vital-sign-item">
                        <label class="vital-label">💓 Blood Pressure</label>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <input type="number" name="bp_systolic" class="vital-input" 
                                   style="width:50%;" placeholder="120"
                                   value="<?= $latest_vital_signs['blood_pressure_systolic'] ?? '' ?>">
                            <span style="color:var(--text-secondary);font-weight:700;">/</span>
                            <input type="number" name="bp_diastolic" class="vital-input" 
                                   style="width:50%;" placeholder="80"
                                   value="<?= $latest_vital_signs['blood_pressure_diastolic'] ?? '' ?>">
                        </div>
                        <span class="vital-unit">mmHg</span>
                    </div>
                    
                    <!-- 3. Pulse Rate -->
                    <div class="vital-sign-item">
                        <label class="vital-label">💓 Pulse Rate</label>
                        <input type="number" name="pulse_rate" class="vital-input" 
                               placeholder="72"
                               value="<?= $latest_vital_signs['pulse_rate'] ?? '' ?>">
                        <span class="vital-unit">bpm</span>
                    </div>
                    
                    <!-- 4. Weight -->
                    <div class="vital-sign-item">
                        <label class="vital-label">⚖️ Weight</label>
                        <input type="number" name="weight" class="vital-input" 
                               step="0.1" placeholder="65"
                               id="weightInput"
                               value="<?= $latest_vital_signs['weight'] ?? '' ?>"
                               oninput="calculateBMI()">
                        <span class="vital-unit">kg</span>
                    </div>
                    
                    <!-- 5. Height -->
                    <div class="vital-sign-item">
                        <label class="vital-label">📏 Height</label>
                        <input type="number" name="height" class="vital-input" 
                               step="0.1" placeholder="170"
                               id="heightInput"
                               value="<?= $latest_vital_signs['height'] ?? '' ?>"
                               oninput="calculateBMI()">
                        <span class="vital-unit">cm</span>
                    </div>
                    
                    <!-- 6. BMI (Auto-calculated) -->
                    <div class="vital-sign-item bmi-item">
                        <label class="vital-label">📊 BMI</label>
                        <input type="number" name="bmi" class="vital-input" 
                               id="bmiOutput" readonly
                               step="0.1" placeholder="Auto-calculated"
                               value="<?= $latest_vital_signs['bmi'] ?? '' ?>">
                        <span class="vital-unit">kg/m²</span>
                        <span class="vital-bmi-category" id="bmiCategory" style="font-size:0.6rem;font-weight:600;display:block;margin-top:2px;">
                            Normal: 18.5 - 24.9
                        </span>
                    </div>
                    
                </div>
                
                <!-- Vital Signs Notes -->
                <div class="form-row mt-3">
                    <label class="form-label">
                        <i class="fas fa-comment label-icon"></i> Vital Signs Notes
                    </label>
                    <textarea name="vital_notes" class="form-control" rows="2" 
                              placeholder="Any notes about the vital signs measurements"><?= $latest_vital_signs['notes'] ?? '' ?></textarea>
                </div>
            </div>
            
            <!-- FORM ACTIONS -->
            <div class="form-actions">
                <button type="submit" class="btn btn-success" id="submitBtn">
                    <i class="fas fa-save"></i> Schedule Appointment
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="appointments.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
            
            <!-- FOOTER INFO -->
            <div class="mt-4 pt-3 text-xs text-gray-400 text-center border-t border-gray-200 dark:border-gray-700">
                <i class="fas fa-info-circle mr-1"></i>
                Schedule an appointment with 6 vital signs: BP, Weight, Height, Temperature, Pulse, BMI
                <span class="mx-2">|</span>
                <span id="formTimestamp"><?= date('h:i:s A') ?></span>
            </div>
        </form>
    </div>

    <!-- QUICK STATS -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-5" style="max-width:950px;margin:24px auto 0;">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <p class="stat-number primary"><?= count($patients) ?></p>
            <p class="stat-label">Patients Available</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👨‍⚕️</div>
            <p class="stat-number green" id="totalDoctorsStat"><?= $total_doctors ?></p>
            <p class="stat-label">Doctors Available</p>
            <p class="text-xs text-gray-400" id="onlineDoctorsStatTime"><?= $online_doctors ?> online</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💓</div>
            <p class="stat-number purple"><?= $patient_id > 0 && $latest_vital_signs ? '✓' : '—' ?></p>
            <p class="stat-label">Vital Signs</p>
            <p class="text-xs text-gray-400"><?= $patient_id > 0 && $latest_vital_signs ? 'Recorded' : 'Not recorded' ?></p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <p class="stat-number red"><?= date('M d, Y') ?></p>
            <p class="stat-label">Today's Date</p>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            New Appointment with 6 Vital Signs
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
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
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DATE & TIME - 12 HOUR FORMAT
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTimestamp');
        if (ftEl) ftEl.textContent = 'Last updated: ' + timeStr;
        
        var formTs = document.getElementById('formTimestamp');
        if (formTs) formTs.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // TIME INPUT VALIDATION - Manual entry
    // ================================================================
    var hourInput = document.getElementById('hourInput');
    var minuteInput = document.getElementById('minuteInput');
    
    if (hourInput) {
        hourInput.addEventListener('input', function() {
            var val = parseInt(this.value);
            if (val < 1) this.value = 1;
            if (val > 12) this.value = 12;
            if (this.value.length > 2) this.value = this.value.slice(0, 2);
        });
        
        hourInput.addEventListener('blur', function() {
            if (this.value === '' || this.value === '0') {
                this.value = '09';
            }
            if (this.value.length === 1) {
                this.value = '0' + this.value;
            }
        });
    }
    
    if (minuteInput) {
        minuteInput.addEventListener('input', function() {
            var val = parseInt(this.value);
            if (val < 0) this.value = 0;
            if (val > 59) this.value = 59;
            if (this.value.length > 2) this.value = this.value.slice(0, 2);
        });
        
        minuteInput.addEventListener('blur', function() {
            if (this.value === '' || this.value === '0') {
                this.value = '00';
            }
            if (this.value.length === 1) {
                this.value = '0' + this.value;
            }
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
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // BMI CALCULATOR
    // ================================================================
    function calculateBMI() {
        var weightInput = document.getElementById('weightInput');
        var heightInput = document.getElementById('heightInput');
        var bmiOutput = document.getElementById('bmiOutput');
        var bmiCategory = document.getElementById('bmiCategory');
        
        if (!weightInput || !heightInput || !bmiOutput || !bmiCategory) return;
        
        var weight = parseFloat(weightInput.value);
        var height = parseFloat(heightInput.value);
        
        if (weight && height && height > 0) {
            var heightM = height / 100;
            var bmi = weight / (heightM * heightM);
            bmi = Math.round(bmi * 10) / 10;
            
            bmiOutput.value = bmi;
            
            var category = '';
            var color = '';
            if (bmi < 16) {
                category = 'Severe Underweight';
                color = '#DC2626';
            } else if (bmi < 18.5) {
                category = 'Underweight';
                color = '#D97706';
            } else if (bmi < 25) {
                category = 'Normal';
                color = '#059669';
            } else if (bmi < 30) {
                category = 'Overweight';
                color = '#D97706';
            } else if (bmi < 35) {
                category = 'Obese Class I';
                color = '#DC2626';
            } else if (bmi < 40) {
                category = 'Obese Class II';
                color = '#DC2626';
            } else {
                category = 'Obese Class III';
                color = '#DC2626';
            }
            
            bmiCategory.textContent = category + ' (18.5 - 24.9 Normal)';
            bmiCategory.style.color = color;
        } else {
            bmiOutput.value = '';
            bmiCategory.textContent = 'Normal: 18.5 - 24.9';
            bmiCategory.style.color = '';
        }
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        if (!toast) return;
        
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
    // AJAX AUTO-UPDATE DOCTOR STATUS USING get_online_doctors.php
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var currentBranchId = <?= json_encode($selected_branch_id) ?>;
    var retryCount = 0;
    var maxRetries = 5;
    var doctorSelect = document.getElementById('doctorSelect');
    var updateCount = 0;

    function getCurrentTime() {
        var now = new Date();
        return now.toLocaleTimeString('en-US', { 
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true 
        });
    }

    function updateDoctorDropdown(doctorsData) {
        if (!doctorSelect) {
            console.error('❌ Doctor select not found!');
            return;
        }
        
        var currentValue = doctorSelect.value;
        var onlineCount = 0;
        var offlineCount = 0;
        
        // Clear all options
        while (doctorSelect.options.length > 0) {
            doctorSelect.remove(0);
        }
        
        // Add placeholder option
        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '-- Select Doctor --';
        doctorSelect.appendChild(placeholder);
        
        // Add each doctor and count online/offline
        doctorsData.forEach(function(doctor) {
            var option = document.createElement('option');
            option.value = doctor.id;
            option.dataset.online = doctor.is_online;
            option.dataset.name = doctor.full_name;
            option.dataset.specialty = doctor.specialty || '';
            
            var label = 'Dr. ' + doctor.full_name;
            if (doctor.specialty) {
                label += ' (' + doctor.specialty + ')';
            }
            
            if (doctor.is_online == 1) {
                label += ' 🟢 Online';
                option.style.color = '#059669';
                option.style.fontWeight = '600';
                onlineCount++;
            } else {
                label += ' ⚪ Offline';
                option.style.color = '#94A3B8';
                option.style.fontWeight = '400';
                offlineCount++;
            }
            
            option.textContent = label;
            doctorSelect.appendChild(option);
        });
        
        // Try to restore previous selection
        var found = false;
        for (var i = 0; i < doctorSelect.options.length; i++) {
            if (doctorSelect.options[i].value == currentValue) {
                doctorSelect.value = currentValue;
                found = true;
                break;
            }
        }
        
        // If not found or no selection, select first online doctor
        if (!found || !currentValue) {
            for (var i = 0; i < doctorSelect.options.length; i++) {
                var opt = doctorSelect.options[i];
                if (opt.value && opt.dataset.online == '1') {
                    doctorSelect.value = opt.value;
                    break;
                }
            }
        }
        
        // Update status text and counts
        updateDoctorStatusText();
        updateDoctorStatusCounts(onlineCount, offlineCount);
        
        // Log update
        updateCount++;
        console.log('✅ Dropdown updated (' + updateCount + ' times) - ' + doctorsData.length + ' doctors loaded');
        console.log('   Online: ' + onlineCount + ', Offline: ' + offlineCount);
    }

    function updateDoctorStatusText() {
        var selectedOption = doctorSelect.options[doctorSelect.selectedIndex];
        var doctorStatusText = document.getElementById('doctorStatusText');
        if (selectedOption && selectedOption.dataset && doctorStatusText) {
            var isOnline = selectedOption.dataset.online == '1';
            var doctorName = selectedOption.dataset.name || 'Doctor';
            if (isOnline) {
                doctorStatusText.innerHTML = '<i class="fas fa-check-circle" style="color:#059669;"></i> Dr. ' + doctorName + ' is <strong style="color:#059669;">Online</strong> and available';
            } else {
                doctorStatusText.innerHTML = '<i class="fas fa-clock" style="color:#D97706;"></i> Dr. ' + doctorName + ' is <strong style="color:#D97706;">Offline</strong> - may not respond immediately';
            }
        }
    }

    function updateDoctorStatusCounts(onlineCount, offlineCount) {
        var onlineDisplay = document.getElementById('onlineCountDisplay');
        var offlineDisplay = document.getElementById('offlineCountDisplay');
        
        if (onlineDisplay) onlineDisplay.textContent = onlineCount;
        if (offlineDisplay) offlineDisplay.textContent = offlineCount;
    }

    function fetchDoctorStatus() {
        if (isUpdating) {
            return;
        }
        
        isUpdating = true;
        var timestamp = new Date().getTime();
        var url = '/dispensary_system/frontend/api/get_online_doctors.php?branch_id=' + encodeURIComponent(currentBranchId) + '&t=' + timestamp;
        
        console.log('🔄 Fetching doctor status... (attempt ' + (retryCount + 1) + ')');
        
        fetch(url, {
            method: 'GET',
            cache: 'no-cache',
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache'
            }
        })
        .then(function(response) { 
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json(); 
        })
        .then(function(data) {
            retryCount = 0;
            if (data.success) {
                console.log('✅ API response: ' + data.total_doctors + ' doctors, ' + data.online_count + ' online');
                
                // Update the dropdown with new data
                updateDoctorDropdown(data.doctors);
                
                // Update online count
                var onlineCount = data.online_count || 0;
                
                // Update UI elements
                var onlineCountEl = document.getElementById('onlineDoctorCount');
                if (onlineCountEl) onlineCountEl.textContent = onlineCount;
                
                var onlineDoctorsText = document.getElementById('onlineDoctorsText');
                if (onlineDoctorsText) onlineDoctorsText.textContent = '(' + onlineCount + ' online)';
                
                var onlineDoctorsStatTime = document.getElementById('onlineDoctorsStatTime');
                if (onlineDoctorsStatTime) onlineDoctorsStatTime.textContent = onlineCount + ' online';
                
                var totalDoctorsStat = document.getElementById('totalDoctorsStat');
                if (totalDoctorsStat) totalDoctorsStat.textContent = data.total_doctors || 0;
                
                // Update badge
                var timeStr = getCurrentTime();
                var updateBadge = document.getElementById('updateBadge');
                if (updateBadge) {
                    updateBadge.innerHTML = '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + timeStr;
                }
                
                var footerTs = document.getElementById('footerTimestamp');
                if (footerTs) {
                    footerTs.textContent = 'Last updated: ' + timeStr;
                }
                
            } else {
                console.warn('⚠️ API returned success=false:', data.message || 'Unknown error');
            }
            isUpdating = false;
        })
        .catch(function(error) {
            console.error('❌ Doctor status update error:', error);
            isUpdating = false;
            
            retryCount++;
            if (retryCount < maxRetries) {
                console.log('🔄 Retrying in 3 seconds... (attempt ' + (retryCount + 1) + '/' + maxRetries + ')');
                setTimeout(fetchDoctorStatus, 3000);
            } else {
                var updateBadge = document.getElementById('updateBadge');
                if (updateBadge) {
                    updateBadge.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#F59E0B;"></i> Offline';
                }
                retryCount = 0;
                console.log('❌ Max retries reached. Please refresh page.');
            }
        });
    }

    // ================================================================
    // LISTEN FOR DOCTOR SELECTION CHANGES
    // ================================================================
    if (doctorSelect) {
        doctorSelect.addEventListener('change', function() {
            updateDoctorStatusText();
        });
    }

    // ================================================================
    // START AUTO-UPDATE
    // ================================================================
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
        // Initial update after 1 second
        setTimeout(function() {
            fetchDoctorStatus();
        }, 1000);
        // Set interval
        updateInterval = setInterval(fetchDoctorStatus, 3000);
        console.log('✅ Auto-update timer started (every 3 seconds)');
    }

    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            console.log('⏹️ Auto-update stopped');
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    // ================================================================
    // INITIALIZATION
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        calculateBMI();
        // Initialize doctor status text
        setTimeout(function() {
            updateDoctorStatusText();
            startAutoUpdate();
            console.log('✅ System initialized');
            console.log('🔄 Doctor status will update in dropdown WITHOUT page refresh');
            console.log('📡 Using API: get_online_doctors.php');
        }, 500);
    });

    console.log('%c📅 Braick - New Appointment with 6 Vital Signs', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= $_SESSION['full_name'] ?? 'Rose Mwangi' ?> (<?= $_SESSION['role'] ?? 'reception' ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👥 Patients: <?= count($patients) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👨‍⚕️ Doctors: <?= $total_doctors ?> (<?= $online_doctors ?> online)', 'font-size:13px; color:#64748B;');
    console.log('%c💓 6 Vital Signs: BP, Weight, Height, Temperature, Pulse Rate, BMI', 'font-size:13px; color:#DC2626;');
    console.log('%c🔄 Auto-update: Every 3 seconds (Doctor status via AJAX)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Dropdown updates WITHOUT page refresh', 'font-size:13px; color:#059669;');
    console.log('%c🕐 Time format: 12-hour (Manual input or select)', 'font-size:13px; color:#64748B;');
    console.log('%c📡 API URL: /dispensary_system/frontend/api/get_online_doctors.php', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>