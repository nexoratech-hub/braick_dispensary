<?php
// ================================================================
// FILE: frontend/pages/reception/assign_doctor.php
// RECEPTION - ASSIGN DOCTOR TO PATIENT (BRANCH FILTERED)
// WITH GLOBAL STATS AUTO-UPDATE
// FIXED: Inaonyesha wagonjwa ambao wana visit pending lakini doctor_id = NULL
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

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$message = '';
$message_type = '';

// Initialize variables
$patients = [];
$doctors = [];
$pending_patients = [];
$online_doctors_count = 0;
$total_doctors = 0;

try {
    $db = getDB();
    
    // ================================================================
    // GET PATIENTS WITHOUT DOCTOR ASSIGNED (FIXED)
    // Inaonyesha wagonjwa ambao:
    // 1. Hawana visit yoyote active
    // 2. Wana visit pending lakini doctor_id ni NULL
    // ================================================================
    $stmt = $db->prepare("
        SELECT p.* 
        FROM patients p
        WHERE p.branch_id = ? 
        AND (
            -- No active visit at all
            p.id NOT IN (
                SELECT DISTINCT patient_id 
                FROM visits 
                WHERE status IN ('pending', 'assigned', 'with_doctor') 
                AND branch_id = ?
            )
            OR 
            -- Has pending visit but no doctor assigned
            p.id IN (
                SELECT DISTINCT patient_id 
                FROM visits 
                WHERE status = 'pending' 
                AND doctor_id IS NULL
                AND branch_id = ?
            )
        )
        ORDER BY p.full_name
    ");
    $stmt->execute([$selected_branch_id, $selected_branch_id, $selected_branch_id]);
    $patients = $stmt->fetchAll();
    
    // ================================================================
    // DEBUG: Check if patients are found
    // ================================================================
    error_log("Patients found: " . count($patients));
    
    // ================================================================
    // GET DOCTORS IN THIS BRANCH (SHOW ONLINE FIRST)
    // ================================================================
    $stmt = $db->prepare("
        SELECT id, full_name, specialty, is_online 
        FROM users 
        WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
        ORDER BY is_online DESC, full_name
    ");
    $stmt->execute([$selected_branch_id]);
    $doctors = $stmt->fetchAll();
    
    // Count online doctors
    foreach ($doctors as $doc) {
        if ($doc['is_online'] == 1) {
            $online_doctors_count++;
        }
    }
    $total_doctors = count($doctors);
    
    // ================================================================
    // GET PENDING PATIENTS (Already assigned but not completed)
    // ================================================================
    $stmt = $db->prepare("
        SELECT v.*, p.full_name as patient_name, p.patient_id, p.phone, u.full_name as doctor_name
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.branch_id = ? AND v.status IN ('pending', 'assigned')
        ORDER BY v.created_at ASC
        LIMIT 20
    ");
    $stmt->execute([$selected_branch_id]);
    $pending_patients = $stmt->fetchAll();
    
    // ================================================================
    // HANDLE FORM SUBMISSION
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $patient_id = (int)($_POST['patient_id'] ?? 0);
        $doctor_id = (int)($_POST['doctor_id'] ?? 0);
        $visit_type = $_POST['visit_type'] ?? 'new';
        $symptoms = trim($_POST['symptoms'] ?? '');
        $symptoms_select = $_POST['symptoms_select'] ?? '';
        
        // Combine selected symptoms with manual entry
        if (!empty($symptoms_select) && $symptoms_select !== 'other') {
            $symptoms = $symptoms_select;
        } else {
            $symptoms = trim($_POST['symptoms_other'] ?? $symptoms);
        }
        
        $notes = trim($_POST['notes'] ?? '');
        
        $errors = [];
        if ($patient_id <= 0) $errors[] = 'Please select a patient';
        if ($doctor_id <= 0) $errors[] = 'Please select a doctor';
        
        if (empty($errors)) {
            // Verify doctor exists and is active
            $stmt = $db->prepare("SELECT id, is_online FROM users WHERE id = ? AND role = 'doctor' AND status = 'active' AND branch_id = ?");
            $stmt->execute([$doctor_id, $selected_branch_id]);
            $doctor_check = $stmt->fetch();
            if (!$doctor_check) {
                $errors[] = 'Selected doctor is not available.';
            }
        }
        
        if (empty($errors)) {
            // Check if patient already has a pending visit
            $stmt = $db->prepare("
                SELECT id FROM visits 
                WHERE patient_id = ? AND status = 'pending' AND doctor_id IS NULL AND branch_id = ?
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$patient_id, $selected_branch_id]);
            $existing_visit = $stmt->fetch();
            
            if ($existing_visit) {
                // Update existing visit with doctor
                $visit_id = $existing_visit['id'];
                $stmt = $db->prepare("
                    UPDATE visits 
                    SET doctor_id = ?, status = 'assigned', updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$doctor_id, $visit_id]);
                $message = "✅ Doctor assigned to existing visit!";
                $message_type = 'success';
                
            } else {
                // Create new visit
                $visit_number = 'V-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $db->prepare("
                    INSERT INTO visits (
                        visit_number, patient_id, doctor_id, branch_id, 
                        visit_type, status, symptoms, notes, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, 'assigned', ?, ?, NOW(), NOW())
                ");
                
                if ($stmt->execute([$visit_number, $patient_id, $doctor_id, $selected_branch_id, $visit_type, $symptoms, $notes])) {
                    $visit_id = $db->lastInsertId();
                    $message = "✅ Doctor assigned successfully! Visit #$visit_number";
                    $message_type = 'success';
                } else {
                    $message = "❌ Failed to assign doctor!";
                    $message_type = 'error';
                }
            }
            
            if ($message_type === 'success') {
                // Update patient assigned_doctor_id
                $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = ? WHERE id = ?");
                $stmt->execute([$doctor_id, $patient_id]);
                
                // Log activity
                try {
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'doctor_assigned', ?, NOW())");
                    $stmt->execute([$_SESSION['user_id'], "Doctor assigned to patient ID: $patient_id in $branch_name"]);
                } catch (Exception $e) {}
                
                // Refresh page after 2 seconds
                echo '<script>
                    showToast("✅ Success", "' . $message . '", "success");
                    setTimeout(function(){ window.location.href = "assign_doctor.php?success=1"; }, 2000);
                </script>';
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
    $pending_patients = [];
}

// ================================================================
// COMMON SYMPTOMS LIST
// ================================================================
$common_symptoms = [
    'Fever' => 'Fever',
    'Headache' => 'Headache',
    'Cough' => 'Cough',
    'Sore Throat' => 'Sore Throat',
    'Body Pain' => 'Body Pain',
    'Fatigue' => 'Fatigue',
    'Nausea' => 'Nausea',
    'Vomiting' => 'Vomiting',
    'Diarrhea' => 'Diarrhea',
    'Chest Pain' => 'Chest Pain',
    'Shortness of Breath' => 'Shortness of Breath',
    'Abdominal Pain' => 'Abdominal Pain',
    'Dizziness' => 'Dizziness',
    'Rash' => 'Rash',
    'Swelling' => 'Swelling'
];

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
    <title>Assign Doctor - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
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
        
        /* ================================================================
           TOP NAV
           ================================================================ */
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
        
        .notif-dot.has-notif {
            background: var(--danger);
        }
        
        .notif-dot.no-notif {
            background: var(--gray-400);
            animation: none;
        }
        
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
        
        .dark-toggle-btn i {
            font-size: 0.9rem;
        }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
        /* ================================================================
           PAGE HEADER - IMPROVED
           ================================================================ */
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
        
        .page-header .header-badge i {
            font-size: 0.7rem;
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
        
        /* ================================================================
           FORM CARD
           ================================================================ */
        .form-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 32px 36px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 1000px;
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
        
        /* ================================================================
           PENDING PATIENTS CARD
           ================================================================ */
        .pending-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 14px 18px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .pending-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        
        .pending-card .name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
        }
        
        .pending-card .id {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .pending-card .doctor {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .pending-card .status-badge {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 3px 14px;
            border-radius: 12px;
        }
        
        .pending-card .status-badge.pending { 
            background: #FEF3C7; 
            color: #D97706; 
        }
        
        .pending-card .status-badge.assigned { 
            background: #E8F0FE; 
            color: #0B5ED7; 
        }
        
        [data-theme="dark"] .pending-card .status-badge.pending { 
            background: #3D2E0A; 
            color: #FBBF24; 
        }
        
        [data-theme="dark"] .pending-card .status-badge.assigned { 
            background: #1E3A5F; 
            color: #6EA8FE; 
        }
        
        .pending-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .pending-list::-webkit-scrollbar {
            width: 4px;
        }
        
        .pending-list::-webkit-scrollbar-track {
            background: var(--bg-body);
            border-radius: 4px;
        }
        
        .pending-list::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
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
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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
        
        .btn-sm { 
            padding: 5px 14px; 
            font-size: 0.75rem; 
            border-radius: 8px; 
        }
        
        /* ================================================================
           BADGES & CARDS
           ================================================================ */
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
        
        .card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-orange { 
            color: #D97706; 
        }
        
        .card-title .title-blue { 
            color: var(--primary); 
        }
        
        /* ================================================================
           STATS CARD
           ================================================================ */
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
        
        .stat-card .stat-number.primary {
            color: var(--primary);
        }
        
        .stat-card .stat-number.green {
            color: var(--success);
        }
        
        .stat-card .stat-number.orange {
            color: #D97706;
        }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .stat-card .stat-icon {
            font-size: 1.4rem;
            margin-bottom: 4px;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
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
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        
        .footer .footer-brand { 
            color: var(--primary); 
            font-weight: 600; 
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .form-card { padding: 20px; }
            .page-header { padding: 18px 20px; }
            .page-header .page-title { font-size: 1.4rem; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .form-card { padding: 14px; }
            .form-card .form-header .form-title { font-size: 1rem; }
            .page-header { padding: 14px 16px; flex-direction: column; align-items: stretch; }
            .page-header .page-title { font-size: 1.2rem; }
            .page-header .page-subtitle { font-size: 0.8rem; }
            .page-header .header-right { margin-top: 8px; }
            .pending-card { flex-direction: column; text-align: center; gap: 8px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .form-card { padding: 12px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        .online-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #059669;
            animation: pulse-dot 1.5s infinite;
        }
        
        /* ================================================================
           MESSAGE ALERT
           ================================================================ */
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
        
        /* ================================================================
           GRID HELPERS
           ================================================================ */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        
        .grid-full {
            grid-column: 1 / -1;
        }
        
        @media (max-width: 640px) {
            .grid-2 {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
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

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER - IMPROVED -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md"></i>
                Assign Doctor
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Assign a doctor to a patient in <strong><?= htmlspecialchars($branch_name) ?></strong>
                
                <span class="header-badge" id="onlineDoctorBadge">
                    <i class="fas fa-user-md"></i>
                    <span class="online-count" id="onlineDoctorCount"><?= $online_doctors_count ?></span> Online 
                    / <?= $total_doctors ?> Total
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    <span id="waitingPatientsCount"><?= is_array($patients) ? count($patients) : 0 ?></span> Waiting
                </span>
                
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </p>
        </div>
        <div class="header-right">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>" style="max-width:1000px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div class="alert-content"><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PENDING PATIENTS (In Progress) -->
    <!-- ================================================================ -->
    <?php if (!empty($pending_patients) && is_array($pending_patients) && count($pending_patients) > 0): ?>
    <div class="card mb-5" style="max-width:1000px;margin:0 auto 20px;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-clock title-orange mr-2"></i> Currently Assigned / Pending
                <span class="text-sm font-normal text-gray-400">(<?= count($pending_patients) ?> patients)</span>
            </h3>
            <span class="text-xs text-gray-400">
                <i class="fas fa-arrow-right mr-1"></i> In queue
            </span>
        </div>
        <div class="pending-list">
            <?php foreach ($pending_patients as $patient): ?>
                <div class="pending-card">
                    <div>
                        <p class="name"><?= htmlspecialchars($patient['patient_name']) ?></p>
                        <p class="id">
                            <i class="fas fa-id-card mr-1"></i>
                            <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?> 
                            <span class="mx-1">•</span>
                            <i class="fas fa-phone mr-1"></i>
                            <?= htmlspecialchars($patient['phone'] ?? 'No phone') ?>
                        </p>
                        <p class="doctor">
                            <i class="fas fa-user-md mr-1"></i>
                            Dr. <?= htmlspecialchars($patient['doctor_name'] ?? 'Not assigned') ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="status-badge <?= $patient['status'] ?>"><?= ucfirst($patient['status']) ?></span>
                        <div class="mt-1">
                            <a href="visit_details.php?id=<?= $patient['id'] ?>" class="text-primary text-xs hover:underline">
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ASSIGN FORM -->
    <!-- ================================================================ -->
    <div class="form-card animate-fade-in-up">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-stethoscope"></i>
            </div>
            <div>
                <h3 class="form-title">Assign Doctor to Patient</h3>
                <p class="form-subtitle">Select a patient and a doctor to assign</p>
            </div>
        </div>
        
        <form method="POST" action="" id="assignForm">
            <!-- ============================================================ -->
            <!-- ROW 1: Patient + Doctor -->
            <!-- ============================================================ -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-user label-icon"></i> Patient <span class="required">*</span>
                    </label>
                    <select name="patient_id" class="form-control" required id="patientSelect">
                        <option value="">-- Select Patient --</option>
                        <?php if (!empty($patients) && is_array($patients) && count($patients) > 0): ?>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?= $patient['id'] ?>" <?= ($_GET['patient_id'] ?? 0) == $patient['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($patient['full_name']) ?> 
                                    (<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)
                                    <?php if (!empty($patient['phone'])): ?>
                                        - <?= htmlspecialchars($patient['phone']) ?>
                                    <?php endif; ?>
                                    <?php if (isset($patient['assigned_doctor_id']) && !empty($patient['assigned_doctor_id'])): ?>
                                        - ✅ Assigned
                                    <?php else: ?>
                                        - ⏳ Waiting
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No patients waiting for assignment</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($patients) || !is_array($patients) || count($patients) == 0): ?>
                        <p class="text-xs text-green-500 mt-1">
                            <i class="fas fa-check-circle mr-1"></i> 
                            All patients have been assigned a doctor.
                        </p>
                    <?php else: ?>
                        <p class="text-xs text-gray-400 mt-1">
                            <i class="fas fa-info-circle mr-1"></i> 
                            <strong><?= count($patients) ?></strong> patient(s) waiting for assignment
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-user-md label-icon"></i> Doctor <span class="required">*</span>
                    </label>
                    <select name="doctor_id" class="form-control" required id="doctorSelect">
                        <option value="">-- Select Doctor --</option>
                        <?php if (!empty($doctors) && is_array($doctors) && count($doctors) > 0): ?>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?= $doctor['id'] ?>">
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
                        <?php else: ?>
                            <option value="" disabled>No doctors available</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($doctors) || !is_array($doctors) || count($doctors) == 0): ?>
                        <p class="text-xs text-red-500 mt-1">
                            <i class="fas fa-exclamation-circle mr-1"></i> 
                            No doctors available in <?= htmlspecialchars($branch_name) ?>
                        </p>
                    <?php else: ?>
                        <p class="text-xs text-gray-400 mt-1">
                            <i class="fas fa-info-circle mr-1"></i> 
                            <?= count($doctors) ?> doctor(s) available
                            <span class="text-green-500">(<?= $online_doctors_count ?> online)</span>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- ROW 2: Visit Type + Symptoms Select -->
            <!-- ============================================================ -->
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
                        <i class="fas fa-notes-medical label-icon"></i> Common Symptoms
                    </label>
                    <select name="symptoms_select" class="form-control" id="symptomsSelect">
                        <option value="">-- Select Common Symptom --</option>
                        <?php foreach ($common_symptoms as $key => $symptom): ?>
                            <option value="<?= htmlspecialchars($symptom) ?>">
                                <?= htmlspecialchars($symptom) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="other">✏️ Other (Type below)</option>
                    </select>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- ROW 3: Symptoms Textarea + Notes -->
            <!-- ============================================================ -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-file-medical label-icon"></i> Symptoms Details
                    </label>
                    <textarea name="symptoms_other" class="form-control" placeholder="Describe patient symptoms in detail..." id="symptomsTextarea" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-sticky-note label-icon"></i> Additional Notes
                    </label>
                    <textarea name="notes" class="form-control" placeholder="Any additional notes..." id="notesInput" rows="3"></textarea>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- FORM ACTIONS -->
            <!-- ============================================================ -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="assignBtn" <?= (empty($patients) || !is_array($patients) || count($patients) == 0 || empty($doctors) || !is_array($doctors) || count($doctors) == 0) ? 'disabled' : '' ?>>
                    <i class="fas fa-user-md"></i> Assign Doctor
                </button>
                <button type="reset" class="btn btn-outline" id="resetBtn">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
            
            <!-- ============================================================ -->
            <!-- FOOTER INFO -->
            <!-- ============================================================ -->
            <div class="mt-4 pt-3 text-xs text-gray-400 text-center border-t border-gray-200 dark:border-gray-700">
                <i class="fas fa-info-circle mr-1"></i>
                Assign a doctor to start the patient's visit
                <span class="mx-2">|</span>
                <span id="formTimestamp"><?= date('h:i:s A') ?></span>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK STATS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-5" style="max-width:1000px;margin:24px auto 0;">
        <div class="stat-card" id="waitingStats">
            <div class="stat-icon">⏳</div>
            <p class="stat-number primary" id="waitingPatientsStat"><?= is_array($patients) ? count($patients) : 0 ?></p>
            <p class="stat-label">Patients Waiting</p>
        </div>
        <div class="stat-card" id="doctorStats">
            <div class="stat-icon">👨‍⚕️</div>
            <p class="stat-number green" id="availableDoctorsStat"><?= is_array($doctors) ? count($doctors) : 0 ?></p>
            <p class="stat-label">Doctors Available</p>
            <p class="text-xs text-gray-400" id="onlineDoctorsStatTime"><?= $online_doctors_count ?> online</p>
        </div>
        <div class="stat-card" id="progressStats">
            <div class="stat-icon">📋</div>
            <p class="stat-number orange" id="inProgressStat"><?= is_array($pending_patients) ? count($pending_patients) : 0 ?></p>
            <p class="stat-label">In Progress</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Assign Doctor
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
        document.getElementById('formTimestamp').textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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
    // SYMPTOMS SELECT - Auto fill textarea
    // ================================================================
    var symptomsSelect = document.getElementById('symptomsSelect');
    var symptomsTextarea = document.getElementById('symptomsTextarea');
    
    symptomsSelect?.addEventListener('change', function() {
        var value = this.value;
        if (value && value !== 'other') {
            // Fill textarea with selected symptom
            var currentValue = symptomsTextarea.value.trim();
            if (currentValue) {
                symptomsTextarea.value = currentValue + ', ' + value;
            } else {
                symptomsTextarea.value = value;
            }
            // Reset select to default after adding
            // this.value = '';
        } else if (value === 'other') {
            symptomsTextarea.focus();
        }
    });

    // ================================================================
    // GLOBAL STATS AUTO-UPDATE (3 SECONDS)
    // ================================================================
    var updateInterval = null;
    
    function fetchGlobalStats() {
        fetch('/dispensary_system/frontend/api/get_global_stats.php?t=' + new Date().getTime())
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var stats = data.stats || {};
                    var onlineCount = stats.online_doctors || 0;
                    var pendingVisits = stats.pending_visits || 0;
                    
                    // Update online doctors count
                    document.getElementById('onlineDoctorCount').textContent = onlineCount;
                    document.getElementById('onlineDoctorsStatTime').textContent = onlineCount + ' online';
                    
                    // Update waiting patients count
                    document.getElementById('waitingPatientsCount').textContent = pendingVisits;
                    document.getElementById('waitingPatientsStat').textContent = pendingVisits;
                    
                    // Update update badge
                    var now = new Date();
                    document.getElementById('updateBadge').innerHTML = '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                }
            })
            .catch(function(error) {
                console.error('Error fetching global stats:', error);
            });
    }

    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        fetchGlobalStats();
        updateInterval = setInterval(fetchGlobalStats, 3000);
    }

    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 1000);
    });

    console.log('%c👨‍⚕️ Braick - Assign Doctor (FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👥 Patients Waiting: <?= is_array($patients) ? count($patients) : 0 ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👨‍⚕️ Doctors Available: <?= is_array($doctors) ? count($doctors) : 0 ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🔄 Auto-update: Every 3 seconds (Global Stats)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Includes patients with pending visit + no doctor assigned', 'font-size:13px; color:#059669;');
</script>

</body>
</html>