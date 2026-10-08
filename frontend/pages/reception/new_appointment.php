<?php
// ================================================================
// FILE: frontend/pages/reception/new_appointment.php
// RECEPTION - NEW APPOINTMENT WITH 7 VITAL SIGNS
// ✅ Inatumia shared header + sidebar
// AJAX auto-update: Doctor status kila sekunde 3
// ================================================================

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
// ACCESS CONTROL
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    switch ($_SESSION['role']) {
        case 'doctor':     header('Location: ../doctor/dashboard.php');     break;
        case 'pharmacy':   header('Location: ../pharmacy/dashboard.php');   break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier':    header('Location: ../cashier/dashboard.php');    break;
        default:           header('Location: ../login.php');                break;
    }
    exit;
}

// ================================================================
// SESSION DATA
// ================================================================
$user_id     = $_SESSION['user_id']     ?? 0;
$full_name   = $_SESSION['full_name']   ?? 'User';
$role        = $_SESSION['role']        ?? 'reception';
$branch_id   = $_SESSION['branch_id']   ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username    = $_SESSION['username']    ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

$patient_id           = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$message              = '';
$message_type         = '';
$patients             = [];
$doctors              = [];
$online_doctors       = 0;
$total_doctors        = 0;
$latest_vital_signs   = null;
$unread_notifications = 0;

