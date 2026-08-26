<?php
// ================================================================
// FILE: frontend/pages/doctor/new_visit.php
// DOCTOR - NEW VISIT
// USING NEW DATABASE: dispensary_db
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
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_online = $_SESSION['is_online'] ?? 0;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// ================================================================
// VERIFY DOCTOR EXISTS AND IS ACTIVE
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, branch_id, specialty, status, is_online FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor_data || $doctor_data['status'] !== 'active') {
        session_destroy();
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
    
    $doctor_name = $doctor_data['full_name'];
    $doctor_branch_id = $doctor_data['branch_id'] ?? 1;
    $doctor_specialty = $doctor_data['specialty'] ?? 'General Medicine';
    $is_online = $doctor_data['is_online'] ?? 0;
    
    $_SESSION['full_name'] = $doctor_name;
    $_SESSION['branch_id'] = $doctor_branch_id;
    $_SESSION['specialty'] = $doctor_specialty;
    $_SESSION['is_online'] = $is_online;
    
} catch (Exception $e) {
    error_log("new_visit verification error: " . $e->getMessage());
}

// ================================================================
// GET PATIENT LIST FOR SELECTION (Patients assigned to this doctor)
// ================================================================
$patients = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT p.* 
        FROM patients p
        JOIN visits v ON p.id = v.patient_id
        WHERE v.doctor_id = ?
        ORDER BY p.full_name
    ");
    $stmt->execute([$doctor_id]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patients = [];
}

// If no patients, try to get all patients
if (empty($patients)) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM patients 
            ORDER BY full_name
            LIMIT 50
        ");
        $stmt->execute();
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $patients = [];
    }
}

