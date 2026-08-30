<?php
// ================================================================
// FILE: frontend/pages/admin/add_inventory.php
// ADMIN - ADD INVENTORY ITEM (MEDICINE)
// BRAICK DISPENSARY - USING EXISTING DB TABLES
// WITH SHARED HEADER & SIDEBAR
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
// CHECK IF USER IS ADMIN
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
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// BRANCH SELECTION FOR SIDEBAR
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';

// ================================================================
// GET STATISTICS FOR SIDEBAR BADGES
// ================================================================
$total_employees = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_doctors = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_branches = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$pending_lab_tests = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
    $pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_lab_tests = 0;
}

$pending_prescriptions = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

// ================================================================
// GET BRANCHES FOR SELECTOR
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branches[] = $row;
}

// ================================================================
// GET UNIQUE CATEGORIES FROM EXISTING MEDICATIONS
// ================================================================
$existing_categories = [];
try {
    $stmt = $db->query("SELECT DISTINCT category FROM medications_inventory WHERE category IS NOT NULL AND category != '' ORDER BY category");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_categories[] = $row['category'];
    }
} catch (Exception $e) {
    $existing_categories = [];
}

// ================================================================
// PRE-DEFINED CATEGORIES FOR DROPDOWN
// ================================================================
$predefined_categories = [
    'Antibiotics',
    'Painkillers',
    'Antipyretics',
    'Antihistamines',
    'Antacids',
    'Antivirals',
    'Antifungals',
    'Antimalarials',
    'Vitamins',
    'Supplements',
    'Respiratory',
    'Cardiovascular',
    'Diabetes',
    'Hypertension',
    'Dermatological',
    'Eye Drops',
    'Ear Drops',
    'Injectables',
    'IV Fluids',
    'Other'
];

// Merge existing categories with predefined ones
$all_categories = array_unique(array_merge($predefined_categories, $existing_categories));
sort($all_categories);

