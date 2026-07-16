<?php
// ================================================================
// FILE: frontend/pages/reception/new_patient.php
// RECEPTION - REGISTER NEW PATIENT (WITH AUTO BILL)
// REGISTRATION FEE IN BACKGROUND - HIDDEN FROM RECEPTION
// WITH GLOBAL STATS AUTO-UPDATE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Rose Mwangi (Reception)
// ================================================================
$_SESSION['user_id'] = 6;
$_SESSION['full_name'] = 'Rose Mwangi';
$_SESSION['role'] = 'reception';
$_SESSION['branch_id'] = 1;
$_SESSION['branch_name'] = 'Dodoma';
$_SESSION['username'] = 'reception.rose';
$_SESSION['is_admin'] = false;

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$message = '';
$message_type = '';

try {
    $db = getDB();
    
    // ================================================================
    // GET REGISTRATION FEE FROM SERVICES (HIDDEN FROM UI)
    // ================================================================
    $registration_fee = 0;
    $registration_service_name = 'Registration Fee';
    $registration_service_id = null;
    
    try {
        $stmt = $db->prepare("SELECT id FROM service_categories WHERE category_name = 'Registration' LIMIT 1");
        $stmt->execute();
        $reg_category = $stmt->fetch();
        
        if ($reg_category) {
            $stmt = $db->prepare("
                SELECT id, service_name, price 
                FROM services 
                WHERE category_id = ? AND is_active = 1 
                ORDER BY price ASC 
                LIMIT 1
            ");
            $stmt->execute([$reg_category['id']]);
            $registration_service = $stmt->fetch();
            
            if ($registration_service) {
                $registration_fee = $registration_service['price'];
                $registration_service_name = $registration_service['service_name'];
                $registration_service_id = $registration_service['id'];
            }
        }
        
        if ($registration_fee == 0) {
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'registration_fee' LIMIT 1");
            $stmt->execute();
            $setting = $stmt->fetch();
            if ($setting) {
                $registration_fee = (float)$setting['setting_value'];
            }
        }
        
    } catch (Exception $e) {
        error_log("Registration fee fetch error: " . $e->getMessage());
    }
    
    // Get branches (only user's branch)
    $branches = [];
    $branch = getBranch($selected_branch_id);
    if ($branch) {
        $branches[] = $branch;
    }
    
    // Generate patient ID
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM patients WHERE branch_id = ?");
    $stmt->execute([$selected_branch_id]);
    $count = $stmt->fetch()['total'] ?? 0;
    $next_id = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    $patient_id_number = 'P-' . date('Y') . '-' . $next_id;
    
    // ================================================================
    // GET ALL DOCTORS IN THIS BRANCH
    // ================================================================
    $stmt = $db->prepare("
        SELECT id, full_name, specialty, is_online, last_online
        FROM users 
        WHERE role = 'doctor' 
        AND status = 'active' 
        AND branch_id = ?
        ORDER BY is_online DESC, full_name
    ");
    $stmt->execute([$selected_branch_id]);
    $all_doctors = $stmt->fetchAll();
    
    $online_doctors = [];
    $offline_doctors = [];
    foreach ($all_doctors as $doc) {
        if ($doc['is_online'] == 1) {
            $online_doctors[] = $doc;
        } else {
            $offline_doctors[] = $doc;
        }
    }
    
    $online_doctors_count = count($online_doctors);
    $total_doctors = count($all_doctors);
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = trim($_POST['full_name'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? null;
        $gender = $_POST['gender'] ?? null;
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');
        $blood_group = $_POST['blood_group'] ?? null;
        $allergies = trim($_POST['allergies'] ?? '');
        $branch_id = $selected_branch_id;
        $doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
        $patient_type = $_POST['patient_type'] ?? 'new';
        
        $errors = [];
        if (empty($full_name)) $errors[] = 'Full name is required';
        if (empty($gender)) $errors[] = 'Gender is required';
        if (empty($phone)) $errors[] = 'Phone number is required';
        
        if ($doctor_id > 0) {
            $stmt = $db->prepare("SELECT id, is_online FROM users WHERE id = ? AND role = 'doctor' AND status = 'active' AND branch_id = ?");
            $stmt->execute([$doctor_id, $selected_branch_id]);
            $doctor_check = $stmt->fetch();
            if (!$doctor_check) {
                $errors[] = 'Selected doctor is not available.';
                $doctor_id = 0;
            }
        }
        
        if (empty($errors)) {
            $stmt = $db->prepare("
                INSERT INTO patients (
                    patient_id, full_name, date_of_birth, gender, phone, email, 
                    address, emergency_contact, blood_group, allergies, branch_id, 
                    assigned_doctor_id, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $assigned_doctor = $doctor_id > 0 ? $doctor_id : null;
            
            if ($stmt->execute([
                $patient_id_number, $full_name, $date_of_birth, $gender, $phone, $email,
                $address, $emergency_contact, $blood_group, $allergies, $branch_id,
                $assigned_doctor, $_SESSION['user_id']
            ])) {
                $patient_db_id = $db->lastInsertId();
                
                $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad($patient_db_id, 4, '0', STR_PAD_LEFT);
                $visit_status = ($doctor_id > 0) ? 'assigned' : 'pending';
                
                $stmt = $db->prepare("
                    INSERT INTO visits (
                        visit_number, patient_id, doctor_id, receptionist_id, 
                        branch_id, visit_type, status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $visit_number, 
                    $patient_db_id, 
                    ($doctor_id > 0 ? $doctor_id : null), 
                    $_SESSION['user_id'], 
                    $branch_id, 
                    $patient_type, 
                    $visit_status
                ]);
                $visit_id = $db->lastInsertId();
                
                if ($registration_fee > 0) {
                    $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_db_id, 6, '0', STR_PAD_LEFT);
                    
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO patient_bills (
                                bill_number, patient_id, visit_id, 
                                registration_fee, subtotal, total_amount, balance, 
                                status, created_by, branch_id, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
                        ");
                        $subtotal = $registration_fee;
                        $stmt->execute([
                            $bill_number,
                            $patient_db_id,
                            $visit_id,
                            $registration_fee,
                            $subtotal,
                            $subtotal,
                            $subtotal,
                            $_SESSION['user_id'],
                            $branch_id
                        ]);
                        $bill_id = $db->lastInsertId();
                        
                        $stmt = $db->prepare("
                            INSERT INTO bill_items (
                                bill_id, item_type, item_id, item_name, 
                                quantity, unit_price, total_price, created_at
                            ) VALUES (?, 'registration', ?, ?, 1, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $bill_id,
                            $registration_service_id,
                            $registration_service_name,
                            $registration_fee,
                            $registration_fee
                        ]);
                        
                        $_SESSION['current_bill_id'] = $bill_id;
                        
                    } catch (Exception $e) {
                        error_log("Bill creation failed: " . $e->getMessage());
                    }
                }
                
                $_SESSION['current_patient_id'] = $patient_db_id;
                $_SESSION['current_visit_id'] = $visit_id;
                
                $doctor_name = 'Not Assigned';
                foreach ($all_doctors as $doc) {
                    if ($doc['id'] == $doctor_id) {
                        $doctor_name = $doc['full_name'] . ($doc['is_online'] ? ' 🟢' : ' ⚪');
                        break;
                    }
                }
                
                try {
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'patient_registered', ?, NOW())");
                    $stmt->execute([$_SESSION['user_id'], "New patient registered: $full_name (ID: $patient_id_number) assigned to $doctor_name in $branch_name"]);
                } catch (Exception $e) {}
                
                $message = "✅ Patient registered successfully!";
                $message .= "<br>📋 Patient ID: <strong>$patient_id_number</strong>";
                $message .= "<br>👨‍⚕️ Assigned to: <strong>" . htmlspecialchars($doctor_name) . "</strong>";
                if ($doctor_id > 0) {
                    $message .= " <span class='text-green-600'>🟢 Online</span>";
                } else {
                    $message .= " <span class='text-gray-400'>⏳ Waiting for assignment</span>";
                }
                $message_type = 'success';
                
                echo '<script>
                    setTimeout(function(){ 
                        window.location.href = "patients.php"; 
                    }, 3000);
                </script>';
                
            } else {
                $message = "❌ Failed to register patient!";
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
    $branches = [];
    $all_doctors = [];
    $online_doctors = [];
    $offline_doctors = [];
    $total_doctors = 0;
    $online_doctors_count = 0;
}

// ================================================================
// COMMON ALLERGIES LIST
// ================================================================
$common_allergies = [
    'Penicillin' => 'Penicillin',
    'Sulfa Drugs' => 'Sulfa Drugs',
    'Aspirin' => 'Aspirin',
    'Ibuprofen' => 'Ibuprofen',
    'Codeine' => 'Codeine',
    'Latex' => 'Latex',
    'Peanuts' => 'Peanuts',
    'Shellfish' => 'Shellfish',
    'Eggs' => 'Eggs',
    'Milk' => 'Milk',
    'Wheat' => 'Wheat',
    'Soy' => 'Soy',
    'Dust' => 'Dust',
    'Pollen' => 'Pollen',
    'Animal Dander' => 'Animal Dander'
];

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/reception_header.php';
include_once __DIR__ . '/../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New Patient - Braick Dispensary</title>
    
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
           ALLERGIES CHECKBOXES
           ================================================================ */
        .allergy-checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 6px;
        }
        
        .allergy-checkbox-group .allergy-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 14px 4px 10px;
            border-radius: 20px;
            border: 2px solid var(--border-color);
            background: var(--bg-body);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.73rem;
            color: var(--text-secondary);
            user-select: none;
        }
        
        .allergy-checkbox-group .allergy-chip:hover {
            border-color: var(--primary);
            background: var(--primary-bg);
            transform: translateY(-1px);
        }
        
        .allergy-checkbox-group .allergy-chip input[type="checkbox"] {
            display: none;
        }
        
        .allergy-checkbox-group .allergy-chip.active {
            border-color: var(--danger);
            background: var(--danger-bg);
            color: var(--danger-dark);
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.15);
        }
        
        .allergy-checkbox-group .allergy-chip .allergy-icon {
            font-size: 0.6rem;
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
           BADGES
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
            color: var(--primary);
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
            .allergy-checkbox-group .allergy-chip { font-size: 0.65rem; padding: 3px 10px; }
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
        
        .offline-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #94A3B8;
        }
        
        .update-badge {
            font-size: 0.6rem;
            color: var(--text-secondary);
            background: var(--bg-body);
            padding: 2px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
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
            <input type="text" id="searchInput" placeholder="Search patients by name, ID or phone...">
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
                <i class="fas fa-user-plus"></i>
                Register New Patient
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Create a new patient record in <strong><?= htmlspecialchars($branch_name) ?></strong>
                
                <span class="header-badge" id="onlineDoctorBadge">
                    <i class="fas fa-user-md"></i>
                    <span class="online-count" id="onlineDoctorCount"><?= $online_doctors_count ?></span> Online 
                    / <?= $total_doctors ?> Total
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-id-card"></i>
                    Next ID: <strong><?= $patient_id_number ?></strong>
                </span>
                
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </p>
        </div>
        <div class="header-right">
            <a href="patients.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- REGISTRATION FEE - HIDDEN FROM UI (NO DISPLAY) -->
    <!-- ================================================================ -->

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>" style="max-width:1000px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div class="alert-content"><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- REGISTRATION FORM -->
    <!-- ================================================================ -->
    <div class="form-card animate-fade-in-up">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <div>
                <h3 class="form-title">Patient Registration Form</h3>
                <p class="form-subtitle">Fill in the patient details below to complete registration</p>
            </div>
        </div>
        
        <form method="POST" action="" id="registrationForm">
            <!-- ============================================================ -->
            <!-- ROW 1: Full Name (Full Width) -->
            <!-- ============================================================ -->
            <div class="form-row grid-full">
                <label class="form-label">
                    <i class="fas fa-user label-icon"></i> Full Name <span class="required">*</span>
                </label>
                <input type="text" name="full_name" class="form-control" placeholder="Enter full name (e.g. John Doe)" 
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
            </div>
            
            <!-- ============================================================ -->
            <!-- ROW 2: Patient Type + Gender -->
            <!-- ============================================================ -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-tag label-icon"></i> Patient Type
                    </label>
                    <select name="patient_type" class="form-control">
                        <option value="new" <?= ($_POST['patient_type'] ?? 'new') === 'new' ? 'selected' : '' ?>>🆕 New Patient</option>
                        <option value="follow-up" <?= ($_POST['patient_type'] ?? '') === 'follow-up' ? 'selected' : '' ?>>🔄 Follow-up</option>
                        <option value="emergency" <?= ($_POST['patient_type'] ?? '') === 'emergency' ? 'selected' : '' ?>>🚨 Emergency</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-venus-mars label-icon"></i> Gender <span class="required">*</span>
                    </label>
                    <select name="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>👨 Male</option>
                        <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>👩 Female</option>
                        <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>⚧ Other</option>
                    </select>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- ROW 3: Date of Birth + Phone -->
            <!-- ============================================================ -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-calendar label-icon"></i> Date of Birth
                    </label>
                    <input type="date" name="date_of_birth" class="form-control" 
                           value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-phone label-icon"></i> Phone Number <span class="required">*</span>
                    </label>
                    <input type="tel" name="phone" class="form-control" placeholder="e.g. 0759 154 160" 
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- ROW 4: Email + Blood Group -->
            <!-- ============================================================ -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-envelope label-icon"></i> Email
                    </label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. john@example.com" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-tint label-icon"></i> Blood Group
                    </label>
                    <select name="blood_group" class="form-control">
                        <option value="">Select Blood Group</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                            <option value="<?= $bg ?>" <?= ($_POST['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- ROW 5: Address (Full Width) -->
            <!-- ============================================================ -->
            <div class="form-row grid-full">
                <label class="form-label">
                    <i class="fas fa-home label-icon"></i> Address
                </label>
                <textarea name="address" class="form-control" placeholder="Enter full address..." rows="2"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>
            
            <!-- ============================================================ -->
            <!-- ROW 6: Emergency Contact + Assign Doctor -->
            <!-- ============================================================ -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-phone-alt label-icon"></i> Emergency Contact
                    </label>
                    <input type="tel" name="emergency_contact" class="form-control" placeholder="e.g. 0755 123 456" 
                           value="<?= htmlspecialchars($_POST['emergency_contact'] ?? '') ?>">
                </div>
                
                <div class="form-row" id="doctorSelectContainer">
                    <label class="form-label">
                        <i class="fas fa-user-md label-icon"></i> Assign Doctor
                        <span class="text-xs font-normal text-gray-400">(Optional)</span>
                    </label>
                    <select name="doctor_id" class="form-control" id="doctorSelect">
                        <option value="">-- No Doctor Assigned --</option>
                        
                        <?php if (!empty($online_doctors)): ?>
                            <optgroup label="🟢 Online Doctors">
                                <?php foreach ($online_doctors as $doctor): ?>
                                    <option value="<?= $doctor['id'] ?>" <?= ($_POST['doctor_id'] ?? '') == $doctor['id'] ? 'selected' : '' ?>>
                                        Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                        <?php if (!empty($doctor['specialty'])): ?>
                                            (<?= htmlspecialchars($doctor['specialty']) ?>)
                                        <?php endif; ?>
                                        - 🟢 Online
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        
                        <?php if (!empty($offline_doctors)): ?>
                            <optgroup label="⚪ Offline Doctors">
                                <?php foreach ($offline_doctors as $doctor): ?>
                                    <option value="<?= $doctor['id'] ?>" <?= ($_POST['doctor_id'] ?? '') == $doctor['id'] ? 'selected' : '' ?>>
                                        Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                        <?php if (!empty($doctor['specialty'])): ?>
                                            (<?= htmlspecialchars($doctor['specialty']) ?>)
                                        <?php endif; ?>
                                        - ⚪ Offline
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                    <div id="doctorStatusMessage" class="mt-1">
                        <?php if ($online_doctors_count > 0): ?>
                            <p class="text-xs text-green-500">
                                <i class="fas fa-check-circle mr-1"></i> 
                                <span id="onlineDoctorCountText"><?= $online_doctors_count ?></span> doctor(s) currently online
                                <?php if (!empty($offline_doctors)): ?>
                                    <span class="text-gray-400">| <?= count($offline_doctors) ?> offline</span>
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <p class="text-xs text-yellow-500">
                                <i class="fas fa-exclamation-triangle mr-1"></i> 
                                No doctors are currently online. You can still register without a doctor.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- ============================================================ -->
            <!-- ROW 7: Allergies (Full Width) -->
            <!-- ============================================================ -->
            <div class="form-row grid-full">
                <label class="form-label">
                    <i class="fas fa-allergies label-icon"></i> Allergies
                    <span class="text-xs font-normal text-gray-400">(Click to select common allergies)</span>
                </label>
                <div class="allergy-checkbox-group" id="allergyCheckboxGroup">
                    <?php foreach ($common_allergies as $key => $label): ?>
                        <label class="allergy-chip" data-allergy="<?= htmlspecialchars($label) ?>">
                            <input type="checkbox" value="<?= htmlspecialchars($label) ?>" class="allergy-checkbox">
                            <span class="allergy-icon">⚠️</span>
                            <?= htmlspecialchars($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <textarea name="allergies" id="allergiesTextarea" class="form-control mt-2" placeholder="List any known allergies..." rows="2"><?= htmlspecialchars($_POST['allergies'] ?? '') ?></textarea>
            </div>
            
            <!-- ============================================================ -->
            <!-- Hidden Fields -->
            <!-- ============================================================ -->
            <input type="hidden" name="branch_id" value="<?= $selected_branch_id ?>">
            
            <!-- ============================================================ -->
            <!-- FORM ACTIONS -->
            <!-- ============================================================ -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="registerBtn">
                    <i class="fas fa-save"></i> Register Patient
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset Form
                </button>
                <a href="patients.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
            
            <!-- ============================================================ -->
            <!-- FOOTER INFO - Registration fee NOT shown -->
            <!-- ============================================================ -->
            <div class="mt-4 pt-3 text-xs text-gray-400 text-center border-t border-gray-200 dark:border-gray-700">
                <i class="fas fa-info-circle mr-1"></i>
                Patient will be registered with ID: <strong><?= $patient_id_number ?></strong>
                <span class="mx-2">|</span>
                Visit status: <strong><?= ($_POST['doctor_id'] ?? 0) > 0 ? 'Assigned' : 'Pending' ?></strong>
                <span class="mx-2">|</span>
                <span id="formTimestamp"><?= date('h:i:s A') ?></span>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK STATS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-5" style="max-width:1000px;margin:24px auto 0;">
        <div class="stat-card" id="onlineDoctorsCard">
            <div class="stat-icon">🟢</div>
            <p class="stat-number" id="onlineDoctorsStat"><?= $online_doctors_count ?></p>
            <p class="stat-label">Online Doctors</p>
            <p class="text-xs text-gray-400" id="onlineDoctorsStatTime">Updated now</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📋</div>
            <p class="stat-number text-purple-600"><?= $patient_id_number ?></p>
            <p class="stat-label">Next Patient ID</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <p class="stat-number text-green-600"><?= date('d M Y') ?></p>
            <p class="stat-label">Today's Date</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Register Patient
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
    // ALLERGIES CHECKBOXES
    // ================================================================
    var allergyChips = document.querySelectorAll('.allergy-chip');
    var allergiesTextarea = document.getElementById('allergiesTextarea');
    
    allergyChips.forEach(function(chip) {
        chip.addEventListener('click', function(e) {
            var checkbox = this.querySelector('.allergy-checkbox');
            var allergyName = this.dataset.allergy;
            
            this.classList.toggle('active');
            checkbox.checked = !checkbox.checked;
            
            var currentValue = allergiesTextarea.value;
            var allergyList = currentValue.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s !== ''; });
            
            if (checkbox.checked) {
                if (!allergyList.includes(allergyName)) {
                    allergyList.push(allergyName);
                }
            } else {
                allergyList = allergyList.filter(function(item) {
                    return item !== allergyName;
                });
            }
            
            allergiesTextarea.value = allergyList.join(', ');
            
            if (checkbox.checked) {
                showToast('✅ Added', allergyName + ' added to allergies', 'info');
            } else {
                showToast('🗑️ Removed', allergyName + ' removed from allergies', 'info');
            }
        });
    });

    function syncAllergyChips() {
        var currentValue = allergiesTextarea.value;
        var allergyList = currentValue.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s !== ''; });
        
        allergyChips.forEach(function(chip) {
            var allergyName = chip.dataset.allergy;
            var checkbox = chip.querySelector('.allergy-checkbox');
            
            if (allergyList.includes(allergyName)) {
                chip.classList.add('active');
                checkbox.checked = true;
            } else {
                chip.classList.remove('active');
                checkbox.checked = false;
            }
        });
    }
    
    allergiesTextarea.addEventListener('input', function() {
        syncAllergyChips();
    });
    
    syncAllergyChips();

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
                    
                    // Update online doctors count
                    document.getElementById('onlineDoctorCount').textContent = onlineCount;
                    document.getElementById('onlineDoctorsStat').textContent = onlineCount;
                    document.getElementById('onlineDoctorCountText').textContent = onlineCount;
                    
                    // Update status message
                    var statusMsg = document.getElementById('doctorStatusMessage');
                    if (onlineCount > 0) {
                        statusMsg.innerHTML = `
                            <p class="text-xs text-green-500">
                                <i class="fas fa-check-circle mr-1"></i> 
                                <span id="onlineDoctorCountText">${onlineCount}</span> doctor(s) currently online
                            </p>
                        `;
                    } else {
                        statusMsg.innerHTML = `
                            <p class="text-xs text-yellow-500">
                                <i class="fas fa-exclamation-triangle mr-1"></i> 
                                No doctors are currently online. You can still register without a doctor.
                            </p>
                        `;
                    }
                    
                    // Update timestamp
                    var now = new Date();
                    document.getElementById('onlineDoctorsStatTime').textContent = 'Updated ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
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

    console.log('%c👤 Braick - New Patient Registration', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Next Patient ID: <?= $patient_id_number ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🟢 Online Doctors: <?= $online_doctors_count ?> / <?= $total_doctors ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔄 Auto-update: Every 3 seconds (Global Stats)', 'font-size:13px; color:#34D399;');
    console.log('%c💡 Registration fee is added silently to the bill', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>