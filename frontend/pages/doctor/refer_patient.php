<?php
// ================================================================
// FILE: frontend/pages/doctor/refer_patient.php
// DOCTOR - REFER PATIENT
// Internal: Select doctor from dropdown
// External: Form with Braick logo
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Doctor
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'dr.john';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'] ?? 5;
$user_full_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_specialty = $_SESSION['specialty'] ?? 'General Medicine';

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = getDB();
$message = '';
$message_type = '';

// ================================================================
// GET PATIENT ID FROM URL
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

if ($patient_id <= 0) {
    header('Location: my_patients.php?error=invalid_patient');
    exit;
}

// ================================================================
// GET PATIENT DETAILS
// ================================================================
$patient = [];
try {
    $stmt = $db->prepare("
        SELECT p.*, 
               u.full_name as doctor_name,
               v.id as visit_id,
               v.visit_number,
               v.diagnosis,
               v.symptoms
        FROM patients p
        LEFT JOIN visits v ON v.patient_id = p.id AND v.status NOT IN ('completed', 'cancelled')
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE p.id = ? AND p.branch_id = ?
        ORDER BY v.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$patient_id, $user_branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('Location: my_patients.php?error=patient_not_found');
        exit;
    }
} catch (Exception $e) {
    header('Location: my_patients.php?error=database_error');
    exit;
}