// ================================================================
// GET DOCTOR'S BRANCH NAME
// ================================================================
$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $doctor_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $doctor_branch_name = 'Branch';
}

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_visit'])) {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $visit_type = trim($_POST['visit_type'] ?? 'follow-up');
    $symptoms = trim($_POST['symptoms'] ?? '');
    $complaint = trim($_POST['complaint'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $consultation_fee = (float)($_POST['consultation_fee'] ?? 0);
    
    $errors = [];
    
    if ($patient_id <= 0) {
        $errors[] = "Please select a patient";
    }
    if (empty($visit_type)) {
        $errors[] = "Please select visit type";
    }
    
    // Verify patient exists
    if ($patient_id > 0) {
        try {
            $stmt = $db->prepare("SELECT id FROM patients WHERE id = ?");
            $stmt->execute([$patient_id]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = "Patient not found";
            }
        } catch (Exception $e) {
            $errors[] = "Error verifying patient: " . $e->getMessage();
        }
    }
    
    if (empty($errors)) {
        try {
            // Generate visit number
            $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $db->beginTransaction();
            
            // Insert visit using correct column names for new database
            $stmt = $db->prepare("
                INSERT INTO visits (
                    visit_number, visit_date, patient_id, doctor_id, receptionist_id,
                    branch_id, visit_type, status, symptoms, complaint,
                    diagnosis, treatment, notes, consultation_fee, created_at
                ) VALUES (?, NOW(), ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $receptionist_id = null; // Doctor creating visit directly
            
            $stmt->execute([
                $visit_number,
                $patient_id,
                $doctor_id,
                $receptionist_id,
                $doctor_branch_id,
                $visit_type,
                $symptoms,
                $complaint ?: $symptoms,
                $diagnosis,
                $treatment ?: $diagnosis,
                $notes,
                $consultation_fee
            ]);
            
            $visit_id = $db->lastInsertId();
            
            // Create bill for this visit
            $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
            $stmt = $db->prepare("
                INSERT INTO bills (
                    bill_number, patient_id, visit_id, branch_id, created_by,
                    subtotal, discount_percent, discount_amount, total_amount, 
                    paid_amount, balance, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, 'pending', NOW())
            ");
            $stmt->execute([
                $bill_number, $patient_id, $visit_id, $doctor_branch_id, $doctor_id
            ]);
            $bill_id = $db->lastInsertId();
            
            // Add consultation fee to bill if set
            if ($consultation_fee > 0) {
                $stmt = $db->prepare("
                    INSERT INTO bill_items (
                        bill_id, patient_id, branch_id, item_type, item_name,
                        quantity, unit_price, total_price, status, created_at
                    ) VALUES (?, ?, ?, 'consultation', ?, 1, ?, ?, 'pending', NOW())
                ");
                $item_name = ucfirst($visit_type) . ' Consultation';
                $stmt->execute([
                    $bill_id, $patient_id, $doctor_branch_id,
                    $item_name, $consultation_fee, $consultation_fee
                ]);
                
                // Update bill total
                $stmt = $db->prepare("
                    UPDATE bills 
                    SET subtotal = ?, total_amount = ?, balance = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$consultation_fee, $consultation_fee, $consultation_fee, $bill_id]);
            }
            
            // Log activity
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                    VALUES (?, ?, 'visit_created', ?, NOW())
                ");
                $stmt->execute([
                    $doctor_id,
                    $doctor_branch_id,
                    "New visit created: $visit_number for patient ID: $patient_id"
                ]);
            } catch (Exception $e) {}
            
            $db->commit();
            
            // Redirect to consultation
            $message = "✅ Visit created successfully!";
            $message_type = 'success';
            
            echo '<script>
                setTimeout(function() {
                    window.location.href = "consultation.php?visit_id=' . $visit_id . '";
                }, 1500);
            </script>';
            
        } catch (Exception $e) {
            if (isset($db)) {
                $db->rollBack();
            }
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
            error_log("new_visit error: " . $e->getMessage());
        }
    } else {
        $message = "❌ " . implode('<br>', $errors);
        $message_type = 'error';
    }
}

// ================================================================
// GET CONSULTATION FEE DEFAULT
// ================================================================
$default_consultation_fee = 15000;
try {
    $stmt = $db->prepare("
        SELECT price FROM services 
        WHERE category_id = 2 AND service_name LIKE '%New%' 
        AND is_active = 1 AND (branch_id IS NULL OR branch_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$doctor_branch_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($service) {
        $default_consultation_fee = $service['price'];
    }
} catch (Exception $e) {
    $default_consultation_fee = 15000;
}

// ================================================================
// VARIABLES FOR SIDEBAR
// ================================================================
$selected_branch_id = $doctor_branch_id;
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;
$pending_lab_tests = 0;
$pending_prescriptions = 0;

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Visit - Braick Dispensary</title>
    <link rel="icon" href="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" type="image/png">
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
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.07);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.1);
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.4);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: var(--transition);
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: var(--transition);
        }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: var(--primary-light); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius-lg);
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
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .page-header .doctor-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.15);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: var(--transition);
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
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 28px 32px;
            border: 2px solid var(--border-color);
            transition: var(--transition);
            max-width: 700px;
            margin: 0 auto;
            box-shadow: var(--shadow-md);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        /* ================================================================
           FORM
           ================================================================ */
        .space-y-4 > * + * { margin-top: 18px; }
        
        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .form-label .required {
            color: #EF4444;
            margin-left: 2px;
        }
        
        .form-label .optional {
            color: var(--text-secondary);
            font-weight: 400;
            font-size: 0.75rem;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.9rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.6;
        }
        
        select.form-control {
            appearance: auto;
            cursor: pointer;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 60px;
        }
        
        .form-hint {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .form-hint a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .form-hint a:hover {
            text-decoration: underline;
        }
        
        /* ================================================================
           ALERT
           ================================================================ */
        .alert {
            padding: 14px 18px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.9rem;
            border: 1px solid transparent;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .alert i {
            margin-top: 2px;
            font-size: 1.1rem;
        }
        
        .alert-success { background: var(--success-bg); color: var(--success-dark); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger-dark); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: #92400E; border-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: var(--primary-dark); border-color: var(--primary); }
        
        [data-theme="dark"] .alert-success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
        [data-theme="dark"] .alert-error { background: #3A1A1A; color: #F87171; border-color: #F87171; }
        [data-theme="dark"] .alert-warning { background: #3D2E0A; color: #FBBF24; border-color: #FBBF24; }
        [data-theme="dark"] .alert-info { background: #1E3A5F; color: #6EA8FE; border-color: #6EA8FE; }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            min-height: 44px;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.35);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #DC2626, #EF4444);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.3);
        }
        
        .btn-sm {
            padding: 6px 14px;
            font-size: 0.75rem;
            min-height: 34px;
        }
        
        .flex { display: flex; }
        .flex-1 { flex: 1; }
        .flex-wrap { flex-wrap: wrap; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-3 { gap: 12px; }
        .gap-2 { gap: 8px; }
        .pt-4 { padding-top: 16px; }
        .border-t { border-top: 2px solid var(--border-color); }
        .mt-2 { margin-top: 8px; }
        .mt-4 { margin-top: 16px; }
        .text-center { text-align: center; }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        .footer .separator { color: var(--border-color); margin: 0 6px; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 12px;
            }
            
            .page-header {
                padding: 16px 18px;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-header .page-title {
                font-size: 1.3rem;
            }
            
            .card {
                padding: 18px 16px;
            }
            
            .flex.gap-3.pt-4 {
                flex-direction: column;
            }
            
            .flex.gap-3.pt-4 .btn {
                width: 100%;
                justify-content: center;
            }
            
            .page-header .header-actions {
                width: 100%;
            }
            
            .page-header .header-actions .btn-outline-light {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .page-header .page-title { font-size: 1.1rem; }
            .page-header .page-title i { font-size: 1.4rem; }
            .card { padding: 12px 14px; }
            .form-control { padding: 8px 12px; font-size: 0.85rem; }
            .btn { padding: 8px 16px; font-size: 0.8rem; min-height: 38px; }
            .page-header .page-subtitle { font-size: 0.8rem; }
            .page-header .branch-tag, .page-header .doctor-tag { font-size: 0.6rem; }
        }
        
        @media print {
            .top-nav, .sidebar, .btn, .footer, .page-header .header-actions {
                display: none !important;
            }
            
            .main-content { margin: 0 !important; padding: 20px !important; }
            .card { border: 1px solid #ddd !important; box-shadow: none !important; }
            .page-header { background: #0B5ED7 !important; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-plus-circle"></i>
                New Visit
                <span class="branch-tag"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?></span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-calendar-plus"></i>
                Start a new patient visit
                <span class="doctor-tag"><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($doctor_name) ?></span>
            </p>
        </div>
        <div class="header-actions">
            <a href="my_patients.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- FORM CARD -->
    <div class="card">
        <form method="POST" action="" id="visitForm">
            <input type="hidden" name="save_visit" value="1">
            
            <div class="space-y-4">
                <!-- Patient Selection -->
                <div>
                    <label class="form-label">
                        Select Patient <span class="required">*</span>
                    </label>
                    <select name="patient_id" class="form-control" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= $patient['id'] ?>">
                                <?= htmlspecialchars($patient['full_name']) ?> 
                                (<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)
                                <?php if (!empty($patient['phone'])): ?>
                                    - <?= htmlspecialchars($patient['phone']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        <a href="new_patient.php">+ Add New Patient</a>
                        <?php if (empty($patients)): ?>
                            <span style="color: #D97706;"> - No patients found. Please add a new patient first.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Visit Type -->
                <div>
                    <label class="form-label">
                        Visit Type <span class="required">*</span>
                    </label>
                    <select name="visit_type" class="form-control" required>
                        <option value="new">New Patient</option>
                        <option value="follow-up" selected>Follow-up</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </div>

                <!-- Consultation Fee -->
                <div>
                    <label class="form-label">
                        Consultation Fee (TSh) <span class="optional">(Optional)</span>
                    </label>
                    <input type="number" name="consultation_fee" class="form-control" 
                           value="<?= $default_consultation_fee ?>" min="0" step="1000">
                    <div class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        Default: TSh <?= number_format($default_consultation_fee, 0) ?>
                    </div>
                </div>

                <!-- Symptoms -->
                <div>
                    <label class="form-label">Symptoms</label>
                    <textarea name="symptoms" class="form-control" rows="3" 
                              placeholder="Describe the patient's symptoms..."></textarea>
                </div>

                <!-- Complaint -->
                <div>
                    <label class="form-label">Complaint <span class="optional">(Optional)</span></label>
                    <textarea name="complaint" class="form-control" rows="2" 
                              placeholder="Patient's main complaint..."></textarea>
                </div>

                <!-- Diagnosis -->
                <div>
                    <label class="form-label">Diagnosis <span class="optional">(Optional)</span></label>
                    <textarea name="diagnosis" class="form-control" rows="2" 
                              placeholder="Enter diagnosis..."></textarea>
                </div>

                <!-- Treatment -->
                <div>
                    <label class="form-label">Treatment <span class="optional">(Optional)</span></label>
                    <textarea name="treatment" class="form-control" rows="2" 
                              placeholder="Enter treatment plan..."></textarea>
                </div>

                <!-- Notes -->
                <div>
                    <label class="form-label">Notes <span class="optional">(Optional)</span></label>
                    <textarea name="notes" class="form-control" rows="2" 
                              placeholder="Additional notes..."></textarea>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 pt-4 border-t">
                    <button type="submit" class="btn btn-primary flex-1" <?= empty($patients) ? 'disabled' : '' ?>>
                        <i class="fas fa-save"></i> Create Visit
                    </button>
                    <button type="reset" class="btn btn-outline">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <a href="my_patients.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span>
            <span class="separator">|</span>
            New Visit
            <span class="separator">|</span>
            Dr. <?= htmlspecialchars($doctor_name) ?>
            <span class="separator">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE - SYNC WITH HEADER
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // Listen for dark mode changes from header
    document.addEventListener('darkModeChanged', function(e) {
        var isDark = e.detail && e.detail.isDark;
        var html = document.documentElement;
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }
    });

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    document.getElementById('visitForm')?.addEventListener('submit', function(e) {
        var patientSelect = document.querySelector('select[name="patient_id"]');
        if (!patientSelect || patientSelect.value === '') {
            e.preventDefault();
            alert('⚠️ Please select a patient');
            patientSelect.focus();
            return false;
        }
        
        var submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
        }
    });

    // ================================================================
    // RESET BUTTON - Clear form
    // ================================================================
    document.querySelector('button[type="reset"]')?.addEventListener('click', function(e) {
        if (!confirm('Clear all form fields?')) {
            e.preventDefault();
        }
    });

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('%c👨‍⚕️ New Visit - Dr. <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Session-based login active', 'font-size:13px; color:#34D399;');
    console.log('%c🏥 Branch: <?= htmlspecialchars($doctor_branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Patients available: <?= count($patients) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Default Consultation Fee: TSh <?= number_format($default_consultation_fee, 0) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c💡 New visit will redirect to consultation page', 'font-size:13px; color:#7C3AED;');
    console.log('%c📦 Using NEW DATABASE: dispensary_db', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>