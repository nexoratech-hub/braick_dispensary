<?php
// ================================================================
// FILE: frontend/pages/admin/settings.php
// SUPER ADMIN - SYSTEM SETTINGS
// BRAICK DISPENSARY - USING EXISTING DATABASE
// WITH SHARED HEADER & SIDEBAR
// FOCUSED ON GENERAL SETTINGS (NO SERVICES)
// FIXED: Edit branch buttons now work properly
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// ================================================================
// ROLE CHECK - ONLY ADMIN CAN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
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
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$active_tab = $_GET['tab'] ?? 'general';
$edit_branch_id = isset($_GET['edit_branch']) ? (int)$_GET['edit_branch'] : 0;

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$total_employees = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
    $total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $total_employees = 0;
}

$total_doctors = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
    $total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $total_doctors = 0;
}

$total_branches = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
    $total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $total_branches = 0;
}

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
try {
    $stmt = $db->query("SELECT id, name, location, phone, email, status FROM branches WHERE status = 'active' ORDER BY name");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $branches[] = $row;
    }
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// GET ALL BRANCHES (INCLUDING INACTIVE FOR EDIT)
// ================================================================
$all_branches = [];
try {
    $stmt = $db->query("SELECT id, name, location, phone, email, status FROM branches ORDER BY name");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $all_branches[] = $row;
    }
} catch (Exception $e) {
    $all_branches = [];
}