try {
    $db = Database::getInstance()->getConnection();

    // Patients
    $stmt = $db->prepare("SELECT id, full_name, patient_id FROM patients WHERE branch_id = ? ORDER BY full_name");
    $stmt->execute([$branch_id]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Doctors
    $stmt = $db->prepare("
        SELECT id, full_name, specialty, is_online, profile_pic
        FROM users
        WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
        ORDER BY is_online DESC, full_name
    ");
    $stmt->execute([$branch_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_doctors = count($doctors);
    $online_doctors = 0;
    foreach ($doctors as $doc) {
        if ((int)$doc['is_online'] === 1) $online_doctors++;
    }

    // Latest vitals
    if ($patient_id > 0) {
        $stmt = $db->prepare("
            SELECT temperature, blood_pressure_systolic, blood_pressure_diastolic,
                   pulse_rate, oxygen_saturation, weight, height, bmi, notes, recorded_at
            FROM vital_signs
            WHERE patient_id = ?
            ORDER BY recorded_at DESC
            LIMIT 1
        ");
        $stmt->execute([$patient_id]);
        $latest_vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Unread notifications
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_notifications = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    // ================================================================
    // HANDLE POST
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $patient_id         = (int)($_POST['patient_id'] ?? 0);
        $doctor_id          = (int)($_POST['doctor_id'] ?? 0);
        $appointment_date   = trim($_POST['appointment_date'] ?? '');
        $appointment_hour   = trim($_POST['appointment_hour'] ?? '09');
        $appointment_minute = trim($_POST['appointment_minute'] ?? '00');
        $appointment_ampm   = trim($_POST['appointment_ampm'] ?? 'AM');
        $purpose            = trim($_POST['purpose'] ?? '');
        $status             = trim($_POST['status'] ?? 'scheduled');
        $visit_type         = trim($_POST['visit_type'] ?? 'new');

        // 7 Vital Signs
        $temperature       = $_POST['temperature']       ?? null;
        $bp_systolic       = $_POST['bp_systolic']       ?? null;
        $bp_diastolic      = $_POST['bp_diastolic']      ?? null;
        $pulse_rate        = $_POST['pulse_rate']        ?? null;
        $oxygen_saturation = $_POST['oxygen_saturation'] ?? null;
        $weight            = $_POST['weight']            ?? null;
        $height            = $_POST['height']            ?? null;
        $vital_notes       = trim($_POST['vital_notes']  ?? '');

        $bmi = null;
        if (!empty($weight) && !empty($height) && $height > 0) {
            $height_m = $height / 100;
            $bmi = round($weight / ($height_m * $height_m), 1);
        }

        $errors = [];
        if ($patient_id <= 0) throw new Exception('Please select a patient');
        if ($doctor_id <= 0)  throw new Exception('Please select a doctor');
        if (empty($appointment_date)) throw new Exception('Please select a date');
        if (empty($appointment_hour)) throw new Exception('Please select an hour');
        if (empty($appointment_ampm)) throw new Exception('Please select AM/PM');

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $hour_12 = (int)$appointment_hour;
                if ($appointment_ampm === 'PM' && $hour_12 != 12) $hour_24 = $hour_12 + 12;
                elseif ($appointment_ampm === 'AM' && $hour_12 == 12) $hour_24 = 0;
                else $hour_24 = $hour_12;

                $time_str = str_pad($hour_24, 2, '0', STR_PAD_LEFT) . ':' . str_pad($appointment_minute, 2, '0', STR_PAD_LEFT) . ':00';
                $datetime = $appointment_date . ' ' . $time_str;

                // Insert appointment
                $stmt = $db->prepare("
                    INSERT INTO appointments (
                        patient_id, doctor_id, appointment_date, purpose, status,
                        visit_type, branch_id, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $patient_id, $doctor_id, $datetime, $purpose, $status,
                    $visit_type, $branch_id, $_SESSION['user_id']
                ]);
                $appointment_id = $db->lastInsertId();

                // Insert vitals
                $has_vital = (
                    (!empty($temperature) || $temperature === '0') ||
                    (!empty($bp_systolic) || $bp_systolic === '0') ||
                    (!empty($bp_diastolic) || $bp_diastolic === '0') ||
                    (!empty($pulse_rate) || $pulse_rate === '0') ||
                    (!empty($oxygen_saturation) || $oxygen_saturation === '0') ||
                    (!empty($weight) || $weight === '0') ||
                    (!empty($height) || $height === '0')
                );

                if ($has_vital) {
                    $stmt = $db->prepare("
                        INSERT INTO vital_signs (
                            patient_id, appointment_id, recorded_by, branch_id,
                            temperature, blood_pressure_systolic, blood_pressure_diastolic,
                            pulse_rate, oxygen_saturation, weight, height, bmi, notes, recorded_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $patient_id, $appointment_id, $_SESSION['user_id'], $branch_id,
                        (!empty($temperature) || $temperature === '0') ? (float)$temperature : null,
                        (!empty($bp_systolic) || $bp_systolic === '0') ? (int)$bp_systolic : null,
                        (!empty($bp_diastolic) || $bp_diastolic === '0') ? (int)$bp_diastolic : null,
                        (!empty($pulse_rate) || $pulse_rate === '0') ? (int)$pulse_rate : null,
                        (!empty($oxygen_saturation) || $oxygen_saturation === '0') ? (int)$oxygen_saturation : null,
                        (!empty($weight) || $weight === '0') ? (float)$weight : null,
                        (!empty($height) || $height === '0') ? (float)$height : null,
                        $bmi,
                        !empty($vital_notes) ? $vital_notes : null
                    ]);
                }

                // Log activity
                try {
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                        VALUES (?, ?, 'appointment_created', ?, NOW())
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'], $branch_id,
                        "New appointment created for patient ID: $patient_id with doctor ID: $doctor_id"
                    ]);
                } catch (Exception $e) {}

                $db->commit();

                $message = "Appointment scheduled successfully!";
                $message_type = 'success';

                echo '<script>
                    setTimeout(function(){
                        if (typeof showToast === "function") {
                            showToast("✅ Success", "Appointment scheduled successfully!", "success");
                        }
                    }, 100);
                    setTimeout(function(){
                        window.location.href = "appointments.php?date=' . $appointment_date . '";
                    }, 2000);
                </script>';
            } catch (Exception $e) {
                $db->rollBack();
                $message = "Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $patients = [];
    $doctors = [];
}

// ================================================================
// PROFILE URLS
// ================================================================
$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/reception_header.php';
include_once __DIR__ . '/../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= (isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true') ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Appointment - Braick Dispensary</title>

    <link rel="icon" href="<?= htmlspecialchars($logo_path) ?>" type="image/png">
    <link rel="shortcut icon" href="<?= htmlspecialchars($logo_path) ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* ================================================================
           PAGE-SPECIFIC STYLES
           (Header/Sidebar handled by shared components)
           ================================================================ */

        /* PAGE HEADER */
        .page-header {
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
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -20%;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        .page-header .page-title {
            color: #fff;
            font-size: 1.7rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .page-header .page-title i { font-size: 1.9rem; opacity: .9; }
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 6px;
        }
        .page-header .page-subtitle strong { color: #fff; font-weight: 600; }

        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: #fff;
            padding: 3px 12px;
            border-radius: 999px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .header-badge {
            background: rgba(255,255,255,0.15);
            color: #fff;
            padding: 4px 14px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 500;
            backdrop-filter: blur(6px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .header-badge .online-count { color: #34D399; font-weight: 700; }

        .update-badge-light {
            background: rgba(255,255,255,0.14);
            color: rgba(255,255,255,0.92);
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.62rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255,255,255,0.12);
        }

        .btn-outline-light {
            background: rgba(255,255,255,0.14);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.22);
            padding: 9px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all .25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(6px);
            position: relative;
            z-index: 1;
            cursor: pointer;
        }
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.26);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
        }

        /* FORM CARD */
        .form-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 32px 36px;
            border: 1px solid var(--border-color);
            transition: all .3s ease;
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
            width: 50px; height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
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
        .form-label .required { color: var(--danger); margin-left: 2px; }
        .form-label .label-icon { margin-right: 4px; color: var(--primary); }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.85rem;
            transition: all .25s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        .form-control:hover { border-color: #94A3B8; }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.10);
        }
        .form-control::placeholder { color: var(--text-secondary); opacity: .55; }
        .form-control:disabled { opacity: .6; cursor: not-allowed; }

        select.form-control { cursor: pointer; }
        textarea.form-control { resize: vertical; min-height: 60px; }

        .form-row { margin-bottom: 18px; }
        .form-row:last-child { margin-bottom: 0; }

        .form-actions {
            display: flex;
            gap: 12px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }

        /* VITAL SIGNS */
        .vital-signs-section {
            background: var(--bg-body);
            border-radius: 14px;
            padding: 20px 24px;
            margin: 20px 0;
            border: 2px solid var(--border-color);
            transition: all .3s ease;
        }
        .vital-signs-section:hover { border-color: var(--primary); }
        .vital-signs-section .vital-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        .vital-signs-section .vital-title i { color: #DC2626; font-size: 1.2rem; }

        .vital-signs-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        .vital-sign-item {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            transition: all .3s ease;
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
            letter-spacing: .05em;
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
        .vital-sign-item .vital-input:focus { color: var(--primary); }
        .vital-sign-item .vital-input::placeholder {
            color: var(--text-secondary);
            opacity: .4;
            font-weight: 400;
        }
        .vital-sign-item .vital-unit {
            font-size: 0.55rem;
            color: var(--text-secondary);
            display: block;
        }
        .vital-sign-item.spo2-item {
            border-color: #0EA5E9;
            background: linear-gradient(135deg, rgba(14,165,233,0.05), rgba(14,165,233,0.10));
        }
        .vital-sign-item.spo2-item:hover {
            border-color: #0284C7;
            box-shadow: 0 2px 12px rgba(14,165,233,0.15);
        }
        .vital-sign-item.spo2-item .vital-label { color: #0284C7; }

        /* TIME INPUT */
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
            transition: all .25s ease;
        }
        .time-select-group .time-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.10);
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

        /* DOCTOR STATUS INFO */
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
        .doctor-status-info .online-doctors { color: var(--success); font-weight: 600; }
        .doctor-status-info .offline-doctors { color: var(--text-secondary); font-weight: 600; }
        .doctor-status-info .status-icon { margin-right: 4px; }
        .doctor-status-info .status-count { font-weight: 700; }

        /* STAT CARD */
        .stat-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all .3s ease;
            box-shadow: var(--shadow-sm);
        }
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .stat-card .stat-number { font-size: 1.6rem; font-weight: 700; }
        .stat-card .stat-number.primary { color: var(--primary); }
        .stat-card .stat-number.green   { color: var(--success); }
        .stat-card .stat-number.purple  { color: #7C3AED; }
        .stat-card .stat-number.red     { color: var(--danger); }
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .stat-card .stat-icon { font-size: 1.4rem; margin-bottom: 4px; }

        /* BUTTONS */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all .3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.35);
        }
        .btn-success {
            background: var(--success);
            color: #fff;
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

        /* ALERTS */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .alert-success { background: var(--success-bg); color: var(--success-dark); border: 1px solid var(--success); }
        .alert-error   { background: var(--danger-bg);  color: var(--danger-dark);  border: 1px solid var(--danger); }
        .alert i { font-size: 1.1rem; margin-top: 2px; }
        .alert .alert-content { flex: 1; }

        /* GRID */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .grid-full { grid-column: 1 / -1; }

        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }

        /* ANIMATIONS */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp .5s ease forwards;
            opacity: 0;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .form-card { padding: 20px; }
            .vital-signs-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .form-card { padding: 14px; }
            .form-card .form-header .form-title { font-size: 1rem; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .vital-signs-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .form-card { padding: 12px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .stat-card .stat-number { font-size: 1.4rem; }
            .vital-signs-grid { grid-template-columns: 1fr 1fr; }
            .vital-signs-section { padding: 12px 14px; }
            .grid-2 { grid-template-columns: 1fr; gap: 12px; }
            .time-select-group .ampm-select { flex: 1 1 100%; max-width: 100%; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-calendar-plus"></i>
                New Appointment
                <span class="role-badge-display">RECEPTION</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Schedule a new appointment with <strong>7 Vital Signs</strong> in <?= htmlspecialchars($branch_name) ?>

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
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="appointments.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Appointments
            </a>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>" style="max-width:950px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div class="alert-content"><?= htmlspecialchars($message) ?></div>
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
                <p class="form-subtitle">Fill in the details below to schedule an appointment with 7 vital signs</p>
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
                            <option value="<?= (int)$patient['id'] ?>" <?= $patient_id == $patient['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($patient['full_name'] ?? 'Unknown') ?> (<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($patients)): ?>
                        <p style="font-size:.72rem;color:#D97706;margin-top:4px;">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            No patients registered. <a href="new_patient.php" style="color:var(--primary);">Register a patient</a>
                        </p>
                    <?php else: ?>
                        <p style="font-size:.72rem;color:var(--text-secondary);margin-top:4px;">
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
                            <option value="<?= (int)$doctor['id'] ?>"
                                    data-online="<?= (int)$doctor['is_online'] ?>"
                                    data-name="<?= htmlspecialchars($doctor['full_name'] ?? 'Doctor') ?>"
                                    data-specialty="<?= htmlspecialchars($doctor['specialty'] ?? '') ?>">
                                Dr. <?= htmlspecialchars($doctor['full_name'] ?? 'Unknown') ?>
                                <?php if (!empty($doctor['specialty'])): ?>(<?= htmlspecialchars($doctor['specialty']) ?>)<?php endif; ?>
                                <?= (int)$doctor['is_online'] === 1 ? '🟢 Online' : '⚪ Offline' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($doctors)): ?>
                        <p style="font-size:.72rem;color:#DC2626;margin-top:4px;">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            No doctors available.
                        </p>
                    <?php else: ?>
                        <p style="font-size:.72rem;color:var(--text-secondary);margin-top:4px;" id="doctorStatusText">
                            <i class="fas fa-info-circle mr-1"></i>
                            <?= $total_doctors ?> doctor(s) available
                            <span style="color:#059669;" id="onlineDoctorsText">(<?= $online_doctors ?> online)</span>
                        </p>
                        <div class="doctor-status-info" id="doctorStatusInfo">
                            <span class="online-doctors">
                                <i class="fas fa-circle status-icon" style="color:#059669;font-size:.5rem;"></i>
                                Online: <span class="status-count" id="onlineCountDisplay"><?= $online_doctors ?></span>
                            </span>
                            <span class="offline-doctors">
                                <i class="fas fa-circle status-icon" style="color:#94A3B8;font-size:.5rem;"></i>
                                Offline: <span class="status-count" id="offlineCountDisplay"><?= max(0, $total_doctors - $online_doctors) ?></span>
                            </span>
                            <span style="color:var(--text-secondary);font-weight:400;">
                                Total: <?= $total_doctors ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ROW 2: Date + Time -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-calendar-day label-icon"></i> Date <span class="required">*</span>
                    </label>
                    <input type="date" name="appointment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-clock label-icon"></i> Time <span class="required">*</span>
                    </label>
                    <div class="time-select-group">
                        <input type="number" name="appointment_hour" class="time-input" id="hourInput" value="09" min="1" max="12" placeholder="HH" required>
                        <span class="time-label">Hour</span>
                        <span class="time-separator">:</span>
                        <input type="number" name="appointment_minute" class="time-input" id="minuteInput" value="00" min="0" max="59" placeholder="MM" required>
                        <span class="time-label">Min</span>
                        <select name="appointment_ampm" class="form-control ampm-select" id="ampmSelect" required>
                            <option value="AM" selected>AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                    <p style="font-size:.72rem;color:var(--text-secondary);margin-top:4px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Enter hour (1-12) and minutes (00-59)
                    </p>
                </div>
            </div>

            <!-- ROW 3: Visit Type + Status -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label"><i class="fas fa-tag label-icon"></i> Visit Type</label>
                    <select name="visit_type" class="form-control">
                        <option value="new">🆕 New Patient</option>
                        <option value="follow-up">🔄 Follow-up</option>
                        <option value="emergency">🚨 Emergency</option>
                    </select>
                </div>
                <div class="form-row">
                    <label class="form-label"><i class="fas fa-flag label-icon"></i> Status</label>
                    <select name="status" class="form-control">
                        <option value="scheduled">📅 Scheduled</option>
                        <option value="confirmed">✅ Confirmed</option>
                        <option value="pending">⏳ Pending</option>
                    </select>
                </div>
            </div>

            <!-- ROW 4: Purpose -->
            <div class="form-row grid-full">
                <label class="form-label"><i class="fas fa-notes-medical label-icon"></i> Purpose</label>
                <textarea name="purpose" class="form-control" placeholder="Reason for appointment..." rows="3"></textarea>
            </div>

            <!-- 7 VITAL SIGNS -->
            <div class="vital-signs-section">
                <div class="vital-title">
                    <i class="fas fa-heartbeat"></i>
                    7 Vital Signs
                    <span style="font-size:.8rem;font-weight:400;color:var(--text-secondary);">(Record patient vital signs)</span>
                    <?php if ($patient_id > 0 && $latest_vital_signs): ?>
                        <span style="font-size:.72rem;color:#059669;margin-left:auto;">
                            <i class="fas fa-check-circle"></i>
                            Last recorded: <?= date('d/m/Y H:i', strtotime($latest_vital_signs['recorded_at'] ?? 'now')) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="vital-signs-grid">
                    <!-- 1. Temperature -->
                    <div class="vital-sign-item">
                        <label class="vital-label">🌡️ Temperature</label>
                        <input type="number" name="temperature" class="vital-input" step="0.1" placeholder="36.5"
                               value="<?= htmlspecialchars($latest_vital_signs['temperature'] ?? '') ?>">
                        <span class="vital-unit">°C</span>
                    </div>

                    <!-- 2. BP -->
                    <div class="vital-sign-item">
                        <label class="vital-label">💓 Blood Pressure</label>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <input type="number" name="bp_systolic" class="vital-input" style="width:50%;" placeholder="120"
                                   value="<?= htmlspecialchars($latest_vital_signs['blood_pressure_systolic'] ?? '') ?>">
                            <span style="color:var(--text-secondary);font-weight:700;">/</span>
                            <input type="number" name="bp_diastolic" class="vital-input" style="width:50%;" placeholder="80"
                                   value="<?= htmlspecialchars($latest_vital_signs['blood_pressure_diastolic'] ?? '') ?>">
                        </div>
                        <span class="vital-unit">mmHg</span>
                    </div>

                    <!-- 3. Pulse -->
                    <div class="vital-sign-item">
                        <label class="vital-label">💓 Pulse Rate</label>
                        <input type="number" name="pulse_rate" class="vital-input" placeholder="72"
                               value="<?= htmlspecialchars($latest_vital_signs['pulse_rate'] ?? '') ?>">
                        <span class="vital-unit">bpm</span>
                    </div>

                    <!-- 4. SpO2 -->
                    <div class="vital-sign-item spo2-item">
                        <label class="vital-label">🫁 Oxygen (SpO₂)</label>
                        <input type="number" name="oxygen_saturation" class="vital-input" placeholder="98" min="0" max="100"
                               value="<?= htmlspecialchars($latest_vital_signs['oxygen_saturation'] ?? '') ?>">
                        <span class="vital-unit">% (Normal: 95-100%)</span>
                    </div>

                    <!-- 5. Weight -->
                    <div class="vital-sign-item">
                        <label class="vital-label">⚖️ Weight</label>
                        <input type="number" name="weight" class="vital-input" step="0.1" placeholder="65" id="weightInput"
                               value="<?= htmlspecialchars($latest_vital_signs['weight'] ?? '') ?>"
                               oninput="calculateBMI()">
                        <span class="vital-unit">kg</span>
                    </div>

                    <!-- 6. Height -->
                    <div class="vital-sign-item">
                        <label class="vital-label">📏 Height</label>
                        <input type="number" name="height" class="vital-input" step="0.1" placeholder="170" id="heightInput"
                               value="<?= htmlspecialchars($latest_vital_signs['height'] ?? '') ?>"
                               oninput="calculateBMI()">
                        <span class="vital-unit">cm</span>
                    </div>

                    <!-- 7. BMI -->
                    <div class="vital-sign-item">
                        <label class="vital-label">📊 BMI</label>
                        <input type="number" name="bmi" class="vital-input" id="bmiOutput" readonly step="0.1" placeholder="Auto"
                               value="<?= htmlspecialchars($latest_vital_signs['bmi'] ?? '') ?>">
                        <span class="vital-unit">kg/m²</span>
                        <span id="bmiCategory" style="font-size:.6rem;font-weight:600;display:block;margin-top:2px;">Normal: 18.5 - 24.9</span>
                    </div>
                </div>

                <div class="form-row" style="margin-top:12px;">
                    <label class="form-label"><i class="fas fa-comment label-icon"></i> Vital Signs Notes</label>
                    <textarea name="vital_notes" class="form-control" rows="2" placeholder="Any notes about the vital signs measurements"><?= htmlspecialchars($latest_vital_signs['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- ACTIONS -->
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
            <div style="margin-top:16px;padding-top:12px;font-size:.68rem;color:var(--text-secondary);text-align:center;border-top:1px solid var(--border-color);">
                <i class="fas fa-info-circle mr-1"></i>
                Schedule with 7 vital signs: BP, Weight, Height, Temperature, Pulse, SpO₂, BMI
                <span style="margin:0 8px;">|</span>
                <span id="formTimestamp"><?= date('h:i:s A') ?></span>
            </div>
        </form>
    </div>

    <!-- QUICK STATS -->
    <div style="max-width:950px;margin:24px auto 0;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <p class="stat-number primary"><?= count($patients) ?></p>
            <p class="stat-label">Patients Available</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👨‍⚕️</div>
            <p class="stat-number green" id="totalDoctorsStat"><?= $total_doctors ?></p>
            <p class="stat-label">Doctors Available</p>
            <p style="font-size:.68rem;color:var(--text-secondary);" id="onlineDoctorsStatTime"><?= $online_doctors ?> online</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💓</div>
            <p class="stat-number purple"><?= $patient_id > 0 && $latest_vital_signs ? '✓' : '—' ?></p>
            <p class="stat-label">Vital Signs</p>
            <p style="font-size:.68rem;color:var(--text-secondary);"><?= $patient_id > 0 && $latest_vital_signs ? 'Recorded' : 'Not recorded' ?></p>
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
            <span style="margin:0 8px;opacity:.4;">|</span>
            New Appointment with 7 Vital Signs
            <span style="margin:0 8px;opacity:.4;">|</span>
            <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
            <span style="margin:0 8px;opacity:.4;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
    // ================================================================
    // TIME INPUT VALIDATION
    // ================================================================
    var hourInput   = document.getElementById('hourInput');
    var minuteInput = document.getElementById('minuteInput');

    if (hourInput) {
        hourInput.addEventListener('input', function () {
            var val = parseInt(this.value);
            if (val < 1) this.value = 1;
            if (val > 12) this.value = 12;
            if (this.value.length > 2) this.value = this.value.slice(0, 2);
        });
        hourInput.addEventListener('blur', function () {
            if (this.value === '' || this.value === '0') this.value = '09';
            if (this.value.length === 1) this.value = '0' + this.value;
        });
    }
    if (minuteInput) {
        minuteInput.addEventListener('input', function () {
            var val = parseInt(this.value);
            if (val < 0) this.value = 0;
            if (val > 59) this.value = 59;
            if (this.value.length > 2) this.value = this.value.slice(0, 2);
        });
        minuteInput.addEventListener('blur', function () {
            if (this.value === '' || this.value === '0') this.value = '00';
            if (this.value.length === 1) this.value = '0' + this.value;
        });
    }

    // ================================================================
    // SpO2 VALIDATION
    // ================================================================
    var spo2Input = document.querySelector('input[name="oxygen_saturation"]');
    if (spo2Input) {
        spo2Input.addEventListener('input', function () {
            var val = parseInt(this.value);
            if (this.value !== '') {
                if (val < 0) this.value = 0;
                if (val > 100) this.value = 100;
            }
        });
        spo2Input.addEventListener('blur', function () {
            if (this.value !== '') {
                var val = parseInt(this.value);
                if (val < 70)      { this.style.color = '#DC2626'; this.title = '⚠️ SpO₂ chini sana - Hatari!'; }
                else if (val < 95) { this.style.color = '#D97706'; this.title = '⚠️ SpO₂ chini ya kawaida'; }
                else               { this.style.color = '#059669'; this.title = '✅ SpO₂ nzuri'; }
            } else {
                this.style.color = '';
                this.title = '';
            }
        });
    }

    // ================================================================
    // BMI CALCULATOR
    // ================================================================
    function calculateBMI() {
        var weightInput  = document.getElementById('weightInput');
        var heightInput  = document.getElementById('heightInput');
        var bmiOutput    = document.getElementById('bmiOutput');
        var bmiCategory  = document.getElementById('bmiCategory');
        if (!weightInput || !heightInput || !bmiOutput || !bmiCategory) return;

        var weight = parseFloat(weightInput.value);
        var height = parseFloat(heightInput.value);

        if (weight && height && height > 0) {
            var heightM = height / 100;
            var bmi = Math.round((weight / (heightM * heightM)) * 10) / 10;
            bmiOutput.value = bmi;

            var category = '', color = '';
            if (bmi < 16)         { category = 'Severe Underweight'; color = '#DC2626'; }
            else if (bmi < 18.5)  { category = 'Underweight';        color = '#D97706'; }
            else if (bmi < 25)    { category = 'Normal';             color = '#059669'; }
            else if (bmi < 30)    { category = 'Overweight';         color = '#D97706'; }
            else if (bmi < 35)    { category = 'Obese Class I';      color = '#DC2626'; }
            else if (bmi < 40)    { category = 'Obese Class II';     color = '#DC2626'; }
            else                  { category = 'Obese Class III';    color = '#DC2626'; }

            bmiCategory.textContent = category + ' (Normal 18.5 - 24.9)';
            bmiCategory.style.color = color;
        } else {
            bmiOutput.value = '';
            bmiCategory.textContent = 'Normal: 18.5 - 24.9';
            bmiCategory.style.color = '';
        }
    }

    // ================================================================
    // TOAST (uses header's showToast if available, else local fallback)
    // ================================================================
    if (typeof window.showToast !== 'function') {
        window.showToast = function (title, message, type) {
            var toast = document.getElementById('toast');
            var t = document.getElementById('toastTitle');
            var m = document.getElementById('toastMessage');
            if (!toast) {
                alert(title + ': ' + message);
                return;
            }
            toast.className = 'toast-custom ' + (type || 'info');
            if (t) t.textContent = title;
            if (m) m.textContent = message;
            toast.style.display = 'flex';
            toast.classList.add('show');
            clearTimeout(toast.timeout);
            toast.timeout = setTimeout(function () {
                toast.classList.remove('show');
                setTimeout(() => toast.style.display = 'none', 400);
            }, 3500);
        };
    }

    // ================================================================
    // AJAX AUTO-UPDATE DOCTOR STATUS
    // ================================================================
    var updateInterval   = null;
    var isUpdating       = false;
    var currentBranchId  = <?= json_encode($branch_id) ?>;
    var retryCount       = 0;
    var maxRetries       = 5;
    var doctorSelect     = document.getElementById('doctorSelect');
    var updateCount      = 0;

    function getCurrentTime() {
        var now = new Date();
        return now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }

    function updateDoctorDropdown(doctorsData) {
        if (!doctorSelect) return;

        var currentValue = doctorSelect.value;
        var onlineCount = 0, offlineCount = 0;

        while (doctorSelect.options.length > 0) doctorSelect.remove(0);

        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '-- Select Doctor --';
        doctorSelect.appendChild(placeholder);

        doctorsData.forEach(function (doctor) {
            var option = document.createElement('option');
            option.value = doctor.id;
            option.dataset.online    = doctor.is_online;
            option.dataset.name      = doctor.full_name || 'Doctor';
            option.dataset.specialty = doctor.specialty || '';

            var label = 'Dr. ' + (doctor.full_name || 'Unknown');
            if (doctor.specialty) label += ' (' + doctor.specialty + ')';

            if (parseInt(doctor.is_online) === 1) {
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

        // Restore selection
        var found = false;
        for (var i = 0; i < doctorSelect.options.length; i++) {
            if (doctorSelect.options[i].value == currentValue) {
                doctorSelect.value = currentValue;
                found = true;
                break;
            }
        }
        if (!found || !currentValue) {
            for (var i = 0; i < doctorSelect.options.length; i++) {
                var opt = doctorSelect.options[i];
                if (opt.value && opt.dataset.online == '1') {
                    doctorSelect.value = opt.value;
                    break;
                }
            }
        }

        updateDoctorStatusText();
        updateDoctorStatusCounts(onlineCount, offlineCount);

        updateCount++;
        console.log('✅ Dropdown updated (' + updateCount + 'x) - ' + doctorsData.length + ' doctors');
    }

    function updateDoctorStatusText() {
        var selectedOption = doctorSelect.options[doctorSelect.selectedIndex];
        var doctorStatusText = document.getElementById('doctorStatusText');
        if (selectedOption && selectedOption.dataset && doctorStatusText) {
            var isOnline = parseInt(selectedOption.dataset.online) === 1;
            var doctorName = selectedOption.dataset.name || 'Doctor';
            if (isOnline) {
                doctorStatusText.innerHTML = '<i class="fas fa-check-circle" style="color:#059669;"></i> Dr. ' + doctorName + ' is <strong style="color:#059669;">Online</strong> and available';
            } else {
                doctorStatusText.innerHTML = '<i class="fas fa-clock" style="color:#D97706;"></i> Dr. ' + doctorName + ' is <strong style="color:#D97706;">Offline</strong> - may not respond immediately';
            }
        }
    }

    function updateDoctorStatusCounts(onlineCount, offlineCount) {
        var onlineDisplay  = document.getElementById('onlineCountDisplay');
        var offlineDisplay = document.getElementById('offlineCountDisplay');
        if (onlineDisplay)  onlineDisplay.textContent  = onlineCount;
        if (offlineDisplay) offlineDisplay.textContent = offlineCount;
    }

    function fetchDoctorStatus() {
        if (isUpdating) return;
        isUpdating = true;
        var timestamp = new Date().getTime();
        var url = '/dispensary_system/frontend/api/get_online_doctors.php?branch_id=' + encodeURIComponent(currentBranchId) + '&t=' + timestamp;

        fetch(url, {
            method: 'GET',
            cache: 'no-cache',
            headers: { 'Cache-Control': 'no-cache, no-store, must-revalidate', 'Pragma': 'no-cache' }
        })
        .then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function (data) {
            retryCount = 0;
            if (data.success) {
                updateDoctorDropdown(data.doctors);
                var onlineCount = data.online_count || 0;

                var onlineCountEl = document.getElementById('onlineDoctorCount');
                if (onlineCountEl) onlineCountEl.textContent = onlineCount;

                var onlineDoctorsText = document.getElementById('onlineDoctorsText');
                if (onlineDoctorsText) onlineDoctorsText.textContent = '(' + onlineCount + ' online)';

                var onlineDoctorsStatTime = document.getElementById('onlineDoctorsStatTime');
                if (onlineDoctorsStatTime) onlineDoctorsStatTime.textContent = onlineCount + ' online';

                var totalDoctorsStat = document.getElementById('totalDoctorsStat');
                if (totalDoctorsStat) totalDoctorsStat.textContent = data.total_doctors || 0;

                var timeStr = getCurrentTime();
                var updateBadge = document.getElementById('updateBadge');
                if (updateBadge) {
                    updateBadge.innerHTML = '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + timeStr;
                }
                var footerTs = document.getElementById('footerTimestamp');
                if (footerTs) footerTs.textContent = 'Last updated: ' + timeStr;
            }
            isUpdating = false;
        })
        .catch(function (error) {
            console.error('❌ Doctor status error:', error);
            isUpdating = false;
            retryCount++;
            if (retryCount < maxRetries) {
                setTimeout(fetchDoctorStatus, 3000);
            } else {
                var updateBadge = document.getElementById('updateBadge');
                if (updateBadge) {
                    updateBadge.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#F59E0B;"></i> Offline';
                }
                retryCount = 0;
            }
        });
    }

    // Doctor select change
    if (doctorSelect) {
        doctorSelect.addEventListener('change', updateDoctorStatusText);
    }

    // Auto-update start/stop
    function startAutoUpdate() {
        if (updateInterval) { clearInterval(updateInterval); updateInterval = null; }
        setTimeout(fetchDoctorStatus, 1000);
        updateInterval = setInterval(fetchDoctorStatus, 3000);
    }
    function stopAutoUpdate() {
        if (updateInterval) { clearInterval(updateInterval); updateInterval = null; }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) stopAutoUpdate();
        else startAutoUpdate();
    });

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function () {
        calculateBMI();
        setTimeout(function () {
            updateDoctorStatusText();
            startAutoUpdate();
        }, 500);
    });

    // Footer clock
    setInterval(function () {
        var now = new Date();
        var t = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var ftEl = document.getElementById('footerTimestamp');
        if (ftEl) ftEl.textContent = 'Last updated: ' + t;
        var formTs = document.getElementById('formTimestamp');
        if (formTs) formTs.textContent = t;
    }, 1000);

    console.log('%c📅 Braick - New Appointment (7 Vital Signs)', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
    console.log('%c✅ Inatumia shared header + sidebar', 'font-size:13px;color:#059669;font-weight:bold;');
    console.log('%c👥 Patients: <?= count($patients) ?>', 'font-size:13px;color:#64748B;');
    console.log('%c👨‍⚕️ Doctors: <?= $total_doctors ?> (<?= $online_doctors ?> online)', 'font-size:13px;color:#64748B;');
    console.log('%c🔄 Auto-update: Every 3 seconds via AJAX', 'font-size:13px;color:#34D399;');
</script>

</body>
</html>