// ================================================================
// PROCESS FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';
$form_data = [
    'medication_name' => '',
    'category' => '',
    'unit' => 'pcs',
    'quantity' => '',
    'reorder_level' => 10,
    'unit_cost' => '',
    'selling_price' => '',
    'supplier' => '',
    'expiry_date' => '',
    'batch_number' => '',
    'branch_id' => $selected_branch_id !== 'all' ? (int)$selected_branch_id : $user_branch_id,
    'status' => 'active'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_medicine') {
    $form_data['medication_name'] = trim($_POST['medication_name'] ?? '');
    $form_data['category'] = trim($_POST['category'] ?? '');
    if (empty($form_data['category']) && !empty($_POST['category_manual'])) {
        $form_data['category'] = trim($_POST['category_manual']);
    }
    $form_data['unit'] = trim($_POST['unit'] ?? 'pcs');
    $form_data['quantity'] = (int)($_POST['quantity'] ?? 0);
    $form_data['reorder_level'] = (int)($_POST['reorder_level'] ?? 10);
    $form_data['unit_cost'] = (float)($_POST['unit_cost'] ?? 0);
    $form_data['selling_price'] = (float)($_POST['selling_price'] ?? 0);
    $form_data['supplier'] = trim($_POST['supplier'] ?? '');
    $form_data['expiry_date'] = $_POST['expiry_date'] ?? '';
    $form_data['batch_number'] = trim($_POST['batch_number'] ?? '');
    $form_data['branch_id'] = (int)($_POST['branch_id'] ?? $user_branch_id);
    $form_data['status'] = $_POST['status'] ?? 'active';
    
    // Auto-generate batch number if empty
    if (empty($form_data['batch_number'])) {
        $form_data['batch_number'] = 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
    
    // Validation
    $errors = [];
    if (empty($form_data['medication_name'])) {
        $errors[] = 'Medicine name is required';
    }
    if ($form_data['quantity'] < 0) {
        $errors[] = 'Quantity cannot be negative';
    }
    if ($form_data['selling_price'] < 0) {
        $errors[] = 'Selling price cannot be negative';
    }
    if ($form_data['selling_price'] > 0 && $form_data['selling_price'] < 1) {
        $errors[] = 'Selling price must be at least TSh 1';
    }
    if ($form_data['branch_id'] <= 0) {
        $errors[] = 'Please select a branch';
    }
    if (!empty($form_data['expiry_date']) && strtotime($form_data['expiry_date']) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Expiry date cannot be in the past';
    }
    
    // Check for duplicate (same medication name + batch number + branch)
    if (empty($errors)) {
        $stmt = $db->prepare("
            SELECT id FROM medications_inventory 
            WHERE medication_name = ? AND batch_number = ? AND branch_id = ?
        ");
        $stmt->execute([$form_data['medication_name'], $form_data['batch_number'], $form_data['branch_id']]);
        if ($stmt->fetch()) {
            $errors[] = 'A medicine with this name and batch number already exists in this branch';
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO medications_inventory (
                    medication_name, category, unit, quantity, reorder_level,
                    unit_cost, selling_price, supplier, expiry_date, batch_number,
                    branch_id, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $form_data['medication_name'],
                $form_data['category'],
                $form_data['unit'],
                $form_data['quantity'],
                $form_data['reorder_level'],
                $form_data['unit_cost'],
                $form_data['selling_price'],
                $form_data['supplier'],
                $form_data['expiry_date'],
                $form_data['batch_number'],
                $form_data['branch_id'],
                $form_data['status']
            ]);
            
            $new_id = $db->lastInsertId();
            
            // Log activity
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                    VALUES (?, ?, 'medicine_added', ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $form_data['branch_id'],
                    "Added new medicine: " . $form_data['medication_name'] . " (Batch: " . $form_data['batch_number'] . ") - " . $form_data['quantity'] . " units"
                ]);
            } catch (Exception $e) {
                // Silent fail
            }
            
            $message = "✅ Medicine added successfully!<br>Batch: <strong>" . htmlspecialchars($form_data['batch_number']) . "</strong><br>Branch: <strong>" . htmlspecialchars($form_data['branch_id']) . "</strong>";
            $message_type = 'success';
            
            // Reset form data on success
            $form_data = [
                'medication_name' => '',
                'category' => '',
                'unit' => 'pcs',
                'quantity' => '',
                'reorder_level' => 10,
                'unit_cost' => '',
                'selling_price' => '',
                'supplier' => '',
                'expiry_date' => '',
                'batch_number' => '',
                'branch_id' => $selected_branch_id !== 'all' ? (int)$selected_branch_id : $user_branch_id,
                'status' => 'active'
            ];
            
            echo '<script>setTimeout(function(){ window.location.href = "pharmacy_inventory.php?branch=' . $form_data['branch_id'] . '&success=1"; }, 2000);</script>';
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
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

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once '../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
$selected_branch_id = $selected_branch_id ?? 'all';
$total_employees = $total_employees ?? 0;
$total_doctors = $total_doctors ?? 0;
$total_branches = $total_branches ?? 0;
$pending_lab_tests = $pending_lab_tests ?? 0;
$pending_prescriptions = $pending_prescriptions ?? 0;
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       ADDITIONAL FORM STYLES - BEAUTIFUL LIKE DASHBOARD
       ================================================================ */
    
    /* Form Card */
    .form-card {
        background: var(--bg-card);
        border-radius: 20px;
        padding: 28px 32px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        max-width: 900px;
        margin: 0 auto;
    }
    
    .form-card:hover {
        border-color: #7C3AED;
        box-shadow: 0 8px 30px rgba(124, 58, 237, 0.08);
    }
    
    /* Form Header */
    .form-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding-bottom: 20px;
        margin-bottom: 24px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .form-header-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        flex-shrink: 0;
        background: linear-gradient(135deg, #7C3AED, #9B4DCA);
        color: white;
        box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
    }
    
    .form-header h3 {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }
    
    .form-header p {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin: 0;
    }
    
    /* Form Labels */
    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 6px;
        display: block;
    }
    
    .form-label i {
        width: 20px;
        text-align: center;
        font-size: 0.85rem;
    }
    
    .form-label .required {
        color: #EF4444;
        margin-left: 2px;
    }
    
    /* Form Controls */
    .form-control {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--bg-card);
        color: var(--text-primary);
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    
    .form-control:focus {
        border-color: #7C3AED;
        box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.12);
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.5;
    }
    
    .form-control:disabled {
        background: var(--bg-body);
        color: var(--text-secondary);
        cursor: not-allowed;
    }
    
    /* Form Row with Icon */
    .form-row-icon {
        position: relative;
    }
    
    .form-row-icon .form-control {
        padding-left: 44px;
    }
    
    .form-row-icon .input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        font-size: 1rem;
        pointer-events: none;
        transition: color 0.3s ease;
    }
    
    .form-row-icon .form-control:focus + .input-icon,
    .form-row-icon .form-control:focus ~ .input-icon {
        color: #7C3AED;
    }
    
    /* Category Input Group */
    .category-input-group {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    
    .category-input-group .form-control {
        flex: 1;
    }
    
    .category-input-group .btn-category-toggle {
        background: #7C3AED;
        color: white;
        border: none;
        border-radius: 12px;
        padding: 8px 14px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
        height: 44px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .category-input-group .btn-category-toggle:hover {
        background: #6B2FA8;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
    }
    
    /* Batch Input Group */
    .batch-input-group {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    
    .batch-input-group .form-control {
        flex: 1;
    }
    
    .batch-input-group .btn-generate-batch {
        background: #7C3AED;
        color: white;
        border: none;
        border-radius: 12px;
        padding: 8px 14px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        height: 44px;
    }
    
    .batch-input-group .btn-generate-batch:hover {
        background: #6B2FA8;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
    }
    
    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 10px 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 44px;
        min-width: 120px;
    }
    
    .btn-purple {
        background: linear-gradient(135deg, #7C3AED, #9B4DCA);
        color: white;
        box-shadow: 0 4px 14px rgba(124, 58, 237, 0.3);
    }
    
    .btn-purple:hover {
        background: linear-gradient(135deg, #6B2FA8, #8B3DBA);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(124, 58, 237, 0.4);
    }
    
    .btn-purple:active {
        transform: translateY(0px);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-primary);
        border: 2px solid var(--border-color);
    }
    
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #7C3AED;
        color: #7C3AED;
        transform: translateY(-2px);
    }
    
    .btn-sm {
        padding: 6px 16px;
        font-size: 0.8rem;
        min-height: 36px;
        min-width: 90px;
    }
    
    /* Button Group */
    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 24px;
        margin-top: 24px;
        border-top: 2px solid var(--border-color);
    }
    
    /* Tips Cards */
    .tip-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    
    .tip-card:hover {
        border-color: #7C3AED;
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.06);
    }
    
    .tip-card .tip-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    
    .tip-card .tip-icon.purple { 
        background: #F3E8FF; 
        color: #7C3AED; 
    }
    .tip-card .tip-icon.green { 
        background: #E6F7EE; 
        color: #059669; 
    }
    .tip-card .tip-icon.blue { 
        background: #E8F0FE; 
        color: #0B5ED7; 
    }
    .tip-card .tip-icon.yellow { 
        background: #FEF3C7; 
        color: #F59E0B; 
    }
    
    .tip-card .tip-text h4 {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }
    
    .tip-card .tip-text p {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }
    
    /* Dark Mode Support */
    [data-theme="dark"] .tip-card .tip-icon.purple { 
        background: #2A1A3A; 
        color: #9B4DCA; 
    }
    [data-theme="dark"] .tip-card .tip-icon.green { 
        background: #1A3A2A; 
        color: #34D399; 
    }
    [data-theme="dark"] .tip-card .tip-icon.blue { 
        background: #1E3A5F; 
        color: #6EA8FE; 
    }
    [data-theme="dark"] .tip-card .tip-icon.yellow { 
        background: #3A2A1A; 
        color: #FBBF24; 
    }
    
    /* Message Box */
    .message-box {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
        animation: slideDown 0.4s ease;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .message-box.success {
        background: #D1FAE5;
        color: #065F46;
        border: 2px solid #6EE7B7;
    }
    
    .message-box.error {
        background: #FEE2E2;
        color: #991B1B;
        border: 2px solid #FCA5A5;
    }
    
    [data-theme="dark"] .message-box.success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }
    
    [data-theme="dark"] .message-box.error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }
    
    /* Responsive */
    @media (max-width: 640px) {
        .form-card {
            padding: 18px 16px;
        }
        .form-header {
            flex-direction: column;
            text-align: center;
        }
        .form-header-icon {
            width: 48px;
            height: 48px;
            font-size: 1.2rem;
        }
        .btn {
            padding: 8px 16px;
            font-size: 0.8rem;
            min-height: 38px;
            min-width: 100%;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .category-input-group {
            flex-direction: column;
        }
        .category-input-group .btn-category-toggle {
            width: 100%;
            justify-content: center;
        }
        .batch-input-group {
            flex-direction: column;
        }
        .batch-input-group .btn-generate-batch {
            width: 100%;
            justify-content: center;
        }
        .tip-card {
            padding: 12px 16px;
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
            <input type="text" id="searchInput" placeholder="Search patients, doctors, medicines...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <!-- Dark Mode Toggle -->
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
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
                <i class="fas fa-plus-circle mr-2" style="color: #7C3AED;"></i> Add Medicine
            </h1>
            <p class="page-subtitle">
                Add new medicine to inventory
                <span class="ml-2 inline-flex bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs border border-purple-200">
                    <i class="fas fa-prescription-bottle mr-1"></i> Inventory Management
                </span>
            </p>
        </div>
        <div>
            <a href="pharmacy_inventory.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FORM - BEAUTIFUL LIKE DASHBOARD -->
    <!-- ================================================================ -->
    <div class="form-card">
        <!-- Form Header -->
        <div class="form-header">
            <div class="form-header-icon">
                <i class="fas fa-prescription-bottle"></i>
            </div>
            <div>
                <h3>Medicine Information</h3>
                <p>Enter the details to add a new medicine to the inventory</p>
            </div>
        </div>
        
        <form method="POST" action="" id="addMedicineForm">
            <input type="hidden" name="action" value="add_medicine">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Medicine Name - Full Width -->
                <div class="md:col-span-2">
                    <label class="form-label">
                        <i class="fas fa-capsules text-purple-600"></i> Medicine Name
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="medication_name" class="form-control" 
                               placeholder="e.g. Paracetamol 500mg, Amoxicillin 250mg" 
                               value="<?= htmlspecialchars($form_data['medication_name']) ?>" required>
                        <span class="input-icon"><i class="fas fa-capsules"></i></span>
                    </div>
                </div>
                
                <!-- Category -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-tags text-purple-600"></i> Category
                    </label>
                    <div class="category-input-group">
                        <select name="category" id="categorySelect" class="form-control">
                            <option value="">Select or type manually</option>
                            <?php foreach ($all_categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= $form_data['category'] === $cat ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__other__">+ Other (Type manually)</option>
                        </select>
                        <input type="text" name="category_manual" id="categoryManual" class="form-control" 
                               placeholder="Enter custom category..." style="display:none;" 
                               value="<?= htmlspecialchars($form_data['category']) ?>">
                        <button type="button" class="btn-category-toggle" onclick="toggleCategoryInput()">
                            <i class="fas fa-edit"></i> Manual
                        </button>
                    </div>
                    <p class="help-text mt-1">Select an existing category or type a new one</p>
                </div>
                
                <!-- Unit -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-ruler text-purple-600"></i> Unit
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <select name="unit" class="form-control" required>
                            <option value="pcs" <?= $form_data['unit'] === 'pcs' ? 'selected' : '' ?>>Pieces (pcs)</option>
                            <option value="tablets" <?= $form_data['unit'] === 'tablets' ? 'selected' : '' ?>>Tablets</option>
                            <option value="capsules" <?= $form_data['unit'] === 'capsules' ? 'selected' : '' ?>>Capsules</option>
                            <option value="ml" <?= $form_data['unit'] === 'ml' ? 'selected' : '' ?>>Milliliters (ml)</option>
                            <option value="mg" <?= $form_data['unit'] === 'mg' ? 'selected' : '' ?>>Milligrams (mg)</option>
                            <option value="g" <?= $form_data['unit'] === 'g' ? 'selected' : '' ?>>Grams (g)</option>
                            <option value="bottle" <?= $form_data['unit'] === 'bottle' ? 'selected' : '' ?>>Bottle</option>
                            <option value="box" <?= $form_data['unit'] === 'box' ? 'selected' : '' ?>>Box</option>
                            <option value="strip" <?= $form_data['unit'] === 'strip' ? 'selected' : '' ?>>Strip</option>
                            <option value="vial" <?= $form_data['unit'] === 'vial' ? 'selected' : '' ?>>Vial</option>
                            <option value="sachet" <?= $form_data['unit'] === 'sachet' ? 'selected' : '' ?>>Sachet</option>
                        </select>
                        <span class="input-icon"><i class="fas fa-ruler"></i></span>
                    </div>
                </div>
                
                <!-- Quantity -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-boxes text-purple-600"></i> Current Quantity
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="number" name="quantity" class="form-control" 
                               placeholder="0" min="0" 
                               value="<?= htmlspecialchars($form_data['quantity']) ?>" required>
                        <span class="input-icon"><i class="fas fa-boxes"></i></span>
                    </div>
                </div>
                
                <!-- Reorder Level -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-exclamation-triangle text-yellow-600"></i> Reorder Level
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="number" name="reorder_level" class="form-control" 
                               placeholder="10" min="0" 
                               value="<?= htmlspecialchars($form_data['reorder_level']) ?>" required>
                        <span class="input-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    </div>
                    <p class="help-text mt-1">Alert when stock reaches this level</p>
                </div>
                
                <!-- Buying Price -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-shopping-cart text-blue-600"></i> Buying Price (TSh)
                    </label>
                    <div class="form-row-icon">
                        <input type="number" name="unit_cost" class="form-control" 
                               placeholder="0" step="1" min="0" 
                               value="<?= htmlspecialchars($form_data['unit_cost']) ?>">
                        <span class="input-icon"><i class="fas fa-shopping-cart"></i></span>
                    </div>
                </div>
                
                <!-- Selling Price -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-money-bill-wave text-green-600"></i> Selling Price (TSh)
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="number" name="selling_price" class="form-control" 
                               placeholder="1" step="1" min="1" 
                               value="<?= htmlspecialchars($form_data['selling_price'] ?: '1') ?>" required>
                        <span class="input-icon"><i class="fas fa-money-bill-wave"></i></span>
                    </div>
                    <p class="help-text mt-1">Minimum TSh 1 (not 100)</p>
                </div>
                
                <!-- Supplier -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-truck text-purple-600"></i> Supplier
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="supplier" class="form-control" 
                               placeholder="e.g. AVANA MEDICS" 
                               value="<?= htmlspecialchars($form_data['supplier']) ?>">
                        <span class="input-icon"><i class="fas fa-truck"></i></span>
                    </div>
                </div>
                
                <!-- Expiry Date -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-calendar-alt text-red-600"></i> Expiry Date
                    </label>
                    <div class="form-row-icon">
                        <input type="date" name="expiry_date" class="form-control" 
                               value="<?= htmlspecialchars($form_data['expiry_date']) ?>">
                        <span class="input-icon"><i class="fas fa-calendar-alt"></i></span>
                    </div>
                    <p class="help-text mt-1">System will show days remaining until expiry</p>
                </div>
                
                <!-- Branch -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-store text-purple-600"></i> Branch
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <select name="branch_id" class="form-control" required>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>" <?= $form_data['branch_id'] == $branch['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($branch['name']) ?>
                                    <?= !empty($branch['location']) ? '- ' . htmlspecialchars($branch['location']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="input-icon"><i class="fas fa-store"></i></span>
                    </div>
                </div>
                
                <!-- Batch Number - Full Width -->
                <div class="md:col-span-2">
                    <label class="form-label">
                        <i class="fas fa-barcode text-purple-600"></i> Batch Number
                    </label>
                    <div class="batch-input-group">
                        <input type="text" name="batch_number" id="batchNumberInput" class="form-control" 
                               placeholder="BATCH-YYYYMMDD-XXXX" 
                               value="<?= htmlspecialchars($form_data['batch_number'] ?: 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6))) ?>">
                        <button type="button" class="btn-generate-batch" onclick="generateBatchNumber()">
                            <i class="fas fa-sync-alt"></i> Generate
                        </button>
                    </div>
                    <p class="help-text mt-1">
                        <i class="fas fa-info-circle"></i> Auto-generated. Click "Generate" for a new batch number.
                    </p>
                </div>
                
                <!-- Status -->
                <div class="md:col-span-2">
                    <label class="form-label">
                        <i class="fas fa-circle text-purple-600"></i> Status
                    </label>
                    <div class="flex items-center gap-4 pt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="status" value="active" <?= $form_data['status'] === 'active' ? 'checked' : '' ?>>
                            <span>Active</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="status" value="inactive" <?= $form_data['status'] === 'inactive' ? 'checked' : '' ?>>
                            <span>Inactive</span>
                        </label>
                    </div>
                    <p class="help-text mt-1">Active items will appear in inventory</p>
                </div>
                
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-purple">
                    <i class="fas fa-save"></i> Add Medicine
                </button>
                <a href="pharmacy_inventory.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK TIPS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-5" style="max-width:900px;margin:20px auto 0;">
        <div class="tip-card">
            <div class="tip-icon purple">
                <i class="fas fa-tag"></i>
            </div>
            <div class="tip-text">
                <h4>Tip #1</h4>
                <p>Batch number auto-generates</p>
            </div>
        </div>
        <div class="tip-card">
            <div class="tip-icon green">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="tip-text">
                <h4>Tip #2</h4>
                <p>Minimum selling price TSh 1</p>
            </div>
        </div>
        <div class="tip-card">
            <div class="tip-icon yellow">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="tip-text">
                <h4>Tip #3</h4>
                <p>Set reorder level for alerts</p>
            </div>
        </div>
        <div class="tip-card">
            <div class="tip-icon blue">
                <i class="fas fa-store"></i>
            </div>
            <div class="tip-text">
                <h4>Tip #4</h4>
                <p>Select correct branch</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Add Medicine
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
    // TOGGLE CATEGORY INPUT
    // ================================================================
    function toggleCategoryInput() {
        var select = document.getElementById('categorySelect');
        var manual = document.getElementById('categoryManual');
        var btn = document.querySelector('.btn-category-toggle');
        
        if (manual.style.display === 'none' || manual.style.display === '') {
            manual.style.display = 'block';
            select.style.display = 'none';
            btn.innerHTML = '<i class="fas fa-list"></i> Select';
            manual.focus();
        } else {
            manual.style.display = 'none';
            select.style.display = 'block';
            btn.innerHTML = '<i class="fas fa-edit"></i> Manual';
            if (manual.value) {
                select.value = manual.value;
                var found = false;
                for (var i = 0; i < select.options.length; i++) {
                    if (select.options[i].value === manual.value) {
                        found = true;
                        break;
                    }
                }
                if (!found) {
                    var opt = document.createElement('option');
                    opt.value = manual.value;
                    opt.text = manual.value;
                    select.add(opt, select.options[select.options.length - 1]);
                    select.value = manual.value;
                }
            }
        }
    }

    // ================================================================
    // CATEGORY SELECT CHANGE
    // ================================================================
    document.getElementById('categorySelect')?.addEventListener('change', function() {
        if (this.value === '__other__') {
            document.getElementById('categoryManual').style.display = 'block';
            document.getElementById('categoryManual').focus();
            document.querySelector('.btn-category-toggle').innerHTML = '<i class="fas fa-list"></i> Select';
        }
    });

    // ================================================================
    // GENERATE BATCH NUMBER
    // ================================================================
    function generateBatchNumber() {
        var now = new Date();
        var dateStr = now.getFullYear() + 
                      String(now.getMonth() + 1).padStart(2, '0') + 
                      String(now.getDate()).padStart(2, '0');
        var random = Math.random().toString(36).substring(2, 8).toUpperCase();
        var batch = 'BATCH-' + dateStr + '-' + random;
        document.getElementById('batchNumberInput').value = batch;
    }

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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        document.getElementById('currentDateTime').textContent = 
            now.toLocaleDateString('en-US', { 
                weekday: 'short', 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            }) + 
            ' • ' + 
            now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit', 
                hour12: true 
            });
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
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    document.getElementById('addMedicineForm')?.addEventListener('submit', function(e) {
        var name = document.querySelector('input[name="medication_name"]');
        var quantity = document.querySelector('input[name="quantity"]');
        var sellingPrice = document.querySelector('input[name="selling_price"]');
        var reorderLevel = document.querySelector('input[name="reorder_level"]');
        var errors = [];

        if (!name.value.trim()) {
            errors.push('Medicine name is required');
            name.style.borderColor = '#DC2626';
        }

        if (parseInt(quantity.value) < 0) {
            errors.push('Quantity cannot be negative');
            quantity.style.borderColor = '#DC2626';
        }

        if (parseFloat(sellingPrice.value) < 1 && sellingPrice.value !== '') {
            errors.push('Selling price must be at least TSh 1');
            sellingPrice.style.borderColor = '#DC2626';
        }

        if (parseFloat(sellingPrice.value) < 0) {
            errors.push('Selling price cannot be negative');
            sellingPrice.style.borderColor = '#DC2626';
        }

        if (parseInt(reorderLevel.value) < 0) {
            errors.push('Reorder level cannot be negative');
            reorderLevel.style.borderColor = '#DC2626';
        }

        if (errors.length > 0) {
            e.preventDefault();
            alert('⚠️ Please fix the following errors:\n\n' + errors.join('\n'));
            return false;
        }

        return true;
    });

    console.log('%c💊 Braick - Add Medicine (ADMIN)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Table: medications_inventory', 'font-size:13px; color:#7C3AED;');
    console.log('%c✅ Price minimum: TSh 1', 'font-size:13px; color:#059669;');
    console.log('%c✅ Batch auto-generate enabled', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔒 Login protection: Active', 'font-size:13px; color:#059669;');
</script>

</body>
</html>