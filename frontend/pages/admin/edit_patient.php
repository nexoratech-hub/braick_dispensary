<?php
// ================================================================
// FILE: frontend/pages/admin/edit_patient.php
// EDIT PATIENT - UPDATE PATIENT INFORMATION
// BRAICK DISPENSARY - USING EXISTING DB TABLES
// ✅ Uses SHARED header & sidebar
// ✅ Page-specific CSS only
// ✅ Dark mode inatumia header toggle
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER HAS ADMIN ACCESS
// ================================================================
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

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// VARIABLES
// ================================================================
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';
$message = '';
$message_type = '';
$unread_notifications = 0;

if ($patient_id <= 0) {
    header('Location: patients.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// GET PATIENT DATA
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, b.name as branch_name, u.full_name as assigned_doctor_name
    FROM patients p
    LEFT JOIN branches b ON p.branch_id = b.id
    LEFT JOIN users u ON p.assigned_doctor_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: patients.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET BRANCHES AND DOCTORS FOR SELECTORS
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get doctors for the patient's branch only
$doctors_list = [];
if ($patient['branch_id']) {
    $stmt = $db->prepare("
        SELECT id, full_name, specialty 
        FROM users 
        WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
        ORDER BY full_name
    ");
    $stmt->execute([$patient['branch_id']]);
    $doctors_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// PREDEFINED ALLERGIES LIST
// ================================================================
$predefined_allergies = [
    'Penicillin',
    'Sulfa Drugs',
    'Aspirin',
    'Ibuprofen',
    'Codeine',
    'Morphine',
    'Tetracycline',
    'Cephalosporins',
    'Erythromycin',
    'Metronidazole',
    'Peanuts',
    'Tree Nuts',
    'Milk',
    'Eggs',
    'Soy',
    'Wheat',
    'Shellfish',
    'Fish',
    'Sesame',
    'Latex',
    'Iodine',
    'Bees',
    'Wasps',
    'Pollen',
    'Dust Mites',
    'Mold',
    'Pet Dander',
    'Nickel',
    'Caffeine',
    'Alcohol'
];

// Parse existing allergies into array
$current_allergies = [];
if (!empty($patient['allergies'])) {
    $current_allergies = array_map('trim', explode(',', $patient['allergies']));
    $current_allergies = array_filter($current_allergies);
}

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $full_name = trim($_POST['full_name'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    $gender = $_POST['gender'] ?? null;
    $marital_status = $_POST['marital_status'] ?? null;
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $blood_group = $_POST['blood_group'] ?? null;
    
    // Handle allergies - combine selected and custom
    $selected_allergies = $_POST['allergies_select'] ?? [];
    $custom_allergies = trim($_POST['allergies_custom'] ?? '');
    
    $allergies_array = [];
    
    // Add selected predefined allergies
    if (!empty($selected_allergies)) {
        $allergies_array = array_merge($allergies_array, $selected_allergies);
    }
    
    // Add custom allergies (comma separated)
    if (!empty($custom_allergies)) {
        $custom_items = array_map('trim', explode(',', $custom_allergies));
        $custom_items = array_filter($custom_items);
        $allergies_array = array_merge($allergies_array, $custom_items);
    }
    
    // Remove duplicates and empty values
    $allergies_array = array_unique($allergies_array);
    $allergies_array = array_filter($allergies_array);
    $allergies = implode(', ', $allergies_array);
    
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $assigned_doctor_id = $_POST['assigned_doctor_id'] ?? null;
    if ($assigned_doctor_id == '') {
        $assigned_doctor_id = null;
    } else {
        $assigned_doctor_id = (int)$assigned_doctor_id;
    }
    
    // Validation
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if ($branch_id <= 0) {
        $errors[] = 'Branch is required';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE patients 
                SET full_name = ?, date_of_birth = ?, gender = ?, marital_status = ?, 
                    phone = ?, email = ?, address = ?, emergency_contact = ?, 
                    blood_group = ?, allergies = ?, branch_id = ?, assigned_doctor_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $full_name,
                $date_of_birth,
                $gender,
                $marital_status,
                $phone,
                $email,
                $address,
                $emergency_contact,
                $blood_group,
                $allergies,
                $branch_id,
                $assigned_doctor_id,
                $patient_id
            ]);
            
            // Log activity
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                    VALUES (?, ?, 'patient_updated', ?, NOW())
                ");
                $details = "Patient updated: $full_name (ID: {$patient['patient_id']}) by " . $user_full_name;
                $stmt->execute([$user_id, $branch_id, $details]);
            } catch (Exception $e) {
                // Silent fail
            }
            
            $message = "Patient '$full_name' updated successfully!";
            $message_type = 'success';
            
            // Refresh patient data
            $stmt = $db->prepare("
                SELECT p.*, b.name as branch_name, u.full_name as assigned_doctor_name
                FROM patients p
                LEFT JOIN branches b ON p.branch_id = b.id
                LEFT JOIN users u ON p.assigned_doctor_id = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Refresh doctors list for the new branch
            if ($branch_id) {
                $stmt = $db->prepare("
                    SELECT id, full_name, specialty 
                    FROM users 
                    WHERE role = 'doctor' AND status = 'active' AND branch_id = ?
                    ORDER BY full_name
                ");
                $stmt->execute([$branch_id]);
                $doctors_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Refresh current allergies
            $current_allergies = $allergies_array;
            
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --pat-primary: #0B5ED7;
        --pat-primary-dark: #0A4CA8;
        --pat-primary-light: #6EA8FE;
        --pat-primary-bg: #E8F0FE;
        --pat-primary-gradient: linear-gradient(135deg, #0B5ED7, #1A73E8);
        --pat-success: #059669;
        --pat-success-bg: #D1FAE5;
        --pat-danger: #DC2626;
        --pat-danger-bg: #FEE2E2;
        --pat-warning: #D97706;
        --pat-warning-bg: #FEF3C7;
        --pat-purple: #7C3AED;
        --pat-purple-bg: #EDE9FE;
        --pat-bg-body: #F0F4F8;
        --pat-bg-card: #FFFFFF;
        --pat-text-primary: #1E293B;
        --pat-text-secondary: #64748B;
        --pat-border-color: #E2E8F0;
        --pat-radius: 10px;
        --pat-radius-lg: 14px;
        --pat-shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
        --pat-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    }

    [data-theme="dark"] {
        --pat-bg-body: #0F172A;
        --pat-bg-card: #1E293B;
        --pat-text-primary: #F1F5F9;
        --pat-text-secondary: #94A3B8;
        --pat-border-color: #334155;
        --pat-primary-bg: #1E3A5F;
    }

    /* ================================================================
       DARK MODE - PAGE YOTE
       ================================================================ */
    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
        color: #F1F5F9;
    }

    /* ================================================================
       FORM CARD
       ================================================================ */
    .form-card-custom {
        background: var(--pat-bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 1px solid var(--pat-border-color);
        transition: all 0.3s;
        max-width: 900px;
        margin: 0 auto;
    }

    .form-card-custom:hover {
        border-color: var(--pat-primary);
        box-shadow: var(--pat-shadow-md);
    }

    .form-header-custom {
        display: flex;
        align-items: center;
        gap: 16px;
        padding-bottom: 16px;
        margin-bottom: 20px;
        border-bottom: 2px solid var(--pat-border-color);
    }

    .form-header-custom .form-header-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
        background: var(--pat-primary-gradient);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    .form-header-custom h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--pat-text-primary);
        margin: 0;
    }

    .form-header-custom p {
        font-size: 0.85rem;
        color: var(--pat-text-secondary);
        margin: 0;
    }

    /* ================================================================
       FORM CONTROLS
       ================================================================ */
    .form-label-custom {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--pat-text-primary);
        margin-bottom: 4px;
        display: block;
    }

    .form-label-custom .required {
        color: #EF4444;
        margin-left: 2px;
    }

    .form-control-custom {
        width: 100%;
        padding: 8px 14px;
        border: 2px solid var(--pat-border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--pat-bg-card);
        color: var(--pat-text-primary);
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }

    .form-control-custom:focus {
        border-color: var(--pat-primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }

    .form-control-custom::placeholder {
        color: var(--pat-text-secondary);
        opacity: 0.5;
    }

    .form-control-custom:disabled {
        background: var(--pat-bg-body);
        cursor: not-allowed;
        opacity: 0.7;
    }

    [data-theme="dark"] .form-control-custom option {
        background: #1E293B;
        color: #F1F5F9;
    }

    select.form-control-custom {
        appearance: auto;
        -webkit-appearance: auto;
    }

    textarea.form-control-custom {
        resize: vertical;
        min-height: 60px;
    }

    .form-row-icon-custom {
        position: relative;
    }

    .form-row-icon-custom .form-control-custom {
        padding-left: 40px;
    }

    .form-row-icon-custom .input-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--pat-text-secondary);
        font-size: 0.9rem;
        pointer-events: none;
        transition: color 0.3s ease;
    }

    .form-row-icon-custom .form-control-custom:focus ~ .input-icon,
    .form-row-icon-custom .form-control-custom:focus + .input-icon {
        color: var(--pat-primary);
    }

    .help-text-custom {
        font-size: 0.7rem;
        color: var(--pat-text-secondary);
        margin-top: 3px;
    }

    .section-divider-custom {
        border: none;
        border-top: 2px dashed var(--pat-border-color);
        margin: 16px 0;
    }

    .section-title-custom {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--pat-primary);
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }

    [data-theme="dark"] .section-title-custom {
        color: #6EA8FE;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 40px;
        font-family: inherit;
    }

    .btn-primary-custom {
        background: var(--pat-primary-gradient);
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
    }

    .btn-primary-custom:hover {
        background: linear-gradient(135deg, #0A4CA8, #1557B0);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4);
        color: white;
    }

    .btn-outline-custom {
        background: transparent;
        color: var(--pat-text-primary);
        border: 2px solid var(--pat-border-color);
    }

    .btn-outline-custom:hover {
        background: var(--pat-bg-body);
        border-color: var(--pat-primary);
        color: var(--pat-primary);
        transform: translateY(-2px);
    }

    [data-theme="dark"] .btn-outline-custom:hover {
        background: #0F172A;
        border-color: #6EA8FE;
        color: #6EA8FE;
    }

    .btn-sm-custom {
        padding: 4px 14px;
        font-size: 0.75rem;
        min-height: 32px;
    }

    .form-actions-custom {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 20px;
        margin-top: 20px;
        border-top: 2px solid var(--pat-border-color);
    }

    /* ================================================================
       ALLERGY TAGS
       ================================================================ */
    .allergy-tags-custom {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 8px 12px;
        border: 2px solid var(--pat-border-color);
        border-radius: 10px;
        min-height: 50px;
        background: var(--pat-bg-card);
        margin-top: 4px;
    }

    .allergy-tag-custom {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        background: var(--pat-primary-bg);
        color: var(--pat-primary);
        border: 1px solid rgba(11, 94, 215, 0.2);
    }

    [data-theme="dark"] .allergy-tag-custom {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: rgba(110, 168, 254, 0.2);
    }

    .allergy-tag-custom .remove-allergy {
        cursor: pointer;
        color: #EF4444;
        font-size: 0.8rem;
        margin-left: 2px;
        transition: all 0.3s;
    }

    .allergy-tag-custom .remove-allergy:hover {
        transform: scale(1.2);
    }

    .allergy-select-container-custom {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
        margin-top: 8px;
    }

    .allergy-select-container-custom select {
        flex: 1;
        min-width: 200px;
    }

    .allergy-select-container-custom .btn-add-allergy {
        padding: 6px 16px;
        border-radius: 8px;
        background: var(--pat-primary);
        color: white;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .allergy-select-container-custom .btn-add-allergy:hover {
        background: var(--pat-primary-dark);
        transform: translateY(-1px);
    }

    .allergy-custom-input-custom {
        margin-top: 8px;
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .allergy-custom-input-custom input {
        flex: 1;
    }

    .allergy-custom-input-custom .btn-add-custom {
        padding: 6px 16px;
        border-radius: 8px;
        background: var(--pat-success);
        color: white;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .allergy-custom-input-custom .btn-add-custom:hover {
        background: #047857;
        transform: translateY(-1px);
    }

    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header-custom {
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
        color: white;
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
    }

    .page-header-custom .page-subtitle {
        color: rgba(255,255,255,0.85);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 6px;
    }

    .page-header-custom .header-badge-custom {
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
        border: 1px solid rgba(255,255,255,0.15);
    }

    .page-header-custom .btn-outline-light {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s;
    }

    .page-header-custom .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateX(-3px);
        color: white;
    }

    /* ================================================================
       ALERTS
       ================================================================ */
    .alert-custom {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        font-weight: 500;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-custom.alert-success {
        background: #D1FAE5;
        color: #065F46;
        border: 2px solid #6EE7B7;
    }

    .alert-custom.alert-error {
        background: #FEE2E2;
        color: #991B1B;
        border: 2px solid #FCA5A5;
    }

    [data-theme="dark"] .alert-custom.alert-success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }

    [data-theme="dark"] .alert-custom.alert-error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-custom {
        padding: 14px 0;
        border-top: 1px solid var(--pat-border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--pat-text-secondary);
    }

    .footer-custom .footer-brand {
        color: var(--pat-primary);
        font-weight: 600;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 640px) {
        .form-card-custom { padding: 16px 14px; }
        .form-header-custom { flex-direction: column; text-align: center; }
        .form-header-custom .form-header-icon { width: 44px; height: 44px; font-size: 1.2rem; }
        .btn-custom { padding: 6px 14px; font-size: 0.8rem; min-height: 36px; }
        .form-actions-custom { flex-direction: column; }
        .form-actions-custom .btn-custom { width: 100%; justify-content: center; }
        .form-row-icon-custom .form-control-custom { padding-left: 34px; }
        .allergy-select-container-custom { flex-direction: column; }
        .allergy-select-container-custom select { width: 100%; min-width: unset; }
        .allergy-custom-input-custom { flex-direction: column; }
        .allergy-custom-input-custom input { width: 100%; }
        .page-header-custom { padding: 18px 20px; }
        .page-header-custom .page-title { font-size: 1.3rem; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-edit"></i> Edit Patient
            </h1>
            <p class="page-subtitle">
                Update patient information
                <span class="header-badge-custom">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($patient['full_name']) ?>
                </span>
                <span class="header-badge-custom">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id']) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="patient_details.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Patient
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert-custom alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.2rem;flex-shrink:0;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- EDIT PATIENT FORM -->
    <!-- ================================================================ -->
    <div class="form-card-custom">
        <div class="form-header-custom">
            <div class="form-header-icon">
                <i class="fas fa-user-edit"></i>
            </div>
            <div>
                <h3>Edit Patient Information</h3>
                <p>Update personal details, contact information and medical history</p>
            </div>
        </div>
        
        <form method="POST" action="" id="editPatientForm">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;" class="form-grid-responsive">
                
                <!-- ================================================================ -->
                <!-- Personal Information -->
                <!-- ================================================================ -->
                <div style="grid-column: 1 / -1;">
                    <h3 class="section-title-custom">
                        <i class="fas fa-user-circle"></i> Personal Information
                    </h3>
                    <hr class="section-divider-custom">
                </div>
                
                <!-- Full Name -->
                <div>
                    <label class="form-label-custom">
                        <i class="fas fa-user" style="color:#0B5ED7;"></i> Full Name
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon-custom">
                        <input type="text" name="full_name" class="form-control-custom" 
                               placeholder="Enter full name" 
                               value="<?= htmlspecialchars($patient['full_name']) ?>" required>
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                    </div>
                </div>
                
                <!-- Patient ID (Read-only) -->
                <div>
                    <label class="form-label-custom">
                        <i class="fas fa-id-card" style="color:#0B5ED7;"></i> Patient ID
                    </label>
                    <div class="form-row-icon-custom">
                        <input type="text" class="form-control-custom" 
                               value="<?= htmlspecialchars($patient['patient_id']) ?>" disabled>
                        <span class="input-icon"><i class="fas fa-id-card"></i></span>
                    </div>
                    <p class="help-text-custom">Patient ID cannot be changed</p>
                </div>
                
                <!-- Date of Birth -->
                <div>
                    <label class="form-label-custom">
                        <i class="fas fa-calendar-alt" style="color:#0B5ED7;"></i> Date of Birth
                    </label>
                    <div class="form-row-icon-custom">
                        <input type="date" name="date_of_birth" class="form-control-custom" 
                               value="<?= $patient['date_of_birth'] ?>">
                        <span class="input-icon"><i class="fas fa-calendar-alt"></i></span>
                    </div>
                </div>
                
                <!-- Gender -->
                <div>
                    <label class="form-label-custom">
                        <i class="fas fa-venus-mars" style="color:#0B5ED7;"></i> Gender
                    </label>
                    <div class="form-row-icon-custom">
                        <select name="gender" class="form-control-custom">
                            <option value="">Select Gender</option>
                            <option value="Male" <?= $patient['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $patient['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= $patient['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                        <span class="input-icon"><i class="fas fa-venus-mars"></i></span>
                    </div>
                </div>
                
                <!-- Marital Status -->
                <div>
                    <label class="form-label-custom">
                        <i class="fas fa-ring" style="color:#0B5ED7;"></i> Marital Status
                    </label>
                    <div class="form-row-icon-custom">
                        <select name="marital_status" class="form-control-custom">
                            <option value="">Select Marital Status</option>
                            <option value="Single" <?= $patient['marital_status'] === 'Single' ? 'selected' : '' ?>>Single</option>
                            <option value="Married" <?= $patient['marital_status'] === 'Married' ? 'selected' : '' ?>>Married</option>
                            <option value="Divorced" <?= $patient['marital_status'] === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                            <option value="Widowed" <?= $patient['marital_status'] === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                        </select>
                        <span class="input-icon"><i class="fas fa-ring"></i></span>
                    </div>
                </div>
                
                <!-- Blood Group -->
                <div>
                    <label class="form-label-custom">
                        <i class="fas fa-tint" style="color:#0B5ED7;"></i> Blood Group
                    </label>
                    <div class="form-row-icon-custom">
                        <select name="blood_group" class="form-control-custom">
                            <option value="">Select Blood Group</option>
                            <option value="A+" <?= $patient['blood_group'] === 'A+' ? 'selected' : '' ?>>A+</option>
                            <option value="A-" <?= $patient['blood_group'] === 'A-' ? 'selected' : '' ?>>A-</option>
                            <option value="B+" <?= $patient['blood_group'] === 'B+' ? 'selected' : '' ?>>B+</option>
                            <option value="B-" <?= $patient['blood_group'] === 'B-' ? 'selected' : '' ?>>B-</option>
                            <option value="AB+" <?= $patient['blood_group'] === 'AB+' ? 'selected' : '' ?>>AB+</option>
                            <option value="AB-" <?= $patient['blood_group'] === 'AB-' ? 'selected' : '' ?>>AB-</option>
                            <option value="O+" <?= $patient['blood_group'] === 'O+' ? 'selected' : '' ?>>O+</option>
                            <option value="O-" <?= $patient['blood_group'] === 'O-' ? 'selected' : '' ?>>O-</option>
                        </select>
                        <span class="input-icon"><i class="fas fa-tint"></i></span>
                    </div>
                </div>
                
                <!-- ================================================================ -->
                <!-- Contact Information -->
                <!-- ================================================================ -->
                <div style="grid-column: 1 / -1;">
                    <h3 class="section-title-custom">
                        <i class="fas fa-address-card"></i> Contact Information
                    </h3>
                    <hr class="section-divider-custom">
                </div>
                
                <!-- Phone -->
                <div>
                    <label class="form-label-custom">
                        <i class="fas fa-phone" style="color:#0B5ED7;"></i> Phone Number
                    </label>
                    <div class="form-row-icon-custom">
                        <input type="tel" name="phone" class="form-control-custom" 
                               placeholder="Enter phone number" 
                               value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
                        <span class="input-icon"><i class="fas fa-phone"></i></span>
                    </div>
                </div>
                
                <!-- Email -->
                <div>
                    <label class="form-label-custom">
                        <i class="fas fa-envelope" style="color:#0B5ED7;"></i> Email Address
                    </label>
                    <div class="form-row-icon-custom">
                        <input type="email" name="email" class="form-control-custom" 
                               placeholder="Enter email address" 
                               value="<?= htmlspecialchars($patient['email'] ?? '') ?>">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                    </div>
                </div>
                
                <!-- Address -->
                <div>
                    <label class="form-label-custom">
                        <i class="fas fa-map-marker-alt" style="color:#0B5ED7;"></i> Address
                    </label>
                    <div class="form-row-icon-custom">
                        <input type="text" name="address" class="form-control-custom" 
                               placeholder="Enter address" 
                               value="<?= htmlspecialchars($patient['address'] ?? '') ?>">
                        <span class="input-icon"><i class="fas fa-map-marker-alt"></i></span>
                    </div>
                </div>
                
                <!-- Emergency Contact -->
                <div>
                    <label class="form-label-custom">
                        <i class="fas fa-phone-alt" style="color:#0B5ED7;"></i> Emergency Contact
                    </label>
                    <div class="form-row-icon-custom">
                        <input type="text" name="emergency_contact" class="form-control-custom" 
                               placeholder="Enter emergency contact number" 
                               value="<?= htmlspecialchars($patient['emergency_contact'] ?? '') ?>">
                        <span class="input-icon"><i class="fas fa-phone-alt"></i></span>
                    </div>
                </div>
                
                <!-- ================================================================ -->
                <!-- Allergies -->
                <!-- ================================================================ -->
                <div style="grid-column: 1 / -1;">
                    <h3 class="section-title-custom">
                        <i class="fas fa-allergies"></i> Allergies
                    </h3>
                    <hr class="section-divider-custom">
                </div>
                
                <div style="grid-column: 1 / -1;">
                    <label class="form-label-custom">
                        <i class="fas fa-allergies" style="color:#0B5ED7;"></i> Allergies
                    </label>
                    
                    <!-- Allergy Tags Display -->
                    <div class="allergy-tags-custom" id="allergyTagsContainer">
                        <?php foreach ($current_allergies as $allergy): ?>
                            <span class="allergy-tag-custom" data-allergy="<?= htmlspecialchars($allergy) ?>">
                                <?= htmlspecialchars($allergy) ?>
                                <span class="remove-allergy" onclick="removeAllergy(this)" title="Remove allergy">✕</span>
                            </span>
                        <?php endforeach; ?>
                        <?php if (empty($current_allergies)): ?>
                            <span style="color:var(--pat-text-secondary);font-size:0.85rem;" id="noAllergyMessage">No allergies added</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Hidden input to store allergies -->
                    <input type="hidden" name="allergies_hidden" id="allergiesHidden" value="<?= htmlspecialchars($patient['allergies'] ?? '') ?>">
                    
                    <!-- Select Predefined Allergies -->
                    <div class="allergy-select-container-custom">
                        <select id="allergySelect" class="form-control-custom">
                            <option value="">Select a common allergy...</option>
                            <?php foreach ($predefined_allergies as $allergy): ?>
                                <option value="<?= htmlspecialchars($allergy) ?>"><?= htmlspecialchars($allergy) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn-add-allergy" onclick="addSelectedAllergy()">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                    
                    <!-- Custom Allergy Input -->
                    <div class="allergy-custom-input-custom">
                        <input type="text" id="customAllergyInput" class="form-control-custom" 
                               placeholder="Enter custom allergy..." 
                               onkeypress="if(event.key==='Enter'){event.preventDefault();addCustomAllergy();}">
                        <button type="button" class="btn-add-custom" onclick="addCustomAllergy()">
                            <i class="fas fa-plus"></i> Add Custom
                        </button>
                    </div>
                    
                    <p class="help-text-custom">Select from common allergies or type your own. Click ✕ to remove an allergy.</p>
                </div>
                
                <!-- ================================================================ -->
                <!-- Assignment Information -->
                <!-- ================================================================ -->
                <div style="grid-column: 1 / -1;">
                    <h3 class="section-title-custom">
                        <i class="fas fa-user-md"></i> Assignment Information
                    </h3>
                    <hr class="section-divider-custom">
                </div>
                
                <!-- Branch -->
                <div>
                    <label class="form-label-custom">
                        <i class="fas fa-store-alt" style="color:#0B5ED7;"></i> Branch
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon-custom">
                        <select name="branch_id" id="branchSelect" class="form-control-custom" required onchange="loadDoctors(this.value)">
                            <option value="">Select Branch</option>
                            <?php foreach ($branches_list as $branch): ?>
                                <option value="<?= $branch['id'] ?>" <?= $branch['id'] == $patient['branch_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($branch['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="input-icon"><i class="fas fa-store-alt"></i></span>
                    </div>
                </div>
                
                <!-- Assigned Doctor -->
                <div>
                    <label class="form-label-custom">
                        <i class="fas fa-user-md" style="color:#0B5ED7;"></i> Assigned Doctor
                    </label>
                    <div class="form-row-icon-custom">
                        <select name="assigned_doctor_id" id="doctorSelect" class="form-control-custom">
                            <option value="">None</option>
                            <?php foreach ($doctors_list as $doctor): ?>
                                <option value="<?= $doctor['id'] ?>" <?= $doctor['id'] == $patient['assigned_doctor_id'] ? 'selected' : '' ?>>
                                    Dr. <?= htmlspecialchars($doctor['full_name']) ?> <?= $doctor['specialty'] ? '(' . htmlspecialchars($doctor['specialty']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="input-icon"><i class="fas fa-user-md"></i></span>
                    </div>
                    <p class="help-text-custom">Only doctors from the selected branch are shown</p>
                </div>
                
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions-custom">
                <button type="submit" class="btn-custom btn-primary-custom">
                    <i class="fas fa-save"></i> Update Patient
                </button>
                <a href="patient_details.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" class="btn-custom btn-outline-custom">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="reset" class="btn-custom btn-outline-custom">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-custom">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Edit Patient
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // TOAST (page-specific)
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 9999;
            max-width: 400px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            animation: slideInToast 0.4s ease;
            font-size: 0.85rem;
            font-weight: 500;
        `;
        
        var bgColor = type === 'success' ? '#059669' : 
                      type === 'error' ? '#DC2626' : 
                      type === 'warning' ? '#D97706' : '#0B5ED7';
        toast.style.background = bgColor;
        
        var icon = type === 'success' ? 'fa-check-circle' : 
                   type === 'error' ? 'fa-exclamation-circle' : 
                   type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
        
        toast.innerHTML = `
            <i class="fas ${icon}" style="font-size:1.1rem;"></i>
            <div>
                <p style="font-weight:600;font-size:0.85rem;margin:0;">${title}</p>
                <p style="font-size:0.75rem;opacity:0.9;margin:2px 0 0 0;">${message}</p>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(function() {
            toast.style.animation = 'slideOutToast 0.4s ease';
            setTimeout(function() { toast.remove(); }, 400);
        }, 3000);
    }

    if (!document.getElementById('toastAnimations')) {
        var style = document.createElement('style');
        style.id = 'toastAnimations';
        style.textContent = `
            @keyframes slideInToast {
                from { transform: translateX(120%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutToast {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(120%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    }

    // ================================================================
    // LOAD DOCTORS BY BRANCH (AJAX)
    // ================================================================
    function loadDoctors(branchId) {
        var doctorSelect = document.getElementById('doctorSelect');
        var currentPatientDoctor = '<?= $patient['assigned_doctor_id'] ?>';
        
        doctorSelect.innerHTML = '<option value="">None</option>';
        
        if (branchId) {
            doctorSelect.innerHTML = '<option value="">Loading doctors...</option>';
            
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '../../../backend/ajax/get_doctors_by_branch.php?branch_id=' + encodeURIComponent(branchId), true);
            xhr.onload = function() {
                if (this.status === 200) {
                    try {
                        var data = JSON.parse(this.responseText);
                        doctorSelect.innerHTML = '<option value="">None</option>';
                        
                        if (data.success && data.doctors) {
                            data.doctors.forEach(function(doctor) {
                                var option = document.createElement('option');
                                option.value = doctor.id;
                                option.textContent = 'Dr. ' + doctor.full_name + (doctor.specialty ? ' (' + doctor.specialty + ')' : '');
                                if (doctor.id == currentPatientDoctor) {
                                    option.selected = true;
                                }
                                doctorSelect.appendChild(option);
                            });
                        }
                    } catch (e) {
                        console.error('Error parsing doctors data:', e);
                    }
                }
            };
            xhr.onerror = function() {
                console.error('Error loading doctors');
                doctorSelect.innerHTML = '<option value="">Error loading doctors</option>';
            };
            xhr.send();
        }
    }

    // ================================================================
    // ALLERGIES MANAGEMENT
    // ================================================================
    var allergies = <?= json_encode($current_allergies) ?>;
    
    function updateAllergyTags() {
        var container = document.getElementById('allergyTagsContainer');
        var hidden = document.getElementById('allergiesHidden');
        
        container.innerHTML = '';
        
        if (allergies.length === 0) {
            container.innerHTML = '<span style="color:var(--pat-text-secondary);font-size:0.85rem;" id="noAllergyMessage">No allergies added</span>';
            hidden.value = '';
            return;
        }
        
        allergies.forEach(function(allergy) {
            var tag = document.createElement('span');
            tag.className = 'allergy-tag-custom';
            tag.dataset.allergy = allergy;
            tag.innerHTML = allergy + ' <span class="remove-allergy" onclick="removeAllergy(this)" title="Remove allergy">✕</span>';
            container.appendChild(tag);
        });
        
        hidden.value = allergies.join(', ');
    }
    
    function addSelectedAllergy() {
        var select = document.getElementById('allergySelect');
        var allergy = select.value;
        
        if (!allergy) {
            showToast('Info', 'Please select an allergy from the list', 'info');
            return;
        }
        
        if (allergies.includes(allergy)) {
            showToast('Info', 'This allergy is already added', 'warning');
            return;
        }
        
        allergies.push(allergy);
        updateAllergyTags();
        select.value = '';
        showToast('Success', 'Allergy added: ' + allergy, 'success');
    }
    
    function addCustomAllergy() {
        var input = document.getElementById('customAllergyInput');
        var allergy = input.value.trim();
        
        if (!allergy) {
            showToast('Info', 'Please enter an allergy', 'info');
            return;
        }
        
        if (allergies.includes(allergy)) {
            showToast('Info', 'This allergy is already added', 'warning');
            input.value = '';
            return;
        }
        
        allergies.push(allergy);
        updateAllergyTags();
        input.value = '';
        showToast('Success', 'Custom allergy added: ' + allergy, 'success');
    }
    
    function removeAllergy(element) {
        var tag = element.closest('.allergy-tag-custom');
        var allergy = tag.dataset.allergy;
        var index = allergies.indexOf(allergy);
        
        if (index > -1) {
            allergies.splice(index, 1);
            updateAllergyTags();
            showToast('Info', 'Allergy removed: ' + allergy, 'info');
        }
    }

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    document.getElementById('editPatientForm')?.addEventListener('submit', function(e) {
        var fullName = document.querySelector('input[name="full_name"]').value.trim();
        var branch = document.querySelector('select[name="branch_id"]').value;
        
        if (fullName === '') {
            e.preventDefault();
            alert('⚠️ Please enter the patient\'s full name.');
            document.querySelector('input[name="full_name"]').focus();
            return false;
        }
        
        if (branch === '') {
            e.preventDefault();
            alert('⚠️ Please select a branch.');
            document.querySelector('select[name="branch_id"]').focus();
            return false;
        }
        
        document.getElementById('allergiesHidden').value = allergies.join(', ');
        
        return true;
    });

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
    // RESPONSIVE FORM GRID
    // ================================================================
    (function() {
        function adjustFormGrid() {
            var grid = document.querySelector('.form-grid-responsive');
            if (!grid) return;
            
            if (window.innerWidth <= 768) {
                grid.style.gridTemplateColumns = '1fr';
            } else {
                grid.style.gridTemplateColumns = '1fr 1fr';
            }
        }
        
        adjustFormGrid();
        window.addEventListener('resize', adjustFormGrid);
    })();

    console.log('%c🏥 Braick Dispensary - Edit Patient', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 ID: <?= htmlspecialchars($patient['patient_id']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💊 Allergies: <?= count($current_allergies) ?> items (pick + custom)', 'font-size:13px; color:#7B2FBE;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>