<?php
// ================================================================
// FILE: frontend/pages/admin/edit_patient.php
// EDIT PATIENT - UPDATE PATIENT INFORMATION
// BRAICK DISPENSARY - USING EXISTING DB TABLES
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

<style>
    /* ================================================================
       FORM STYLES
       ================================================================ */
    
    .form-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 1px solid var(--border-color);
        transition: all 0.3s;
        max-width: 900px;
        margin: 0 auto;
    }
    
    .form-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.05);
    }
    
    .form-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding-bottom: 16px;
        margin-bottom: 20px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .form-header-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .form-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }
    
    .form-header p {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin: 0;
    }
    
    .form-label {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
        display: block;
    }
    
    .form-label .required {
        color: #EF4444;
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
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    
    .form-control:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.5;
    }
    
    .form-control:disabled {
        background: var(--bg-body);
        cursor: not-allowed;
        opacity: 0.7;
    }
    
    select.form-control {
        appearance: auto;
        -webkit-appearance: auto;
    }
    
    textarea.form-control {
        resize: vertical;
        min-height: 60px;
    }
    
    .form-row-icon {
        position: relative;
    }
    
    .form-row-icon .form-control {
        padding-left: 40px;
    }
    
    .form-row-icon .input-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        font-size: 0.9rem;
        pointer-events: none;
        transition: color 0.3s ease;
    }
    
    .form-row-icon .form-control:focus ~ .input-icon,
    .form-row-icon .form-control:focus + .input-icon {
        color: #0B5ED7;
    }
    
    .help-text {
        font-size: 0.7rem;
        color: var(--text-secondary);
        margin-top: 3px;
    }
    
    .section-divider {
        border: none;
        border-top: 2px dashed var(--border-color);
        margin: 16px 0;
    }
    
    .section-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #0B5ED7;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    
    [data-theme="dark"] .section-title {
        color: #6EA8FE;
    }
    
    .btn {
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
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #0A4CA8, #1557B0);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-primary);
        border: 2px solid var(--border-color);
    }
    
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }
    
    .btn-sm {
        padding: 4px 14px;
        font-size: 0.75rem;
        min-height: 32px;
    }
    
    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 20px;
        margin-top: 20px;
        border-top: 2px solid var(--border-color);
    }
    
    /* Allergies Tags */
    .allergy-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 8px 12px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        min-height: 50px;
        background: var(--bg-card);
        margin-top: 4px;
    }
    
    .allergy-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        background: #E8F0FE;
        color: #0B5ED7;
        border: 1px solid rgba(11, 94, 215, 0.2);
    }
    
    [data-theme="dark"] .allergy-tag {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: rgba(110, 168, 254, 0.2);
    }
    
    .allergy-tag .remove-allergy {
        cursor: pointer;
        color: #EF4444;
        font-size: 0.8rem;
        margin-left: 2px;
        transition: all 0.3s;
    }
    
    .allergy-tag .remove-allergy:hover {
        transform: scale(1.2);
    }
    
    .allergy-select-container {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
        margin-top: 8px;
    }
    
    .allergy-select-container select {
        flex: 1;
        min-width: 200px;
    }
    
    .allergy-select-container .btn-add-allergy {
        padding: 6px 16px;
        border-radius: 8px;
        background: #0B5ED7;
        color: white;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s;
        white-space: nowrap;
    }
    
    .allergy-select-container .btn-add-allergy:hover {
        background: #0A4CA8;
        transform: translateY(-1px);
    }
    
    .allergy-custom-input {
        margin-top: 8px;
        display: flex;
        gap: 8px;
        align-items: center;
    }
    
    .allergy-custom-input input {
        flex: 1;
    }
    
    .allergy-custom-input .btn-add-custom {
        padding: 6px 16px;
        border-radius: 8px;
        background: #059669;
        color: white;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s;
        white-space: nowrap;
    }
    
    .allergy-custom-input .btn-add-custom:hover {
        background: #047857;
        transform: translateY(-1px);
    }
    
    /* Responsive */
    @media (max-width: 640px) {
        .form-card {
            padding: 16px 14px;
        }
        .form-header {
            flex-direction: column;
            text-align: center;
        }
        .form-header-icon {
            width: 44px;
            height: 44px;
            font-size: 1.2rem;
        }
        .btn {
            padding: 6px 14px;
            font-size: 0.8rem;
            min-height: 36px;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .form-row-icon .form-control {
            padding-left: 34px;
        }
        .allergy-select-container {
            flex-direction: column;
        }
        .allergy-select-container select {
            width: 100%;
            min-width: unset;
        }
        .allergy-custom-input {
            flex-direction: column;
        }
        .allergy-custom-input input {
            width: 100%;
        }
    }
</style>

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
            <form method="GET" action="patients.php" class="flex-1 flex">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                <input type="text" name="search" placeholder="Search patients..." 
                       class="flex-1 px-3 py-2 bg-transparent border-none outline-none text-sm" 
                       style="color: var(--text-primary);">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
            </form>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches_list as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-user-edit mr-2" style="color: #0B5ED7;"></i> Edit Patient
            </h1>
            <p class="page-subtitle">
                Update patient information
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($patient['full_name']) ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-id-card mr-1"></i> <?= htmlspecialchars($patient['patient_id']) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="patient_details.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Patient
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
    <!-- EDIT PATIENT FORM -->
    <!-- ================================================================ -->
    <div class="form-card">
        <div class="form-header">
            <div class="form-header-icon">
                <i class="fas fa-user-edit"></i>
            </div>
            <div>
                <h3>Edit Patient Information</h3>
                <p>Update personal details, contact information and medical history</p>
            </div>
        </div>
        
        <form method="POST" action="" id="editPatientForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- ================================================================ -->
                <!-- Personal Information -->
                <!-- ================================================================ -->
                <div class="md:col-span-2">
                    <h3 class="section-title">
                        <i class="fas fa-user-circle"></i> Personal Information
                    </h3>
                    <hr class="section-divider">
                </div>
                
                <!-- Full Name -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-user text-blue-600"></i> Full Name
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="full_name" class="form-control" 
                               placeholder="Enter full name" 
                               value="<?= htmlspecialchars($patient['full_name']) ?>" required>
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                    </div>
                </div>
                
                <!-- Patient ID (Read-only) -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-id-card text-blue-600"></i> Patient ID
                    </label>
                    <div class="form-row-icon">
                        <input type="text" class="form-control" 
                               value="<?= htmlspecialchars($patient['patient_id']) ?>" disabled>
                        <span class="input-icon"><i class="fas fa-id-card"></i></span>
                    </div>
                    <p class="help-text">Patient ID cannot be changed</p>
                </div>
                
                <!-- Date of Birth -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-calendar-alt text-blue-600"></i> Date of Birth
                    </label>
                    <div class="form-row-icon">
                        <input type="date" name="date_of_birth" class="form-control" 
                               value="<?= $patient['date_of_birth'] ?>">
                        <span class="input-icon"><i class="fas fa-calendar-alt"></i></span>
                    </div>
                </div>
                
                <!-- Gender -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-venus-mars text-blue-600"></i> Gender
                    </label>
                    <div class="form-row-icon">
                        <select name="gender" class="form-control">
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
                    <label class="form-label">
                        <i class="fas fa-ring text-blue-600"></i> Marital Status
                    </label>
                    <div class="form-row-icon">
                        <select name="marital_status" class="form-control">
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
                    <label class="form-label">
                        <i class="fas fa-tint text-blue-600"></i> Blood Group
                    </label>
                    <div class="form-row-icon">
                        <select name="blood_group" class="form-control">
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
                <div class="md:col-span-2">
                    <h3 class="section-title">
                        <i class="fas fa-address-card"></i> Contact Information
                    </h3>
                    <hr class="section-divider">
                </div>
                
                <!-- Phone -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-phone text-blue-600"></i> Phone Number
                    </label>
                    <div class="form-row-icon">
                        <input type="tel" name="phone" class="form-control" 
                               placeholder="Enter phone number" 
                               value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
                        <span class="input-icon"><i class="fas fa-phone"></i></span>
                    </div>
                </div>
                
                <!-- Email -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-envelope text-blue-600"></i> Email Address
                    </label>
                    <div class="form-row-icon">
                        <input type="email" name="email" class="form-control" 
                               placeholder="Enter email address" 
                               value="<?= htmlspecialchars($patient['email'] ?? '') ?>">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                    </div>
                </div>
                
                <!-- Address -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-map-marker-alt text-blue-600"></i> Address
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="address" class="form-control" 
                               placeholder="Enter address" 
                               value="<?= htmlspecialchars($patient['address'] ?? '') ?>">
                        <span class="input-icon"><i class="fas fa-map-marker-alt"></i></span>
                    </div>
                </div>
                
                <!-- Emergency Contact -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-phone-alt text-blue-600"></i> Emergency Contact
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="emergency_contact" class="form-control" 
                               placeholder="Enter emergency contact number" 
                               value="<?= htmlspecialchars($patient['emergency_contact'] ?? '') ?>">
                        <span class="input-icon"><i class="fas fa-phone-alt"></i></span>
                    </div>
                </div>
                
                <!-- ================================================================ -->
                <!-- Medical Information - Allergies with Pick and Custom -->
                <!-- ================================================================ -->
                <div class="md:col-span-2">
                    <h3 class="section-title">
                        <i class="fas fa-allergies"></i> Allergies
                    </h3>
                    <hr class="section-divider">
                </div>
                
                <div class="md:col-span-2">
                    <label class="form-label">
                        <i class="fas fa-allergies text-blue-600"></i> Allergies
                    </label>
                    
                    <!-- Allergy Tags Display -->
                    <div class="allergy-tags" id="allergyTagsContainer">
                        <?php foreach ($current_allergies as $allergy): ?>
                            <span class="allergy-tag" data-allergy="<?= htmlspecialchars($allergy) ?>">
                                <?= htmlspecialchars($allergy) ?>
                                <span class="remove-allergy" onclick="removeAllergy(this)" title="Remove allergy">✕</span>
                            </span>
                        <?php endforeach; ?>
                        <?php if (empty($current_allergies)): ?>
                            <span class="text-gray-400 text-sm" id="noAllergyMessage">No allergies added</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Hidden input to store allergies -->
                    <input type="hidden" name="allergies_hidden" id="allergiesHidden" value="<?= htmlspecialchars($patient['allergies'] ?? '') ?>">
                    
                    <!-- Select Predefined Allergies -->
                    <div class="allergy-select-container">
                        <select id="allergySelect" class="form-control">
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
                    <div class="allergy-custom-input">
                        <input type="text" id="customAllergyInput" class="form-control" 
                               placeholder="Enter custom allergy..." 
                               onkeypress="if(event.key==='Enter'){event.preventDefault();addCustomAllergy();}">
                        <button type="button" class="btn-add-custom" onclick="addCustomAllergy()">
                            <i class="fas fa-plus"></i> Add Custom
                        </button>
                    </div>
                    
                    <p class="help-text">Select from common allergies or type your own. Click ✕ to remove an allergy.</p>
                </div>
                
                <!-- ================================================================ -->
                <!-- Assignment Information -->
                <!-- ================================================================ -->
                <div class="md:col-span-2">
                    <h3 class="section-title">
                        <i class="fas fa-user-md"></i> Assignment Information
                    </h3>
                    <hr class="section-divider">
                </div>
                
                <!-- Branch -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-store-alt text-blue-600"></i> Branch
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <select name="branch_id" id="branchSelect" class="form-control" required onchange="loadDoctors(this.value)">
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
                
                <!-- Assigned Doctor (Only doctors from selected branch) -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-user-md text-blue-600"></i> Assigned Doctor
                    </label>
                    <div class="form-row-icon">
                        <select name="assigned_doctor_id" id="doctorSelect" class="form-control">
                            <option value="">None</option>
                            <?php foreach ($doctors_list as $doctor): ?>
                                <option value="<?= $doctor['id'] ?>" <?= $doctor['id'] == $patient['assigned_doctor_id'] ? 'selected' : '' ?>>
                                    Dr. <?= htmlspecialchars($doctor['full_name']) ?> <?= $doctor['specialty'] ? '(' . htmlspecialchars($doctor['specialty']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="input-icon"><i class="fas fa-user-md"></i></span>
                    </div>
                    <p class="help-text">Only doctors from the selected branch are shown</p>
                </div>
                
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Patient
                </button>
                <a href="patient_details.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Edit Patient
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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

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
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // LOAD DOCTORS BY BRANCH (AJAX)
    // ================================================================
    function loadDoctors(branchId) {
        var doctorSelect = document.getElementById('doctorSelect');
        var currentPatientDoctor = '<?= $patient['assigned_doctor_id'] ?>';
        
        // Clear current options
        doctorSelect.innerHTML = '<option value="">None</option>';
        
        if (branchId) {
            // Show loading state
            doctorSelect.innerHTML = '<option value="">Loading doctors...</option>';
            
            // Fetch doctors for this branch
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
        
        // Clear container
        container.innerHTML = '';
        
        if (allergies.length === 0) {
            container.innerHTML = '<span class="text-gray-400 text-sm" id="noAllergyMessage">No allergies added</span>';
            hidden.value = '';
            return;
        }
        
        // Add each allergy as a tag
        allergies.forEach(function(allergy) {
            var tag = document.createElement('span');
            tag.className = 'allergy-tag';
            tag.dataset.allergy = allergy;
            tag.innerHTML = allergy + ' <span class="remove-allergy" onclick="removeAllergy(this)" title="Remove allergy">✕</span>';
            container.appendChild(tag);
        });
        
        // Update hidden input
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
        var tag = element.closest('.allergy-tag');
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
        
        // Update allergies hidden input before submit
        document.getElementById('allergiesHidden').value = allergies.join(', ');
        
        return true;
    });

    console.log('%c🏥 Braick Dispensary - Edit Patient', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 ID: <?= htmlspecialchars($patient['patient_id']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💊 Allergies: <?= count($current_allergies) ?> items (pick + custom)', 'font-size:13px; color:#7B2FBE;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c👨‍⚕️ Doctors filtered by branch', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#34D399;');
    console.log('%c📊 Tables: patients, branches, users', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>