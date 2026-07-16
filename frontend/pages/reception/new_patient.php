<?php
// ================================================================
// FILE: frontend/pages/reception/new_patient.php
// RECEPTION - REGISTER NEW PATIENT (WITH AUTO BILL)
// REGISTRATION FEE IN BACKGROUND - CASHIER ANAONA
// PATIENT ANAENDA KWA DOCTOR WA BRANCH HUSIKA
// ONLY ONLINE DOCTORS ARE SHOWN
// WITH AJAX AUTO-UPDATE (3 SECONDS) - ONLINE DOCTORS COUNT
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
    // GET REGISTRATION FEE FROM SERVICES
    // ================================================================
    $registration_fee = 0;
    $registration_service_name = 'Registration Fee';
    $registration_service_id = null;
    
    try {
        // First get the registration category ID
        $stmt = $db->prepare("SELECT id FROM service_categories WHERE category_name = 'Registration' LIMIT 1");
        $stmt->execute();
        $reg_category = $stmt->fetch();
        
        if ($reg_category) {
            // Then get the service under that category
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
        
        // If no service found, check system_settings for registration_fee
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
    // GET ONLINE DOCTORS IN THIS BRANCH ONLY
    // ================================================================
    $stmt = $db->prepare("
        SELECT id, full_name, specialty, is_online, last_online
        FROM users 
        WHERE role = 'doctor' 
        AND status = 'active' 
        AND branch_id = ?
        AND is_online = 1
        ORDER BY full_name
    ");
    $stmt->execute([$selected_branch_id]);
    $doctors = $stmt->fetchAll();
    
    // ================================================================
    // GET ALL DOCTORS COUNT (FOR DISPLAY)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM users 
        WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
    ");
    $stmt->execute([$selected_branch_id]);
    $total_doctors = $stmt->fetch()['total'] ?? 0;
    
    // ================================================================
    // GET ONLINE DOCTORS COUNT
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM users 
        WHERE role = 'doctor' AND status = 'active' AND branch_id = ? AND is_online = 1
    ");
    $stmt->execute([$selected_branch_id]);
    $online_doctors_count = $stmt->fetch()['total'] ?? 0;
    
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
        
        // Validation
        $errors = [];
        if (empty($full_name)) $errors[] = 'Full name is required';
        if (empty($gender)) $errors[] = 'Gender is required';
        if (empty($phone)) $errors[] = 'Phone number is required';
        if ($doctor_id <= 0) $errors[] = 'Please select an online doctor';
        
        // Verify doctor is online
        if ($doctor_id > 0) {
            $stmt = $db->prepare("SELECT is_online FROM users WHERE id = ? AND role = 'doctor' AND branch_id = ?");
            $stmt->execute([$doctor_id, $selected_branch_id]);
            $doctor_check = $stmt->fetch();
            if (!$doctor_check || $doctor_check['is_online'] != 1) {
                $errors[] = 'Selected doctor is not online. Please select an online doctor.';
                $doctor_id = 0;
            }
        }
        
        if (empty($errors)) {
            // ================================================================
            // 1. INSERT PATIENT
            // ================================================================
            $stmt = $db->prepare("
                INSERT INTO patients (
                    patient_id, full_name, date_of_birth, gender, phone, email, 
                    address, emergency_contact, blood_group, allergies, branch_id, 
                    assigned_doctor_id, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                $patient_id_number, $full_name, $date_of_birth, $gender, $phone, $email,
                $address, $emergency_contact, $blood_group, $allergies, $branch_id,
                $doctor_id, $_SESSION['user_id']
            ])) {
                $patient_db_id = $db->lastInsertId();
                
                // ================================================================
                // 2. CREATE VISIT - ASSIGNED TO SELECTED DOCTOR
                // ================================================================
                $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad($patient_db_id, 4, '0', STR_PAD_LEFT);
                
                $stmt = $db->prepare("
                    INSERT INTO visits (
                        visit_number, patient_id, doctor_id, receptionist_id, 
                        branch_id, visit_type, status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, 'new', 'assigned', NOW(), NOW())
                ");
                $stmt->execute([$visit_number, $patient_db_id, $doctor_id, $_SESSION['user_id'], $branch_id]);
                $visit_id = $db->lastInsertId();
                
                // ================================================================
                // 3. CREATE BILL WITH REGISTRATION FEE (BACKGROUND)
                // ================================================================
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
                        
                        // ================================================================
                        // 4. ADD REGISTRATION FEE TO BILL ITEMS
                        // ================================================================
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
                
                // Get doctor name for message
                $doctor_name = '';
                foreach ($doctors as $doc) {
                    if ($doc['id'] == $doctor_id) {
                        $doctor_name = $doc['full_name'];
                        break;
                    }
                }
                
                // Log activity
                try {
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'patient_registered', ?, NOW())");
                    $stmt->execute([$_SESSION['user_id'], "New patient registered: $full_name (ID: $patient_id_number) assigned to Dr. $doctor_name in $branch_name"]);
                } catch (Exception $e) {}
                
                $message = "Patient registered successfully! Patient ID: <strong>$patient_id_number</strong>";
                $message .= "<br>Assigned to: <strong>Dr. " . htmlspecialchars($doctor_name) . "</strong> (Online)";
                if ($registration_fee > 0) {
                    $message .= "<br>Registration Fee: <strong>TSh " . number_format($registration_fee) . "</strong> added to bill automatically.";
                }
                $message_type = 'success';
                
                // ================================================================
                // REDIRECT TO PATIENTS LIST (STAY IN RECEPTION)
                // ================================================================
                echo '<script>
                    setTimeout(function(){ 
                        window.location.href = "patients.php"; 
                    }, 2000);
                </script>';
                
            } else {
                $message = "Failed to register patient!";
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
    $doctors = [];
    $total_doctors = 0;
    $online_doctors_count = 0;
}

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
    
    <!-- Favicon -->
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           COMPLETE STYLES
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
            font-family: 'Inter', 'Segoe UI', sans-serif;
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
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
        /* ================================================================
           FORM CARD
           ================================================================ */
        .form-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 900px;
            margin: 0 auto;
        }
        .form-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
        }
        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: block;
        }
        .form-label .required {
            color: var(--danger);
            margin-left: 2px;
        }
        .form-control {
            width: 100%;
            padding: 8px 14px;
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
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        .form-row {
            margin-bottom: 14px;
        }
        .form-row:last-child {
            margin-bottom: 0;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            padding-top: 16px;
            margin-top: 16px;
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
           ONLINE DOCTOR OPTION STYLES
           ================================================================ */
        .doctor-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
        }
        .doctor-option .online-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #059669;
            animation: pulse-dot 1.5s infinite;
            margin-right: 4px;
        }
        .doctor-option .offline-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #94A3B8;
            margin-right: 4px;
        }
        .doctor-option .status-text {
            font-size: 0.65rem;
            font-weight: 500;
        }
        .doctor-option .status-text.online {
            color: #059669;
        }
        .doctor-option .status-text.offline {
            color: #94A3B8;
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            border-bottom: 3px solid var(--primary);
            padding-bottom: 12px;
        }
        .page-header .page-title {
            color: var(--primary-dark);
            font-size: 1.6rem;
            font-weight: 700;
        }
        [data-theme="dark"] .page-header .page-title {
            color: var(--primary-light);
        }
        .page-header .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.78rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        .btn-blue {
            background: var(--primary);
            color: white;
        }
        .btn-blue:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        .btn-blue:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .btn-green {
            background: var(--success);
            color: white;
        }
        .btn-green:hover {
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
        .btn-sm { padding: 3px 10px; font-size: 0.7rem; border-radius: 6px; }
        
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
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 14px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
        }
        .card:hover {
            border-color: var(--primary);
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 12px;
            z-index: 999;
            max-width: 360px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .form-card { padding: 16px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
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

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-plus mr-2" style="color: var(--primary);"></i> Register New Patient
                <span class="role-badge-display ml-2">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                Create a new patient record in <?= htmlspecialchars($branch_name) ?>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200" id="onlineDoctorBadge">
                    <i class="fas fa-user-md mr-1"></i> <span id="onlineDoctorCount"><?= $online_doctors_count ?></span> Online / <?= $total_doctors ?> Total
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-id-card mr-1"></i> Next ID: <?= $patient_id_number ?>
                </span>
                <span class="ml-2 inline-flex text-xs text-gray-400" id="lastCheckTime">
                    <i class="fas fa-clock mr-1"></i> <span id="checkTimeText">Checking...</span>
                </span>
            </p>
        </div>
        <div>
            <a href="patients.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- REGISTRATION FORM -->
    <!-- ================================================================ -->
    <div class="form-card">
        <div class="flex items-center gap-3 mb-4">
            <i class="fas fa-user-plus text-2xl text-primary"></i>
            <h3 class="text-lg font-semibold text-gray-800">Patient Registration Form</h3>
        </div>
        
        <form method="POST" action="" id="registrationForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- Full Name -->
                <div class="form-row md:col-span-2">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g. John Doe" 
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </div>
                
                <!-- Date of Birth -->
                <div class="form-row">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" 
                           value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                </div>
                
                <!-- Gender -->
                <div class="form-row">
                    <label class="form-label">Gender <span class="required">*</span></label>
                    <select name="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <!-- Phone -->
                <div class="form-row">
                    <label class="form-label">Phone Number <span class="required">*</span></label>
                    <input type="tel" name="phone" class="form-control" placeholder="e.g. 0759 154 160" 
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                </div>
                
                <!-- Email -->
                <div class="form-row">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. john@example.com" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                
                <!-- Assign Doctor - ONLINE ONLY - WITH AUTO-UPDATE -->
                <div class="form-row" id="doctorSelectContainer">
                    <label class="form-label">Assign Online Doctor <span class="required">*</span></label>
                    <select name="doctor_id" class="form-control" required id="doctorSelect">
                        <option value="">-- Select Online Doctor --</option>
                        <?php if (!empty($doctors)): ?>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?= $doctor['id'] ?>" <?= ($_POST['doctor_id'] ?? '') == $doctor['id'] ? 'selected' : '' ?>>
                                    Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                    <?php if (!empty($doctor['specialty'])): ?>
                                        (<?= htmlspecialchars($doctor['specialty']) ?>)
                                    <?php endif; ?>
                                    - 🟢 Online
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <div id="doctorStatusMessage">
                        <?php if (empty($doctors)): ?>
                            <p class="text-xs text-red-500 mt-1">
                                <i class="fas fa-exclamation-circle mr-1"></i> 
                                No doctors are currently online in <?= htmlspecialchars($branch_name) ?>.
                                <br>Please wait for a doctor to come online or check back later.
                            </p>
                        <?php else: ?>
                            <p class="text-xs text-green-500 mt-1" id="onlineDoctorMessage">
                                <i class="fas fa-check-circle mr-1"></i> 
                                <span id="onlineDoctorCountText"><?= count($doctors) ?></span> doctor(s) currently online in <?= htmlspecialchars($branch_name) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Address -->
                <div class="form-row md:col-span-2">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" placeholder="Enter full address..."><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                </div>
                
                <!-- Emergency Contact -->
                <div class="form-row">
                    <label class="form-label">Emergency Contact</label>
                    <input type="tel" name="emergency_contact" class="form-control" placeholder="e.g. 0755 123 456" 
                           value="<?= htmlspecialchars($_POST['emergency_contact'] ?? '') ?>">
                </div>
                
                <!-- Blood Group -->
                <div class="form-row">
                    <label class="form-label">Blood Group</label>
                    <select name="blood_group" class="form-control">
                        <option value="">Select Blood Group</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                            <option value="<?= $bg ?>" <?= ($_POST['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Allergies -->
                <div class="form-row md:col-span-2">
                    <label class="form-label">Allergies</label>
                    <textarea name="allergies" class="form-control" placeholder="List any known allergies..."><?= htmlspecialchars($_POST['allergies'] ?? '') ?></textarea>
                </div>
                
                <!-- Branch (hidden - forced to user's branch) -->
                <input type="hidden" name="branch_id" value="<?= $selected_branch_id ?>">
                
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-blue" <?= empty($doctors) ? 'disabled' : '' ?> id="registerBtn">
                    <i class="fas fa-save"></i> Register Patient
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="patients.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
            <?php if (empty($doctors)): ?>
                <p class="text-xs text-red-500 mt-2 text-center" id="noDoctorWarning">
                    <i class="fas fa-info-circle mr-1"></i> 
                    Registration is disabled because no doctors are currently online.
                </p>
            <?php endif; ?>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK STATS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5" style="max-width:900px;margin:20px auto 0;">
        <div class="card text-center" id="onlineDoctorsCard">
            <p class="text-2xl font-bold text-primary" id="onlineDoctorsStat"><?= $online_doctors_count ?></p>
            <p class="text-sm text-gray-500">
                <span class="text-green-500">🟢</span> Online Doctors
                <span class="text-xs text-gray-400 block" id="onlineDoctorsStatTime">Updated now</span>
            </p>
        </div>
        <div class="card text-center">
            <p class="text-2xl font-bold text-green-600"><?= date('M d, Y') ?></p>
            <p class="text-sm text-gray-500">Today's Date</p>
        </div>
        <div class="card text-center">
            <p class="text-2xl font-bold text-purple-600"><?= $patient_id_number ?></p>
            <p class="text-sm text-gray-500">Next Patient ID</p>
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
<!-- JAVASCRIPT - WITH AJAX AUTO-UPDATE (3 SECONDS) -->
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
    // AJAX AUTO-UPDATE - ONLINE DOCTORS COUNT (EVERY 3 SECONDS)
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var lastOnlineCount = <?= json_encode($online_doctors_count) ?>;
    var lastDoctorsList = <?= json_encode($doctors) ?>;
    
    function fetchOnlineDoctors() {
        if (isUpdating) return;
        isUpdating = true;
        
        var branchId = <?= json_encode($selected_branch_id) ?>;
        
        fetch('get_online_doctors.php?branch_id=' + branchId + '&t=' + new Date().getTime())
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var onlineCount = data.count || 0;
                    var doctors = data.doctors || [];
                    
                    // Update if changed
                    if (onlineCount !== lastOnlineCount || JSON.stringify(doctors) !== JSON.stringify(lastDoctorsList)) {
                        lastOnlineCount = onlineCount;
                        lastDoctorsList = doctors;
                        updateDoctorDropdown(onlineCount, doctors);
                    }
                    
                    // Update timestamp
                    var now = new Date();
                    document.getElementById('checkTimeText').textContent = 'Last check: ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    document.getElementById('onlineDoctorsStatTime').textContent = 'Updated ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Error fetching online doctors:', error);
                isUpdating = false;
            });
    }
    
    function updateDoctorDropdown(onlineCount, doctors) {
        var select = document.getElementById('doctorSelect');
        var currentValue = select.value;
        
        // Update count badges
        document.getElementById('onlineDoctorCount').textContent = onlineCount;
        document.getElementById('onlineDoctorsStat').textContent = onlineCount;
        document.getElementById('onlineDoctorCountText').textContent = onlineCount;
        
        // Update doctor options
        var currentOptions = select.querySelectorAll('option');
        var currentIds = [];
        currentOptions.forEach(function(opt) {
            if (opt.value !== '') {
                currentIds.push(opt.value);
            }
        });
        
        var newIds = doctors.map(function(d) { return String(d.id); });
        
        // Check if options need to be updated
        var needsUpdate = false;
        if (currentIds.length !== newIds.length) {
            needsUpdate = true;
        } else {
            for (var i = 0; i < currentIds.length; i++) {
                if (currentIds[i] !== newIds[i]) {
                    needsUpdate = true;
                    break;
                }
            }
        }
        
        if (needsUpdate) {
            // Clear existing options except the first one
            while (select.options.length > 1) {
                select.remove(1);
            }
            
            if (doctors.length > 0) {
                doctors.forEach(function(doc) {
                    var option = document.createElement('option');
                    option.value = doc.id;
                    var specialtyText = doc.specialty ? ' (' + doc.specialty + ')' : '';
                    option.textContent = 'Dr. ' + doc.full_name + specialtyText + ' - 🟢 Online';
                    if (String(doc.id) === currentValue) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
                
                // Enable register button
                document.getElementById('registerBtn').disabled = false;
                document.getElementById('noDoctorWarning')?.style?.display = 'none';
                document.getElementById('doctorStatusMessage').innerHTML = `
                    <p class="text-xs text-green-500 mt-1">
                        <i class="fas fa-check-circle mr-1"></i> 
                        <span id="onlineDoctorCountText">${onlineCount}</span> doctor(s) currently online
                    </p>
                `;
                showToast('🔄 Updated', onlineCount + ' doctor(s) currently online', 'info');
            } else {
                // No doctors online
                document.getElementById('registerBtn').disabled = true;
                document.getElementById('doctorStatusMessage').innerHTML = `
                    <p class="text-xs text-red-500 mt-1">
                        <i class="fas fa-exclamation-circle mr-1"></i> 
                        No doctors are currently online. Registration is disabled.
                    </p>
                `;
                if (document.getElementById('noDoctorWarning')) {
                    document.getElementById('noDoctorWarning').style.display = 'block';
                }
                showToast('⚠️ No Doctors Online', 'Please wait for a doctor to come online', 'warning');
            }
        }
    }

    // ================================================================
    // START AUTO-UPDATE - EVERY 3 SECONDS
    // ================================================================
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        updateInterval = setInterval(fetchOnlineDoctors, 3000);
        fetchOnlineDoctors(); // Initial fetch
    }

    // ================================================================
    // STOP AUTO-UPDATE
    // ================================================================
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }

    // ================================================================
    // VISIBILITY CHANGE - PAUSE WHEN HIDDEN
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Start auto-update after 2 seconds
        setTimeout(function() {
            startAutoUpdate();
        }, 2000);
    });

    console.log('%c👤 Braick - New Patient Registration (With Auto-Update)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Next Patient ID: <?= $patient_id_number ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Registration Fee: TSh <?= number_format($registration_fee) ?> (Added to bill automatically)', 'font-size:13px; color:#F59E0B;');
    console.log('%c🟢 Online Doctors: <?= $online_doctors_count ?> / <?= $total_doctors ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔄 Auto-update: Every 3 seconds (Online doctors count)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ After registration: Redirects to patients.php (stays in reception)', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>