// ================================================================
// HANDLE SETTINGS UPDATE
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // UPDATE GENERAL SETTINGS
    // ================================================================
    if ($action === 'update_general') {
        $site_name = trim($_POST['site_name'] ?? '');
        $site_phone = trim($_POST['site_phone'] ?? '');
        $site_email = trim($_POST['site_email'] ?? '');
        $site_address = trim($_POST['site_address'] ?? '');
        $currency = trim($_POST['currency'] ?? 'TSh');
        $timezone = trim($_POST['timezone'] ?? 'Africa/Dar_es_Salaam');
        
        try {
            $settings_data = [
                'site_name' => $site_name,
                'site_phone' => $site_phone,
                'site_email' => $site_email,
                'site_address' => $site_address,
                'currency' => $currency,
                'timezone' => $timezone
            ];
            
            foreach ($settings_data as $key => $value) {
                $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, category, updated_at) 
                                       VALUES (?, ?, 'general', NOW()) 
                                       ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
                $stmt->execute([$key, $value, $value]);
            }
            
            $message = "✅ General settings updated successfully!";
            $message_type = 'success';
            
            // Refresh settings
            $settings = [];
            $stmt = $db->query("SELECT setting_key, setting_value, category FROM system_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // UPDATE FINANCIAL SETTINGS
    // ================================================================
    if ($action === 'update_financial') {
        $currency_symbol = trim($_POST['currency_symbol'] ?? 'TSh');
        $tax_percent = (float)($_POST['tax_percent'] ?? 0);
        $max_discount_percent = (float)($_POST['max_discount_percent'] ?? 20);
        $registration_fee = (float)($_POST['registration_fee'] ?? 0);
        $consultation_fee = (float)($_POST['consultation_fee'] ?? 0);
        
        try {
            $settings_data = [
                'currency' => $currency_symbol,
                'tax_percent' => $tax_percent,
                'max_discount_percent' => $max_discount_percent,
                'registration_fee' => $registration_fee,
                'consultation_fee' => $consultation_fee
            ];
            
            foreach ($settings_data as $key => $value) {
                $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, category, updated_at) 
                                       VALUES (?, ?, 'financial', NOW()) 
                                       ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
                $stmt->execute([$key, $value, $value]);
            }
            
            $message = "✅ Financial settings updated successfully!";
            $message_type = 'success';
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // UPDATE BUSINESS HOURS
    // ================================================================
    if ($action === 'update_hours') {
        $business_hours_start = trim($_POST['business_hours_start'] ?? '08:00');
        $business_hours_end = trim($_POST['business_hours_end'] ?? '18:00');
        $weekend_days = isset($_POST['weekend_days']) ? implode(',', $_POST['weekend_days']) : '';
        
        try {
            $settings_data = [
                'business_hours_start' => $business_hours_start,
                'business_hours_end' => $business_hours_end,
                'weekend_days' => $weekend_days
            ];
            
            foreach ($settings_data as $key => $value) {
                $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, category, updated_at) 
                                       VALUES (?, ?, 'hours', NOW()) 
                                       ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
                $stmt->execute([$key, $value, $value]);
            }
            
            $message = "✅ Business hours updated successfully!";
            $message_type = 'success';
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // UPDATE BRANCH - FIXED
    // ================================================================
    if ($action === 'update_branch') {
        $branch_id = (int)($_POST['branch_id'] ?? 0);
        $branch_name = trim($_POST['branch_name'] ?? '');
        $branch_location = trim($_POST['branch_location'] ?? '');
        $branch_phone = trim($_POST['branch_phone'] ?? '');
        $branch_email = trim($_POST['branch_email'] ?? '');
        $branch_status = $_POST['branch_status'] ?? 'active';
        
        if ($branch_id > 0 && !empty($branch_name)) {
            try {
                $stmt = $db->prepare("UPDATE branches SET name = ?, location = ?, phone = ?, email = ?, status = ? WHERE id = ?");
                $stmt->execute([$branch_name, $branch_location, $branch_phone, $branch_email, $branch_status, $branch_id]);
                
                $message = "✅ Branch updated successfully!";
                $message_type = 'success';
                
                // Refresh branches list
                $all_branches = [];
                $stmt = $db->query("SELECT id, name, location, phone, email, status FROM branches ORDER BY name");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $all_branches[] = $row;
                }
                
                // Clear edit mode after successful update
                $edit_branch_id = 0;
                $edit_branch = null;
                
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "❌ Branch name is required!";
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // UPDATE SYSTEM
    // ================================================================
    if ($action === 'update_system') {
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        $debug_mode = isset($_POST['debug_mode']) ? 1 : 0;
        $log_activities = isset($_POST['log_activities']) ? 1 : 0;
        
        try {
            $settings_data = [
                'maintenance_mode' => $maintenance_mode,
                'debug_mode' => $debug_mode,
                'log_activities' => $log_activities
            ];
            
            foreach ($settings_data as $key => $value) {
                $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, category, updated_at) 
                                       VALUES (?, ?, 'system', NOW()) 
                                       ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
                $stmt->execute([$key, $value, $value]);
            }
            
            $message = "✅ System settings updated successfully!";
            $message_type = 'success';
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // UPDATE SECURITY
    // ================================================================
    if ($action === 'update_security') {
        $password_policy = $_POST['password_policy'] ?? 'medium';
        $session_timeout = (int)($_POST['session_timeout'] ?? 30);
        $max_login_attempts = (int)($_POST['max_login_attempts'] ?? 5);
        $two_factor_auth = isset($_POST['two_factor_auth']) ? 1 : 0;
        
        try {
            $settings_data = [
                'password_policy' => $password_policy,
                'session_timeout' => $session_timeout,
                'max_login_attempts' => $max_login_attempts,
                'two_factor_auth' => $two_factor_auth
            ];
            
            foreach ($settings_data as $key => $value) {
                $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, category, updated_at) 
                                       VALUES (?, ?, 'security', NOW()) 
                                       ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
                $stmt->execute([$key, $value, $value]);
            }
            
            $message = "✅ Security settings updated successfully!";
            $message_type = 'success';
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ================================================================
// GET CURRENT SETTINGS
// ================================================================
$settings = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value, category FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $settings = [];
}

// Default values
$defaults = [
    'site_name' => 'Braick Dispensary',
    'site_phone' => '+255 700 000 000',
    'site_email' => 'info@braick.com',
    'site_address' => 'Dodoma City, Tanzania',
    'currency' => 'TSh',
    'timezone' => 'Africa/Dar_es_Salaam',
    'tax_percent' => 0,
    'max_discount_percent' => 20,
    'registration_fee' => 5000,
    'consultation_fee' => 10000,
    'business_hours_start' => '08:00',
    'business_hours_end' => '18:00',
    'weekend_days' => 'Saturday,Sunday',
    'maintenance_mode' => 0,
    'debug_mode' => 0,
    'log_activities' => 1,
    'password_policy' => 'medium',
    'session_timeout' => 30,
    'max_login_attempts' => 5,
    'two_factor_auth' => 0
];

// Merge settings with defaults
foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// ================================================================
// GET BRANCH FOR EDIT - FIXED
// ================================================================
$edit_branch = null;
if ($edit_branch_id > 0) {
    $stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$edit_branch_id]);
    $edit_branch = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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
       SETTINGS PAGE STYLES
       ================================================================ */
    
    .settings-sidebar {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 16px;
        border: 2px solid var(--border-color);
        position: sticky;
        top: 80px;
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
    }
    
    .settings-sidebar:hover {
        border-color: var(--primary-light);
        box-shadow: var(--shadow-md);
    }
    
    .settings-sidebar .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 10px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 0.85rem;
        font-weight: 500;
        border: 2px solid transparent;
        margin-bottom: 4px;
    }
    
    .settings-sidebar .nav-link:hover {
        background: var(--bg-body);
        color: var(--primary);
        border-color: var(--primary-bg);
    }
    
    .settings-sidebar .nav-link.active {
        background: var(--primary-bg);
        color: var(--primary);
        border-color: var(--primary);
        font-weight: 600;
    }
    
    [data-theme="dark"] .settings-sidebar .nav-link.active {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #6EA8FE;
    }
    
    .settings-sidebar .nav-link i {
        width: 20px;
        text-align: center;
        font-size: 0.95rem;
    }
    
    .settings-sidebar .nav-link .badge {
        margin-left: auto;
        background: var(--primary);
        color: white;
        font-size: 0.6rem;
        padding: 2px 8px;
        border-radius: 12px;
    }
    
    .settings-content {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px;
        border: 2px solid var(--border-color);
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
    }
    
    .settings-content:hover {
        border-color: var(--primary-light);
        box-shadow: var(--shadow-md);
    }
    
    .settings-content .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding-bottom: 16px;
        margin-bottom: 20px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .settings-content .section-header h2 {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .settings-content .section-header h2 i {
        color: var(--primary);
    }
    
    .form-label {
        font-size: 0.85rem;
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
        padding: 10px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--bg-card);
        color: var(--text-primary);
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.5;
    }
    
    .form-row {
        margin-bottom: 16px;
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
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-primary {
        background: var(--primary);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(11, 94, 215, 0.4);
    }
    
    .btn-secondary {
        background: var(--bg-body);
        color: var(--text-primary);
        border: 2px solid var(--border-color);
    }
    .btn-secondary:hover {
        background: var(--bg-card);
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .btn-success {
        background: #059669;
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    .btn-success:hover {
        background: #047857;
        transform: translateY(-2px);
    }
    
    .btn-danger {
        background: #EF4444;
        color: white;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
    .btn-danger:hover {
        background: #DC2626;
        transform: translateY(-2px);
    }
    
    .btn-sm {
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    
    .branch-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 18px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 12px;
    }
    
    .branch-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.06);
    }
    
    .branch-card .branch-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .branch-card .branch-name {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .branch-card .branch-location {
        font-size: 0.8rem;
        color: var(--text-secondary);
        margin-top: 2px;
    }
    
    .badge-status {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .badge-status.active { background: #E6F7EE; color: #059669; }
    .badge-status.inactive { background: #FEE2E2; color: #EF4444; }
    
    [data-theme="dark"] .badge-status.active { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .badge-status.inactive { background: #3A1A1A; color: #F87171; }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 3rem;
        color: var(--border-color);
        margin-bottom: 12px;
    }
    
    .edit-branch-form {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid var(--border-color);
    }
    
    .edit-branch-form .form-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .edit-branch-form .form-title i {
        color: var(--primary);
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
        transition: all 0.4s ease;
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    }
    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #EF4444; }
    .toast-custom.info { background: var(--primary); }
    .toast-custom.warning { background: #D97706; }
    
    .text-danger { color: #EF4444; }
    
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 28px 32px;
        min-height: calc(100vh - 68px);
        background: var(--bg-body);
        transition: background 0.3s ease;
    }
    
    @media (max-width: 1024px) {
        .main-content { margin-left: 0; padding: 16px; }
    }
    
    @media (max-width: 768px) {
        .settings-sidebar {
            position: relative;
            top: 0;
            margin-bottom: 16px;
        }
        .settings-content {
            padding: 16px;
        }
        .branch-card .branch-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
    
    @media (max-width: 480px) {
        .main-content { padding: 10px; }
        .settings-content { padding: 12px; }
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
        .search-wrapper, .footer, #sidebarToggle { display: none !important; }
        .main-content { margin: 0; padding: 20px; }
        .settings-content, .settings-sidebar { border: 1px solid #ddd !important; box-shadow: none !important; }
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
            <input type="text" id="searchInput" placeholder="Search...">
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
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5" style="background:var(--primary-gradient);border-radius:16px;padding:28px 36px;margin-bottom:28px;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:16px;box-shadow:0 8px 32px rgba(11,94,215,0.25);position:relative;overflow:hidden;">
        <div style="position:relative;z-index:1;">
            <h1 class="page-title" style="font-size:1.8rem;font-weight:700;color:white;display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0;">
                <i class="fas fa-cog" style="opacity:0.9;"></i> System Settings
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;padding:4px 14px;border-radius:20px;font-size:0.65rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;backdrop-filter:blur(4px);">ADMIN</span>
            </h1>
            <p class="page-subtitle" style="color:rgba(255,255,255,0.85);font-size:0.95rem;display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0;">
                Manage system configurations
                <span class="header-badge" style="background:rgba(255,255,255,0.12);color:white;padding:4px 14px;border-radius:20px;font-size:0.7rem;font-weight:500;backdrop-filter:blur(4px);display:inline-flex;align-items:center;gap:6px;border:1px solid rgba(255,255,255,0.1);">
                    <i class="fas fa-store-alt"></i> <?= $selected_branch_id === 'all' ? 'All Branches' : htmlspecialchars($user_branch_name) ?>
                </span>
            </p>
        </div>
        <div style="position:relative;z-index:1;display:flex;gap:8px;flex-wrap:wrap;">
            <a href="dashboard.php" class="btn-outline-light" style="background:rgba(255,255,255,0.12);color:white;border:1px solid rgba(255,255,255,0.2);padding:8px 18px;border-radius:10px;font-weight:500;font-size:0.82rem;transition:all 0.3s;text-decoration:none;display:inline-flex;align-items:center;gap:8px;backdrop-filter:blur(4px);">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>" style="position:relative;z-index:1;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- SETTINGS LAYOUT - SIDEBAR + CONTENT -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">
        
        <!-- Settings Sidebar -->
        <div class="lg:col-span-1">
            <div class="settings-sidebar">
                <nav>
                    <a href="?tab=general&branch=<?= $selected_branch_id ?>" class="nav-link <?= $active_tab === 'general' ? 'active' : '' ?>">
                        <i class="fas fa-cog"></i>
                        <span>General</span>
                    </a>
                    <a href="?tab=financial&branch=<?= $selected_branch_id ?>" class="nav-link <?= $active_tab === 'financial' ? 'active' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Financial</span>
                    </a>
                    <a href="?tab=hours&branch=<?= $selected_branch_id ?>" class="nav-link <?= $active_tab === 'hours' ? 'active' : '' ?>">
                        <i class="fas fa-clock"></i>
                        <span>Business Hours</span>
                    </a>
                    <a href="?tab=branches&branch=<?= $selected_branch_id ?>" class="nav-link <?= $active_tab === 'branches' ? 'active' : '' ?>">
                        <i class="fas fa-store-alt"></i>
                        <span>Branches</span>
                        <span class="badge"><?= count($all_branches) ?></span>
                    </a>
                    <a href="?tab=security&branch=<?= $selected_branch_id ?>" class="nav-link <?= $active_tab === 'security' ? 'active' : '' ?>">
                        <i class="fas fa-shield-alt"></i>
                        <span>Security</span>
                    </a>
                    <a href="?tab=system&branch=<?= $selected_branch_id ?>" class="nav-link <?= $active_tab === 'system' ? 'active' : '' ?>">
                        <i class="fas fa-server"></i>
                        <span>System</span>
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Settings Content -->
        <div class="lg:col-span-3">
            <div class="settings-content animate-fade-in-up">
                
                <!-- ================================================================ -->
                <!-- GENERAL TAB -->
                <!-- ================================================================ -->
                <?php if ($active_tab === 'general'): ?>
                
                <div class="section-header">
                    <h2>
                        <i class="fas fa-cog"></i>
                        General Settings
                    </h2>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_general">
                    
                    <div class="form-row">
                        <label class="form-label">Site Name <span class="required">*</span></label>
                        <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($settings['site_name'] ?? 'Braick Dispensary') ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="site_phone" class="form-control" value="<?= htmlspecialchars($settings['site_phone'] ?? '+255 700 000 000') ?>">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Email</label>
                        <input type="email" name="site_email" class="form-control" value="<?= htmlspecialchars($settings['site_email'] ?? 'info@braick.com') ?>">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Address</label>
                        <input type="text" name="site_address" class="form-control" value="<?= htmlspecialchars($settings['site_address'] ?? 'Dodoma City, Tanzania') ?>">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Currency</label>
                        <input type="text" name="currency" class="form-control" value="<?= htmlspecialchars($settings['currency'] ?? 'TSh') ?>">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" class="form-control">
                            <option value="Africa/Dar_es_Salaam" <?= ($settings['timezone'] ?? '') == 'Africa/Dar_es_Salaam' ? 'selected' : '' ?>>Africa/Dar_es_Salaam</option>
                            <option value="Africa/Nairobi" <?= ($settings['timezone'] ?? '') == 'Africa/Nairobi' ? 'selected' : '' ?>>Africa/Nairobi</option>
                            <option value="Africa/Maputo" <?= ($settings['timezone'] ?? '') == 'Africa/Maputo' ? 'selected' : '' ?>>Africa/Maputo</option>
                            <option value="Africa/Kampala" <?= ($settings['timezone'] ?? '') == 'Africa/Kampala' ? 'selected' : '' ?>>Africa/Kampala</option>
                            <option value="Africa/Lagos" <?= ($settings['timezone'] ?? '') == 'Africa/Lagos' ? 'selected' : '' ?>>Africa/Lagos</option>
                            <option value="UTC" <?= ($settings['timezone'] ?? '') == 'UTC' ? 'selected' : '' ?>>UTC</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
                
                <?php endif; ?>
                
                <!-- ================================================================ -->
                <!-- FINANCIAL TAB -->
                <!-- ================================================================ -->
                <?php if ($active_tab === 'financial'): ?>
                
                <div class="section-header">
                    <h2>
                        <i class="fas fa-money-bill-wave"></i>
                        Financial Settings
                    </h2>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_financial">
                    
                    <div class="form-row">
                        <label class="form-label">Currency Symbol</label>
                        <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($settings['currency'] ?? 'TSh') ?>">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Tax Percentage (%)</label>
                        <input type="number" name="tax_percent" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($settings['tax_percent'] ?? 0) ?>">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Max Discount Percentage (%)</label>
                        <input type="number" name="max_discount_percent" class="form-control" step="0.01" min="0" max="100" value="<?= htmlspecialchars($settings['max_discount_percent'] ?? 20) ?>">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Default Registration Fee</label>
                        <input type="number" name="registration_fee" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($settings['registration_fee'] ?? 5000) ?>">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Default Consultation Fee</label>
                        <input type="number" name="consultation_fee" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($settings['consultation_fee'] ?? 10000) ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
                
                <?php endif; ?>
                
                <!-- ================================================================ -->
                <!-- BUSINESS HOURS TAB -->
                <!-- ================================================================ -->
                <?php if ($active_tab === 'hours'): ?>
                
                <div class="section-header">
                    <h2>
                        <i class="fas fa-clock"></i>
                        Business Hours
                    </h2>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_hours">
                    
                    <div class="form-row">
                        <label class="form-label">Opening Time <span class="required">*</span></label>
                        <input type="time" name="business_hours_start" class="form-control" value="<?= htmlspecialchars($settings['business_hours_start'] ?? '08:00') ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Closing Time <span class="required">*</span></label>
                        <input type="time" name="business_hours_end" class="form-control" value="<?= htmlspecialchars($settings['business_hours_end'] ?? '18:00') ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Weekend Days</label>
                        <div class="flex flex-wrap gap-3">
                            <?php 
                            $weekend_days = explode(',', $settings['weekend_days'] ?? 'Saturday,Sunday');
                            $all_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            foreach ($all_days as $day):
                            ?>
                                <label class="flex items-center gap-2" style="cursor:pointer;font-size:0.85rem;">
                                    <input type="checkbox" name="weekend_days[]" value="<?= $day ?>" <?= in_array($day, $weekend_days) ? 'checked' : '' ?>>
                                    <?= $day ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
                
                <?php endif; ?>
                
                <!-- ================================================================ -->
                <!-- BRANCHES TAB - FIXED EDIT BUTTONS -->
                <!-- ================================================================ -->
                <?php if ($active_tab === 'branches'): ?>
                
                <div class="section-header">
                    <h2>
                        <i class="fas fa-store-alt"></i>
                        Branches
                        <span class="text-sm font-normal text-gray-400" style="color:var(--text-secondary);font-weight:400;">(<?= count($all_branches) ?> branches)</span>
                    </h2>
                </div>
                
                <?php if (count($all_branches) > 0): ?>
                    <?php foreach ($all_branches as $branch): ?>
                        <div class="branch-card">
                            <div class="branch-header">
                                <div>
                                    <div class="branch-name">
                                        <?= htmlspecialchars($branch['name']) ?>
                                        <span class="badge-status <?= ($branch['status'] ?? 'active') === 'active' ? 'active' : 'inactive' ?> ml-2">
                                            <?= ($branch['status'] ?? 'active') === 'active' ? '✅ Active' : '❌ Inactive' ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($branch['location'])): ?>
                                        <div class="branch-location">
                                            <i class="fas fa-map-marker-alt mr-1" style="color: var(--text-secondary);"></i>
                                            <?= htmlspecialchars($branch['location']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="branch-location">
                                        <?php if (!empty($branch['phone'])): ?>
                                            <i class="fas fa-phone mr-1" style="color: var(--text-secondary);"></i>
                                            <?= htmlspecialchars($branch['phone']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($branch['email'])): ?>
                                            <span class="mx-2">|</span>
                                            <i class="fas fa-envelope mr-1" style="color: var(--text-secondary);"></i>
                                            <?= htmlspecialchars($branch['email']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <a href="?tab=branches&branch=<?= $selected_branch_id ?>&edit_branch=<?= $branch['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-store-alt"></i>
                        <p>No branches found.</p>
                    </div>
                <?php endif; ?>
                
                <!-- Edit Branch Form - FIXED -->
                <?php if ($edit_branch): ?>
                <div class="edit-branch-form">
                    <div class="form-title">
                        <i class="fas fa-edit"></i> Edit Branch: <?= htmlspecialchars($edit_branch['name']) ?>
                        <a href="?tab=branches&branch=<?= $selected_branch_id ?>" class="text-sm text-gray-400 hover:text-red-500 ml-2" style="font-size:0.8rem;color:var(--text-secondary);text-decoration:none;">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_branch">
                        <input type="hidden" name="branch_id" value="<?= $edit_branch['id'] ?>">
                        
                        <div class="form-row">
                            <label class="form-label">Branch Name <span class="required">*</span></label>
                            <input type="text" name="branch_name" class="form-control" value="<?= htmlspecialchars($edit_branch['name']) ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Location</label>
                            <input type="text" name="branch_location" class="form-control" value="<?= htmlspecialchars($edit_branch['location'] ?? '') ?>">
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Phone</label>
                            <input type="text" name="branch_phone" class="form-control" value="<?= htmlspecialchars($edit_branch['phone'] ?? '') ?>">
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Email</label>
                            <input type="email" name="branch_email" class="form-control" value="<?= htmlspecialchars($edit_branch['email'] ?? '') ?>">
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Status</label>
                            <select name="branch_status" class="form-control">
                                <option value="active" <?= ($edit_branch['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($edit_branch['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Branch</button>
                            <a href="?tab=branches&branch=<?= $selected_branch_id ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
                <?php endif; ?>
                
                <!-- ================================================================ -->
                <!-- SECURITY TAB -->
                <!-- ================================================================ -->
                <?php if ($active_tab === 'security'): ?>
                
                <div class="section-header">
                    <h2>
                        <i class="fas fa-shield-alt"></i>
                        Security Settings
                    </h2>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_security">
                    
                    <div class="form-row">
                        <label class="form-label">Password Policy</label>
                        <select name="password_policy" class="form-control">
                            <option value="low" <?= ($settings['password_policy'] ?? 'medium') === 'low' ? 'selected' : '' ?>>Low (min 6 chars)</option>
                            <option value="medium" <?= ($settings['password_policy'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium (min 8 chars, mixed case)</option>
                            <option value="high" <?= ($settings['password_policy'] ?? 'medium') === 'high' ? 'selected' : '' ?>>High (min 10 chars, mixed case, numbers, special)</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Session Timeout (minutes)</label>
                        <input type="number" name="session_timeout" class="form-control" min="5" max="1440" value="<?= htmlspecialchars($settings['session_timeout'] ?? 30) ?>">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Max Login Attempts</label>
                        <input type="number" name="max_login_attempts" class="form-control" min="3" max="20" value="<?= htmlspecialchars($settings['max_login_attempts'] ?? 5) ?>">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="two_factor_auth" value="1" <?= ($settings['two_factor_auth'] ?? 0) ? 'checked' : '' ?>>
                            Enable Two-Factor Authentication
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
                
                <?php endif; ?>
                
                <!-- ================================================================ -->
                <!-- SYSTEM TAB -->
                <!-- ================================================================ -->
                <?php if ($active_tab === 'system'): ?>
                
                <div class="section-header">
                    <h2>
                        <i class="fas fa-server"></i>
                        System Settings
                    </h2>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_system">
                    
                    <div class="form-row">
                        <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="maintenance_mode" value="1" <?= ($settings['maintenance_mode'] ?? 0) ? 'checked' : '' ?>>
                            Maintenance Mode (site will be inaccessible to non-admin users)
                        </label>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="debug_mode" value="1" <?= ($settings['debug_mode'] ?? 0) ? 'checked' : '' ?>>
                            Debug Mode (show detailed error messages)
                        </label>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="log_activities" value="1" <?= ($settings['log_activities'] ?? 1) ? 'checked' : '' ?>>
                            Log User Activities
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
                
                <?php endif; ?>
                
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer mt-5" style="padding:14px 0;border-top:2px solid var(--border-color);margin-top:24px;text-align:center;font-size:0.7rem;color:var(--text-secondary);transition:border-color 0.3s ease,color 0.3s ease;">
        <p>
            <span class="footer-brand" style="color:var(--primary);font-weight:600;">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2" style="color:var(--text-secondary);opacity:0.3;">|</span>
            System Settings
            <span class="text-gray-300 mx-2" style="color:var(--text-secondary);opacity:0.3;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2" style="color:var(--text-secondary);opacity:0.3;">|</span>
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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c⚙️ Braick - System Settings', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Using existing database tables: system_settings, branches, users', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Edit branch buttons now work properly', 'font-size:13px; color:#34D399;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Tab: <?= ucfirst($active_tab) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🌙 Dark Mode: ' + (localStorage.getItem('darkMode') === 'true' ? 'ON' : 'OFF'), 'font-size:13px; color:#64748B;');
</script>

</body>
</html>