// ================================================================
// GET DOCTORS LIST (For Internal Referral)
// ================================================================
$doctors = [];
try {
    $stmt = $db->prepare("
        SELECT id, full_name, specialty, phone, email 
        FROM users 
        WHERE role = 'doctor' 
        AND id != ? 
        AND branch_id = ?
        AND is_active = 1
        ORDER BY full_name
    ");
    $stmt->execute([$user_id, $user_branch_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $doctors = [];
}

// ================================================================
// GET REFERRAL TYPES
// ================================================================
$referral_types = [
    'internal' => 'Internal (Within Facility)',
    'external' => 'External (Outside Facility)'
];

// ================================================================
// GET SPECIALTIES
// ================================================================
$specialties = [
    'General Medicine',
    'Cardiology',
    'Dermatology',
    'Endocrinology',
    'Gastroenterology',
    'Hematology',
    'Infectious Diseases',
    'Nephrology',
    'Neurology',
    'Obstetrics & Gynecology',
    'Oncology',
    'Ophthalmology',
    'Orthopedics',
    'Otolaryngology (ENT)',
    'Pediatrics',
    'Psychiatry',
    'Pulmonology',
    'Radiology',
    'Rheumatology',
    'Surgery',
    'Urology'
];

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_referral') {
        $referral_type = $_POST['referral_type'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        // Internal referral fields
        $referred_to_doctor = isset($_POST['referred_to_doctor']) ? (int)$_POST['referred_to_doctor'] : 0;
        $internal_notes = trim($_POST['internal_notes'] ?? '');
        
        // External referral fields
        $external_facility = trim($_POST['external_facility'] ?? '');
        $external_address = trim($_POST['external_address'] ?? '');
        $external_phone = trim($_POST['external_phone'] ?? '');
        $external_contact_person = trim($_POST['external_contact_person'] ?? '');
        $referral_reason = trim($_POST['referral_reason'] ?? '');
        $clinical_summary = trim($_POST['clinical_summary'] ?? '');
        
        // Validate
        $errors = [];
        if (empty($referral_type)) {
            $errors[] = "Please select referral type";
        }
        if (empty($reason)) {
            $errors[] = "Please enter reason for referral";
        }
        
        if ($referral_type === 'internal') {
            if ($referred_to_doctor <= 0) {
                $errors[] = "Please select a doctor";
            }
        } elseif ($referral_type === 'external') {
            if (empty($external_facility)) {
                $errors[] = "Please enter facility name";
            }
        }
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                $referral_number = 'REF-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                
                // Prepare notes
                $full_notes = $notes;
                
                if ($referral_type === 'internal') {
                    // Get doctor name
                    $stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
                    $stmt->execute([$referred_to_doctor]);
                    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                    $doctor_name = $doctor['full_name'] ?? 'Unknown Doctor';
                    
                    $full_notes .= "\n\n--- Internal Referral ---\n";
                    $full_notes .= "Referred to: " . $doctor_name . "\n";
                    $full_notes .= "Specialty: " . ($_POST['specialty'] ?? 'N/A') . "\n";
                    $full_notes .= "Notes: " . $internal_notes;
                } else {
                    $full_notes .= "\n\n--- External Referral ---\n";
                    $full_notes .= "Facility: " . $external_facility . "\n";
                    $full_notes .= "Address: " . $external_address . "\n";
                    $full_notes .= "Phone: " . $external_phone . "\n";
                    $full_notes .= "Contact Person: " . $external_contact_person . "\n";
                    $full_notes .= "Reason: " . $referral_reason . "\n";
                    $full_notes .= "Clinical Summary: " . $clinical_summary;
                }
                
                // Insert referral
                $stmt = $db->prepare("
                    INSERT INTO referrals (
                        referral_number, patient_id, visit_id, 
                        referral_type, reason, referred_to, notes,
                        created_by, branch_id, created_at, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')
                ");
                $stmt->execute([
                    $referral_number,
                    $patient_id,
                    $visit_id ?: null,
                    $referral_type,
                    $reason,
                    $referral_type === 'internal' ? $referred_to_doctor : null,
                    $full_notes,
                    $user_id,
                    $user_branch_id
                ]);
                
                $referral_id = $db->lastInsertId();
                
                // Update patient status
                $stmt = $db->prepare("
                    UPDATE patients 
                    SET status = 'referred',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$patient_id]);
                
                $db->commit();
                
                $message = "✅ Patient referred successfully! Referral #: " . $referral_number;
                $message_type = 'success';
                
                // Redirect after success
                echo '<script>
                    setTimeout(function(){
                        window.location.href = "my_patients.php?success=referral";
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
}

// ================================================================
// GET LOGO HTML
// ================================================================
function getLogoHTML() {
    $logo_paths = [
        '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png',
        '/dispensary_system/frontend/assets/uploads/profiles/logo.png',
        '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.jpg',
        '/dispensary_system/frontend/assets/uploads/profiles/logo.jpg',
        '/dispensary_system/frontend/assets/img/braick_logo.png',
        '/dispensary_system/frontend/assets/img/logo.png'
    ];
    
    $logo_url = '';
    foreach ($logo_paths as $path) {
        $full_path = $_SERVER['DOCUMENT_ROOT'] . $path;
        if (file_exists($full_path)) {
            $logo_url = $path;
            break;
        }
    }
    
    if (!empty($logo_url)) {
        return '<img src="' . $logo_url . '" alt="Braick Dispensary" style="height:50px;width:auto;max-height:50px;border-radius:6px;object-fit:contain;">';
    }
    
    return '<div style="display:inline-block;background:#0B5ED7;color:white;padding:8px 20px;border-radius:8px;font-size:18px;font-weight:bold;font-family:Arial,sans-serif;">BRAICK</div>';
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

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
    <title>Refer Patient - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
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
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
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
        
        .page-header .page-title {
            color: white;
            font-size: 1.6rem;
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
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .btn-outline-light {
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
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        [data-theme="dark"] .card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-blue { color: var(--primary); }
        .card-title .title-green { color: var(--success); }
        .card-title .title-purple { color: #7C3AED; }
        
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        select.form-control {
            appearance: auto;
            cursor: pointer;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.8rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
        }
        
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        }
        
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-purple { background: #EDE9FE; color: #7C3AED; }
        
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: var(--primary); border-color: var(--primary); }
        
        .detail-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .detail-row:last-child { border-bottom: none; }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 140px;
            flex-shrink: 0;
            font-size: 0.8rem;
        }
        
        .detail-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        
        /* ================================================================
           REFERRAL TYPE SELECTOR
           ================================================================ */
        .referral-type-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .referral-type-option {
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .referral-type-option:hover {
            border-color: var(--primary);
            background: var(--primary-bg);
            transform: translateY(-2px);
        }
        
        .referral-type-option.active {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }
        
        .referral-type-option .option-icon {
            font-size: 2rem;
            display: block;
            margin-bottom: 6px;
        }
        
        .referral-type-option .option-title {
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .referral-type-option .option-desc {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 2px;
        }
        
        [data-theme="dark"] .referral-type-option:hover {
            background: #1E3A5F;
        }
        
        [data-theme="dark"] .referral-type-option.active {
            background: var(--primary);
            color: white;
        }
        
        /* ================================================================
           INTERNAL FORM
           ================================================================ */
        .internal-form, .external-form {
            display: none;
            padding-top: 16px;
            border-top: 2px solid var(--border-color);
            margin-top: 16px;
        }
        
        .internal-form.active, .external-form.active {
            display: block;
        }
        
        /* ================================================================
           EXTERNAL REFERRAL LETTER
           ================================================================ */
        .referral-letter {
            background: #fff;
            border: 2px solid #0B5ED7;
            border-radius: var(--radius-lg);
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
            font-family: Arial, sans-serif;
        }
        
        .referral-letter .letter-header {
            text-align: center;
            border-bottom: 3px double #0B5ED7;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .referral-letter .letter-header .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        
        .referral-letter .letter-header .logo-container img {
            height: 50px;
            width: auto;
            max-height: 50px;
            border-radius: 6px;
            object-fit: contain;
        }
        
        .referral-letter .letter-header .logo-container .logo-text {
            display: inline-block;
            background: #0B5ED7;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            font-family: Arial, sans-serif;
        }
        
        .referral-letter .letter-header h2 {
            color: #0B5ED7;
            font-size: 20px;
            margin: 0;
        }
        
        .referral-letter .letter-header .subtitle {
            font-size: 12px;
            color: #666;
            margin: 2px 0 0 0;
        }
        
        .referral-letter .letter-body {
            padding: 10px 0;
        }
        
        .referral-letter .letter-body .field-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .referral-letter .letter-body .field-row .field-label {
            font-weight: 600;
            width: 140px;
            color: #555;
            flex-shrink: 0;
        }
        
        .referral-letter .letter-body .field-row .field-value {
            flex: 1;
            color: #333;
        }
        
        .referral-letter .letter-footer {
            border-top: 2px solid #0B5ED7;
            padding-top: 15px;
            margin-top: 15px;
            text-align: center;
            font-size: 12px;
            color: #888;
        }
        
        [data-theme="dark"] .referral-letter {
            background: #1E293B;
            border-color: #3B82F6;
        }
        
        [data-theme="dark"] .referral-letter .letter-header h2 {
            color: #60A5FA;
        }
        
        [data-theme="dark"] .referral-letter .letter-header .subtitle {
            color: #94A3B8;
        }
        
        [data-theme="dark"] .referral-letter .letter-body .field-row {
            border-color: #334155;
        }
        
        [data-theme="dark"] .referral-letter .letter-body .field-row .field-label {
            color: #94A3B8;
        }
        
        [data-theme="dark"] .referral-letter .letter-body .field-row .field-value {
            color: #F1F5F9;
        }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .card { padding: 14px 16px; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; }
            .referral-type-selector { grid-template-columns: 1fr; }
            .referral-letter { padding: 16px; }
            .referral-letter .letter-body .field-row { flex-direction: column; }
            .referral-letter .letter-body .field-row .field-label { width: 100%; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .card { padding: 10px 12px; }
            .referral-letter { padding: 12px; }
        }
        
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: var(--radius);
            z-index: 9999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
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
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?? '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md"></i>
                Refer Patient
                <span class="role-badge-display">DOCTOR</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                Patient: <strong><?= htmlspecialchars($patient['full_name'] ?? 'Unknown') ?></strong>
                <span class="separator">|</span>
                ID: <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                <span class="separator">|</span>
                <?= !empty($patient['date_of_birth']) ? calculateAge($patient['date_of_birth']) . ' yrs' : 'N/A' ?>
                <span class="separator">|</span>
                <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="my_patients.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <h3 class="card-title">
            <i class="fas fa-user-circle title-blue mr-2"></i>
            Patient Information
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <div class="detail-row"><span class="detail-label">Full Name</span><span class="detail-value"><strong><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></strong></span></div>
            <div class="detail-row"><span class="detail-label">Patient ID</span><span class="detail-value"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Gender</span><span class="detail-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Date of Birth</span><span class="detail-value"><?= !empty($patient['date_of_birth']) ? date('d/m/Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></span></div>
            <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Blood Group</span><span class="detail-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span></div>
            <?php if (!empty($patient['diagnosis'])): ?>
                <div class="detail-row" style="grid-column: span 2;"><span class="detail-label">Diagnosis</span><span class="detail-value"><?= htmlspecialchars($patient['diagnosis']) ?></span></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- REFERRAL FORM -->
    <!-- ================================================================ -->
    <form method="POST" action="" id="referralForm">
        <input type="hidden" name="action" value="save_referral">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="visit_id" value="<?= $visit_id ?>">
        
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-share-alt title-purple mr-2"></i>
                Referral Details
            </h3>
            
            <!-- ================================================================ -->
            <!-- REFERRAL TYPE SELECTOR -->
            <!-- ================================================================ -->
            <div class="referral-type-selector">
                <div class="referral-type-option active" onclick="selectReferralType('internal', this)" data-type="internal">
                    <span class="option-icon">🏥</span>
                    <div class="option-title">Internal Referral</div>
                    <div class="option-desc">Refer within this facility to another doctor</div>
                </div>
                <div class="referral-type-option" onclick="selectReferralType('external', this)" data-type="external">
                    <span class="option-icon">🌍</span>
                    <div class="option-title">External Referral</div>
                    <div class="option-desc">Refer to an outside facility or specialist</div>
                </div>
            </div>
            
            <input type="hidden" name="referral_type" id="referralType" value="internal">
            
            <!-- ================================================================ -->
            <!-- INTERNAL REFERRAL FORM -->
            <!-- ================================================================ -->
            <div class="internal-form active" id="internalForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Referred To <span class="text-danger">*</span></label>
                        <select name="referred_to_doctor" class="form-control" required>
                            <option value="">-- Select Doctor --</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?= $doctor['id'] ?>">
                                    <?= htmlspecialchars($doctor['full_name']) ?> 
                                    <?= !empty($doctor['specialty']) ? '(' . htmlspecialchars($doctor['specialty']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if (empty($doctors)): ?>
                                <option value="" disabled>No other doctors available</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Specialty</label>
                        <select name="specialty" class="form-control">
                            <option value="">-- Select Specialty --</option>
                            <?php foreach ($specialties as $specialty): ?>
                                <option value="<?= htmlspecialchars($specialty) ?>"><?= htmlspecialchars($specialty) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Additional Notes</label>
                    <textarea name="internal_notes" class="form-control" rows="3" placeholder="Any additional notes for the receiving doctor..."></textarea>
                </div>
            </div>
            
            <!-- ================================================================ -->
            <!-- EXTERNAL REFERRAL FORM -->
            <!-- ================================================================ -->
            <div class="external-form" id="externalForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Facility/Hospital Name <span class="text-danger">*</span></label>
                        <input type="text" name="external_facility" class="form-control" placeholder="e.g. Muhimbili National Hospital">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="external_contact_person" class="form-control" placeholder="e.g. Dr. Jane Doe">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="external_phone" class="form-control" placeholder="e.g. +255 700 000 000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="external_email" class="form-control" placeholder="e.g. hospital@email.com">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">Address</label>
                        <textarea name="external_address" class="form-control" rows="2" placeholder="Facility address..."></textarea>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Reason for Referral <span class="text-danger">*</span></label>
                    <textarea name="referral_reason" class="form-control" rows="3" placeholder="Explain why the patient is being referred..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Clinical Summary</label>
                    <textarea name="clinical_summary" class="form-control" rows="3" placeholder="Summary of patient's clinical history, diagnosis, and treatment so far..."></textarea>
                </div>
            </div>
            
            <!-- ================================================================ -->
            <!-- COMMON FIELDS -->
            <!-- ================================================================ -->
            <div class="form-group mt-4">
                <label class="form-label">Reason for Referral <span class="text-danger">*</span></label>
                <textarea name="reason" class="form-control" rows="2" placeholder="Brief reason for referral..." required></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Additional Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Any additional notes..."></textarea>
            </div>
            
            <!-- ================================================================ -->
            <!-- EXTERNAL REFERRAL LETTER PREVIEW -->
            <!-- ================================================================ -->
            <div class="mt-4" id="externalLetterPreview" style="display:none;">
                <h4 class="text-sm font-semibold text-gray-600 mb-3">
                    <i class="fas fa-file-pdf mr-2"></i> Referral Letter Preview
                </h4>
                <div class="referral-letter" id="referralLetter">
                    <div class="letter-header">
                        <div class="logo-container">
                            <?= getLogoHTML() ?>
                            <div>
                                <h2>BRAICK DISPENSARY</h2>
                                <p class="subtitle">Quality Healthcare Services</p>
                            </div>
                        </div>
                        <h2>REFERRAL LETTER</h2>
                    </div>
                    
                    <div class="letter-body">
                        <div class="field-row">
                            <span class="field-label">Date:</span>
                            <span class="field-value" id="letterDate"><?= date('d/m/Y') ?></span>
                        </div>
                        <div class="field-row">
                            <span class="field-label">Patient Name:</span>
                            <span class="field-value"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></span>
                        </div>
                        <div class="field-row">
                            <span class="field-label">Patient ID:</span>
                            <span class="field-value"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
                        </div>
                        <div class="field-row">
                            <span class="field-label">Age/Sex:</span>
                            <span class="field-value"><?= !empty($patient['date_of_birth']) ? calculateAge($patient['date_of_birth']) . ' yrs' : 'N/A' ?> / <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                        </div>
                        <div class="field-row">
                            <span class="field-label">Referred To:</span>
                            <span class="field-value" id="letterFacility">[Facility Name]</span>
                        </div>
                        <div class="field-row">
                            <span class="field-label">Contact Person:</span>
                            <span class="field-value" id="letterContact">[Contact Person]</span>
                        </div>
                        <div class="field-row">
                            <span class="field-label">Reason for Referral:</span>
                            <span class="field-value" id="letterReason">[Reason]</span>
                        </div>
                        <div class="field-row">
                            <span class="field-label">Clinical Summary:</span>
                            <span class="field-value" id="letterSummary">[Clinical Summary]</span>
                        </div>
                        <div class="field-row">
                            <span class="field-label">Referred By:</span>
                            <span class="field-value"><?= htmlspecialchars($user_full_name) ?></span>
                        </div>
                        <div class="field-row">
                            <span class="field-label">Specialty:</span>
                            <span class="field-value"><?= htmlspecialchars($user_specialty) ?></span>
                        </div>
                        <div class="field-row">
                            <span class="field-label">Contact:</span>
                            <span class="field-value"><?= htmlspecialchars($user_branch_name) ?> | <?= date('d/m/Y') ?></span>
                        </div>
                    </div>
                    
                    <div class="letter-footer">
                        <p>This is a computer generated referral letter. Please verify all information.</p>
                        <p><strong>Braick Dispensary</strong> - Quality Healthcare Services</p>
                    </div>
                </div>
                
                <div class="mt-3 flex gap-3">
                    <button type="button" class="btn btn-primary" onclick="printReferralLetter()">
                        <i class="fas fa-print"></i> Print Letter
                    </button>
                </div>
            </div>
            
            <!-- ================================================================ -->
            <!-- FORM ACTIONS -->
            <!-- ================================================================ -->
            <div class="mt-4 flex flex-wrap gap-3" style="padding-top:16px;border-top:2px solid var(--border-color);">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-paper-plane"></i> Submit Referral
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Clear
                </button>
                <a href="my_patients.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>
    </form>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Refer Patient
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- Toast -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // Dark Mode
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

    // Sidebar Toggle
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });

    // Date & Time
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SELECT REFERRAL TYPE
    // ================================================================
    function selectReferralType(type, element) {
        // Update button states
        document.querySelectorAll('.referral-type-option').forEach(function(btn) {
            btn.classList.remove('active');
        });
        if (element) {
            element.classList.add('active');
        }
        
        // Update hidden input
        document.getElementById('referralType').value = type;
        
        // Show/hide forms
        var internalForm = document.getElementById('internalForm');
        var externalForm = document.getElementById('externalForm');
        var externalPreview = document.getElementById('externalLetterPreview');
        
        if (type === 'internal') {
            internalForm.classList.add('active');
            externalForm.classList.remove('active');
            externalPreview.style.display = 'none';
        } else {
            internalForm.classList.remove('active');
            externalForm.classList.add('active');
            externalPreview.style.display = 'block';
            
            // Update letter preview
            updateLetterPreview();
        }
    }

    // ================================================================
    // UPDATE LETTER PREVIEW
    // ================================================================
    function updateLetterPreview() {
        var facility = document.querySelector('input[name="external_facility"]')?.value || '[Facility Name]';
        var contact = document.querySelector('input[name="external_contact_person"]')?.value || '[Contact Person]';
        var reason = document.querySelector('textarea[name="referral_reason"]')?.value || '[Reason]';
        var summary = document.querySelector('textarea[name="clinical_summary"]')?.value || '[Clinical Summary]';
        
        document.getElementById('letterFacility').textContent = facility;
        document.getElementById('letterContact').textContent = contact;
        document.getElementById('letterReason').textContent = reason;
        document.getElementById('letterSummary').textContent = summary;
    }

    // Listen to form changes for letter preview
    document.addEventListener('DOMContentLoaded', function() {
        var inputs = document.querySelectorAll('.external-form input, .external-form textarea');
        inputs.forEach(function(input) {
            input.addEventListener('input', updateLetterPreview);
        });
    });

    // ================================================================
    // PRINT REFERRAL LETTER
    // ================================================================
    function printReferralLetter() {
        var letter = document.getElementById('referralLetter');
        if (!letter) {
            showToast('Error', 'No letter to print', 'error');
            return;
        }
        
        var printWindow = window.open('', '_blank', 'width=800,height=600');
        if (!printWindow) {
            showToast('Error', 'Please allow popups for printing', 'error');
            return;
        }
        
        var content = letter.outerHTML;
        
        printWindow.document.write('<!DOCTYPE html><html><head><title>Referral Letter</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; padding: 40px; background: white; }');
        printWindow.document.write('.referral-letter { max-width: 800px; margin: 0 auto; padding: 30px; border: 2px solid #0B5ED7; border-radius: 10px; }');
        printWindow.document.write('.letter-header { text-align: center; border-bottom: 3px double #0B5ED7; padding-bottom: 15px; margin-bottom: 20px; }');
        printWindow.document.write('.logo-container { display: flex; align-items: center; justify-content: center; gap: 15px; flex-wrap: wrap; margin-bottom: 10px; }');
        printWindow.document.write('.logo-container img { height: 50px; width: auto; max-height: 50px; border-radius: 6px; }');
        printWindow.document.write('.logo-container .logo-text { display: inline-block; background: #0B5ED7; color: white; padding: 8px 20px; border-radius: 8px; font-size: 18px; font-weight: bold; }');
        printWindow.document.write('.letter-header h2 { color: #0B5ED7; font-size: 20px; margin: 0; }');
        printWindow.document.write('.letter-header .subtitle { font-size: 12px; color: #666; margin: 2px 0 0 0; }');
        printWindow.document.write('.letter-body { padding: 10px 0; }');
        printWindow.document.write('.field-row { display: flex; padding: 6px 0; border-bottom: 1px solid #f0f0f0; }');
        printWindow.document.write('.field-row .field-label { font-weight: 600; width: 140px; color: #555; flex-shrink: 0; }');
        printWindow.document.write('.field-row .field-value { flex: 1; color: #333; }');
        printWindow.document.write('.letter-footer { border-top: 2px solid #0B5ED7; padding-top: 15px; margin-top: 15px; text-align: center; font-size: 12px; color: #888; }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(content);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        
        setTimeout(function() {
            printWindow.print();
        }, 500);
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
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }

    console.log('%c👨‍⚕️ Refer Patient', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Internal: Select doctor from dropdown', 'font-size:13px; color:#059669;');
    console.log('%c📋 External: Form with Braick logo', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>