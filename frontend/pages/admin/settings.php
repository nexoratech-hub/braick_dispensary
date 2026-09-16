<?php
// ================================================================
// FILE: frontend/pages/admin/settings.php
// SUPER ADMIN - SYSTEM SETTINGS
// BRAICK DISPENSARY
// ✅ Uses SHARED admin_header.php & admin_sidebar.php
// ✅ Page-specific CSS only (no duplicates)
// ✅ Dark mode via header toggle
// ✅ FULL BLUE page header - NO WHITE BACKGROUND
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

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
// GET FILTER PARAMETERS
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$active_tab = $_GET['tab'] ?? 'general';
$edit_branch_id = isset($_GET['edit_branch']) ? (int)$_GET['edit_branch'] : 0;

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;
$pending_lab_tests = 0;
$pending_prescriptions = 0;

try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
    $total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {}

try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
    $total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {}

try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
    $total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {}

try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
    $pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {}

try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name, location, phone, email, status FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

$all_branches = [];
try {
    $stmt = $db->query("SELECT id, name, location, phone, email, status FROM branches ORDER BY name");
    $all_branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    
    // UPDATE GENERAL
    if ($action === 'update_general') {
        $settings_data = [
            'site_name' => trim($_POST['site_name'] ?? ''),
            'site_phone' => trim($_POST['site_phone'] ?? ''),
            'site_email' => trim($_POST['site_email'] ?? ''),
            'site_address' => trim($_POST['site_address'] ?? ''),
            'currency' => trim($_POST['currency'] ?? 'TSh'),
            'timezone' => trim($_POST['timezone'] ?? 'Africa/Dar_es_Salaam')
        ];
        
        try {
            foreach ($settings_data as $key => $value) {
                $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, category, updated_at) 
                                       VALUES (?, ?, 'general', NOW()) 
                                       ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
                $stmt->execute([$key, $value, $value]);
            }
            $message = "✅ General settings updated successfully!";
            $message_type = 'success';
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // UPDATE FINANCIAL
    if ($action === 'update_financial') {
        $settings_data = [
            'currency' => trim($_POST['currency_symbol'] ?? 'TSh'),
            'tax_percent' => (float)($_POST['tax_percent'] ?? 0),
            'max_discount_percent' => (float)($_POST['max_discount_percent'] ?? 20),
            'registration_fee' => (float)($_POST['registration_fee'] ?? 0),
            'consultation_fee' => (float)($_POST['consultation_fee'] ?? 0)
        ];
        
        try {
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
    
    // UPDATE HOURS
    if ($action === 'update_hours') {
        $settings_data = [
            'business_hours_start' => trim($_POST['business_hours_start'] ?? '08:00'),
            'business_hours_end' => trim($_POST['business_hours_end'] ?? '18:00'),
            'weekend_days' => isset($_POST['weekend_days']) ? implode(',', $_POST['weekend_days']) : ''
        ];
        
        try {
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
    
    // UPDATE BRANCH
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
                
                // Refresh branches
                $all_branches = [];
                $stmt = $db->query("SELECT id, name, location, phone, email, status FROM branches ORDER BY name");
                $all_branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
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
    
    // UPDATE SYSTEM
    if ($action === 'update_system') {
        $settings_data = [
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
            'debug_mode' => isset($_POST['debug_mode']) ? 1 : 0,
            'log_activities' => isset($_POST['log_activities']) ? 1 : 0
        ];
        
        try {
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
    
    // UPDATE SECURITY
    if ($action === 'update_security') {
        $settings_data = [
            'password_policy' => $_POST['password_policy'] ?? 'medium',
            'session_timeout' => (int)($_POST['session_timeout'] ?? 30),
            'max_login_attempts' => (int)($_POST['max_login_attempts'] ?? 5),
            'two_factor_auth' => isset($_POST['two_factor_auth']) ? 1 : 0
        ];
        
        try {
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
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $settings = [];
}

// Defaults
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

foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// GET EDIT BRANCH
$edit_branch = null;
if ($edit_branch_id > 0) {
    $stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$edit_branch_id]);
    $edit_branch = $stmt->fetch(PDO::FETCH_ASSOC);
}

// PROFILE PICTURE
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --set-primary: #0B5ED7;
        --set-primary-dark: #0A4CA8;
        --set-primary-light: #6EA8FE;
        --set-primary-bg: #DBEAFE;
        --set-success: #059669;
        --set-danger: #EF4444;
        --set-warning: #D97706;
        --set-bg-body: #F1F5F9;
        --set-bg-card: #FFFFFF;
        --set-text-primary: #1E293B;
        --set-text-secondary: #64748B;
        --set-border-color: #E2E8F0;
        --set-radius: 12px;
        --set-radius-lg: 16px;
        --set-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --set-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --set-shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
    }

    [data-theme="dark"] {
        --set-bg-body: #0F172A;
        --set-bg-card: #1E293B;
        --set-text-primary: #F1F5F9;
        --set-text-secondary: #94A3B8;
        --set-border-color: #334155;
        --set-primary-bg: #1E3A5F;
        --set-shadow-sm: 0 1px 2px rgba(0,0,0,0.2);
        --set-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
    }

    /* ================================================================
       DARK MODE
       ================================================================ */
    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
        color: #F1F5F9;
    }

    /* ================================================================
       PAGE HEADER - 100% BLUE, NO WHITE
       ================================================================ */
    .page-header-set {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%) !important;
        border-radius: 16px;
        padding: 28px 36px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.35);
        position: relative;
        overflow: hidden;
        border: none;
    }

    .page-header-set .page-title-set {
        color: #FFFFFF;
        font-size: 1.8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin: 0;
        position: relative;
        z-index: 1;
    }

    .page-header-set .page-title-set i {
        color: #FFFFFF;
        opacity: 0.9;
    }

    .page-header-set .role-badge-display-set {
        background: rgba(255,255,255,0.15);
        color: #FFFFFF;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.1);
    }

    .page-header-set .page-subtitle-set {
        color: rgba(255,255,255,0.9);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 6px;
        position: relative;
        z-index: 1;
    }

    .page-header-set .header-badge-set {
        background: rgba(255,255,255,0.10);
        color: #FFFFFF;
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

    .page-header-set .header-badge-set i {
        color: #FFFFFF;
    }

    .page-header-set .btn-outline-light-set {
        background: rgba(255,255,255,0.12);
        color: #FFFFFF;
        border: 1px solid rgba(255,255,255,0.25);
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
        transition: all 0.3s;
        position: relative;
        z-index: 1;
    }

    .page-header-set .btn-outline-light-set:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: #FFFFFF;
    }

    /* ================================================================
       SETTINGS SIDEBAR
       ================================================================ */
    .settings-sidebar-set {
        background: var(--set-bg-card);
        border-radius: 16px;
        padding: 16px;
        border: 2px solid var(--set-border-color);
        position: sticky;
        top: 80px;
        box-shadow: var(--set-shadow-sm);
        transition: all 0.3s ease;
    }

    .settings-sidebar-set:hover {
        border-color: var(--set-primary-light);
        box-shadow: var(--set-shadow-md);
    }

    .settings-sidebar-set .nav-link-set {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 10px;
        color: var(--set-text-secondary);
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 0.85rem;
        font-weight: 500;
        border: 2px solid transparent;
        margin-bottom: 4px;
    }

    .settings-sidebar-set .nav-link-set:hover {
        background: var(--set-bg-body);
        color: var(--set-primary);
        border-color: var(--set-primary-bg);
    }

    .settings-sidebar-set .nav-link-set.active {
        background: var(--set-primary-bg);
        color: var(--set-primary);
        border-color: var(--set-primary);
        font-weight: 600;
    }

    [data-theme="dark"] .settings-sidebar-set .nav-link-set.active {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #6EA8FE;
    }

    .settings-sidebar-set .nav-link-set i {
        width: 20px;
        text-align: center;
        font-size: 0.95rem;
    }

    .settings-sidebar-set .nav-link-set .badge-set {
        margin-left: auto;
        background: var(--set-primary);
        color: white;
        font-size: 0.6rem;
        padding: 2px 8px;
        border-radius: 12px;
    }

    /* ================================================================
       SETTINGS CONTENT
       ================================================================ */
    .settings-content-set {
        background: var(--set-bg-card);
        border-radius: 16px;
        padding: 24px;
        border: 2px solid var(--set-border-color);
        box-shadow: var(--set-shadow-sm);
        transition: all 0.3s ease;
    }

    .settings-content-set:hover {
        border-color: var(--set-primary-light);
        box-shadow: var(--set-shadow-md);
    }

    .settings-content-set .section-header-set {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding-bottom: 16px;
        margin-bottom: 20px;
        border-bottom: 2px solid var(--set-border-color);
    }

    .settings-content-set .section-header-set h2 {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--set-text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .settings-content-set .section-header-set h2 i {
        color: var(--set-primary);
    }

    /* ================================================================
       FORM CONTROLS
       ================================================================ */
    .form-label-set {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--set-text-primary);
        margin-bottom: 4px;
        display: block;
    }

    .form-label-set .required-set {
        color: #EF4444;
        margin-left: 2px;
    }

    .form-control-set {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid var(--set-border-color);
        border-radius: 10px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--set-bg-card);
        color: var(--set-text-primary);
        font-family: inherit;
    }

    .form-control-set:focus {
        border-color: var(--set-primary);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
    }

    .form-control-set::placeholder {
        color: var(--set-text-secondary);
        opacity: 0.5;
    }

    select.form-control-set {
        cursor: pointer;
    }

    [data-theme="dark"] select.form-control-set option {
        background: #1E293B;
        color: #F1F5F9;
    }

    .form-row-set {
        margin-bottom: 16px;
    }

    .form-actions-set {
        display: flex;
        gap: 12px;
        padding-top: 16px;
        margin-top: 16px;
        border-top: 2px solid var(--set-border-color);
        flex-wrap: wrap;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-set {
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
        font-family: inherit;
    }

    .btn-primary-set {
        background: var(--set-primary);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    .btn-primary-set:hover {
        background: var(--set-primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(11, 94, 215, 0.4);
        color: white;
    }

    .btn-secondary-set {
        background: var(--set-bg-body);
        color: var(--set-text-primary);
        border: 2px solid var(--set-border-color);
    }
    .btn-secondary-set:hover {
        background: var(--set-bg-card);
        border-color: var(--set-primary);
        color: var(--set-primary);
    }

    .btn-success-set {
        background: #059669;
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    .btn-success-set:hover {
        background: #047857;
        transform: translateY(-2px);
        color: white;
    }

    .btn-danger-set {
        background: #EF4444;
        color: white;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
    .btn-danger-set:hover {
        background: #DC2626;
        transform: translateY(-2px);
        color: white;
    }

    .btn-sm-set {
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
    }

    /* ================================================================
       BRANCH CARD
       ================================================================ */
    .branch-card-set {
        background: var(--set-bg-card);
        border-radius: 12px;
        padding: 16px 18px;
        border: 2px solid var(--set-border-color);
        transition: all 0.3s ease;
        margin-bottom: 12px;
    }

    .branch-card-set:hover {
        border-color: var(--set-primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.06);
    }

    .branch-card-set .branch-header-set {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .branch-card-set .branch-name-set {
        font-size: 1rem;
        font-weight: 600;
        color: var(--set-text-primary);
    }

    .branch-card-set .branch-location-set {
        font-size: 0.8rem;
        color: var(--set-text-secondary);
        margin-top: 2px;
    }

    .badge-status-set {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .badge-status-set.active { background: #E6F7EE; color: #059669; }
    .badge-status-set.inactive { background: #FEE2E2; color: #EF4444; }

    [data-theme="dark"] .badge-status-set.active { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .badge-status-set.inactive { background: #3A1A1A; color: #F87171; }

    /* ================================================================
       EDIT BRANCH FORM
       ================================================================ */
    .edit-branch-form-set {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid var(--set-border-color);
    }

    .edit-branch-form-set .form-title-set {
        font-size: 1rem;
        font-weight: 600;
        color: var(--set-text-primary);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .edit-branch-form-set .form-title-set i {
        color: var(--set-primary);
    }

    /* ================================================================
       MESSAGE
       ================================================================ */
    .message-set {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 0.9rem;
        font-weight: 500;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message-set.success {
        background: #D1FAE5;
        color: #065F46;
        border: 2px solid #059669;
    }

    .message-set.error {
        background: #FEE2E2;
        color: #991B1B;
        border: 2px solid #EF4444;
    }

    [data-theme="dark"] .message-set.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .message-set.error { background: #3A1A1A; color: #F87171; }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state-set {
        text-align: center;
        padding: 40px 20px;
        color: var(--set-text-secondary);
    }

    .empty-state-set i {
        font-size: 3rem;
        color: var(--set-border-color);
        margin-bottom: 12px;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-set {
        padding: 14px 0;
        border-top: 2px solid var(--set-border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--set-text-secondary);
    }

    .footer-set .footer-brand-set {
        color: var(--set-primary);
        font-weight: 600;
    }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-set {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .settings-sidebar-set {
            position: relative;
            top: 0;
            margin-bottom: 16px;
        }
    }

    @media (max-width: 768px) {
        .page-header-set { padding: 20px 24px; }
        .page-header-set .page-title-set { font-size: 1.4rem; }
        .settings-content-set { padding: 16px; }
        .branch-card-set .branch-header-set { flex-direction: column; align-items: flex-start; }
    }

    @media (max-width: 480px) {
        .page-header-set { padding: 16px 18px; flex-direction: column; align-items: flex-start !important; }
        .page-header-set .page-title-set { font-size: 1.1rem; }
        .settings-content-set { padding: 12px; }
    }

    @media print {
        .btn-set, .btn-outline-light-set, .settings-sidebar-set { display: none !important; }
        .page-header-set { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header - 100% BLUE -->
    <div class="page-header-set animate-fade-in-up-set">
        <div>
            <h1 class="page-title-set">
                <i class="fas fa-cog"></i>
                System Settings
                <span class="role-badge-display-set">ADMIN</span>
            </h1>
            <p class="page-subtitle-set">
                Manage system configurations
                <span class="header-badge-set">
                    <i class="fas fa-store-alt"></i>
                    <?= $selected_branch_id === 'all' ? 'All Branches' : htmlspecialchars($user_branch_name) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light-set">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-set <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- SETTINGS LAYOUT -->
    <!-- ================================================================ -->
    <div style="display:grid;grid-template-columns:1fr;gap:20px;" class="settings-layout-set">
        
        <!-- Settings Sidebar -->
        <div style="max-width:100%;" class="settings-sidebar-wrapper-set">
            <div class="settings-sidebar-set">
                <nav>
                    <a href="?tab=general&branch=<?= $selected_branch_id ?>" class="nav-link-set <?= $active_tab === 'general' ? 'active' : '' ?>">
                        <i class="fas fa-cog"></i>
                        <span>General</span>
                    </a>
                    <a href="?tab=financial&branch=<?= $selected_branch_id ?>" class="nav-link-set <?= $active_tab === 'financial' ? 'active' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Financial</span>
                    </a>
                    <a href="?tab=hours&branch=<?= $selected_branch_id ?>" class="nav-link-set <?= $active_tab === 'hours' ? 'active' : '' ?>">
                        <i class="fas fa-clock"></i>
                        <span>Business Hours</span>
                    </a>
                    <a href="?tab=branches&branch=<?= $selected_branch_id ?>" class="nav-link-set <?= $active_tab === 'branches' ? 'active' : '' ?>">
                        <i class="fas fa-store-alt"></i>
                        <span>Branches</span>
                        <span class="badge-set"><?= count($all_branches) ?></span>
                    </a>
                    <a href="?tab=security&branch=<?= $selected_branch_id ?>" class="nav-link-set <?= $active_tab === 'security' ? 'active' : '' ?>">
                        <i class="fas fa-shield-alt"></i>
                        <span>Security</span>
                    </a>
                    <a href="?tab=system&branch=<?= $selected_branch_id ?>" class="nav-link-set <?= $active_tab === 'system' ? 'active' : '' ?>">
                        <i class="fas fa-server"></i>
                        <span>System</span>
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Settings Content -->
        <div class="settings-content-set animate-fade-in-up-set" style="animation-delay:0.05s;">
            
            <!-- ================================================================ -->
            <!-- GENERAL TAB -->
            <!-- ================================================================ -->
            <?php if ($active_tab === 'general'): ?>
            
            <div class="section-header-set">
                <h2><i class="fas fa-cog"></i> General Settings</h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_general">
                
                <div class="form-row-set">
                    <label class="form-label-set">Site Name <span class="required-set">*</span></label>
                    <input type="text" name="site_name" class="form-control-set" value="<?= htmlspecialchars($settings['site_name']) ?>" required>
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set">Phone Number</label>
                    <input type="text" name="site_phone" class="form-control-set" value="<?= htmlspecialchars($settings['site_phone']) ?>">
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set">Email</label>
                    <input type="email" name="site_email" class="form-control-set" value="<?= htmlspecialchars($settings['site_email']) ?>">
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set">Address</label>
                    <input type="text" name="site_address" class="form-control-set" value="<?= htmlspecialchars($settings['site_address']) ?>">
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set">Currency</label>
                    <input type="text" name="currency" class="form-control-set" value="<?= htmlspecialchars($settings['currency']) ?>">
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set">Timezone</label>
                    <select name="timezone" class="form-control-set">
                        <option value="Africa/Dar_es_Salaam" <?= $settings['timezone'] == 'Africa/Dar_es_Salaam' ? 'selected' : '' ?>>Africa/Dar_es_Salaam</option>
                        <option value="Africa/Nairobi" <?= $settings['timezone'] == 'Africa/Nairobi' ? 'selected' : '' ?>>Africa/Nairobi</option>
                        <option value="Africa/Maputo" <?= $settings['timezone'] == 'Africa/Maputo' ? 'selected' : '' ?>>Africa/Maputo</option>
                        <option value="Africa/Kampala" <?= $settings['timezone'] == 'Africa/Kampala' ? 'selected' : '' ?>>Africa/Kampala</option>
                        <option value="Africa/Lagos" <?= $settings['timezone'] == 'Africa/Lagos' ? 'selected' : '' ?>>Africa/Lagos</option>
                        <option value="UTC" <?= $settings['timezone'] == 'UTC' ? 'selected' : '' ?>>UTC</option>
                    </select>
                </div>
                
                <div class="form-actions-set">
                    <button type="submit" class="btn-set btn-success-set"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
            
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- FINANCIAL TAB -->
            <!-- ================================================================ -->
            <?php if ($active_tab === 'financial'): ?>
            
            <div class="section-header-set">
                <h2><i class="fas fa-money-bill-wave"></i> Financial Settings</h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_financial">
                
                <div class="form-row-set">
                    <label class="form-label-set">Currency Symbol</label>
                    <input type="text" name="currency_symbol" class="form-control-set" value="<?= htmlspecialchars($settings['currency']) ?>">
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set">Tax Percentage (%)</label>
                    <input type="number" name="tax_percent" class="form-control-set" step="0.01" min="0" value="<?= htmlspecialchars($settings['tax_percent']) ?>">
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set">Max Discount Percentage (%)</label>
                    <input type="number" name="max_discount_percent" class="form-control-set" step="0.01" min="0" max="100" value="<?= htmlspecialchars($settings['max_discount_percent']) ?>">
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set">Default Registration Fee</label>
                    <input type="number" name="registration_fee" class="form-control-set" step="0.01" min="0" value="<?= htmlspecialchars($settings['registration_fee']) ?>">
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set">Default Consultation Fee</label>
                    <input type="number" name="consultation_fee" class="form-control-set" step="0.01" min="0" value="<?= htmlspecialchars($settings['consultation_fee']) ?>">
                </div>
                
                <div class="form-actions-set">
                    <button type="submit" class="btn-set btn-success-set"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
            
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- BUSINESS HOURS TAB -->
            <!-- ================================================================ -->
            <?php if ($active_tab === 'hours'): ?>
            
            <div class="section-header-set">
                <h2><i class="fas fa-clock"></i> Business Hours</h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_hours">
                
                <div class="form-row-set">
                    <label class="form-label-set">Opening Time <span class="required-set">*</span></label>
                    <input type="time" name="business_hours_start" class="form-control-set" value="<?= htmlspecialchars($settings['business_hours_start']) ?>" required>
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set">Closing Time <span class="required-set">*</span></label>
                    <input type="time" name="business_hours_end" class="form-control-set" value="<?= htmlspecialchars($settings['business_hours_end']) ?>" required>
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set">Weekend Days</label>
                    <div style="display:flex;flex-wrap:wrap;gap:12px;">
                        <?php 
                        $weekend_days = explode(',', $settings['weekend_days']);
                        $all_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        foreach ($all_days as $day):
                        ?>
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.85rem;color:var(--set-text-primary);">
                                <input type="checkbox" name="weekend_days[]" value="<?= $day ?>" <?= in_array($day, $weekend_days) ? 'checked' : '' ?>>
                                <?= $day ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-actions-set">
                    <button type="submit" class="btn-set btn-success-set"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
            
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- BRANCHES TAB -->
            <!-- ================================================================ -->
            <?php if ($active_tab === 'branches'): ?>
            
            <div class="section-header-set">
                <h2>
                    <i class="fas fa-store-alt"></i>
                    Branches
                    <span style="font-size:0.85rem;font-weight:400;color:var(--set-text-secondary);">(<?= count($all_branches) ?> branches)</span>
                </h2>
            </div>
            
            <?php if (count($all_branches) > 0): ?>
                <?php foreach ($all_branches as $branch): ?>
                    <div class="branch-card-set">
                        <div class="branch-header-set">
                            <div>
                                <div class="branch-name-set">
                                    <?= htmlspecialchars($branch['name']) ?>
                                    <span class="badge-status-set <?= ($branch['status'] ?? 'active') === 'active' ? 'active' : 'inactive' ?>" style="margin-left:8px;">
                                        <?= ($branch['status'] ?? 'active') === 'active' ? '✅ Active' : '❌ Inactive' ?>
                                    </span>
                                </div>
                                <?php if (!empty($branch['location'])): ?>
                                    <div class="branch-location-set">
                                        <i class="fas fa-map-marker-alt" style="margin-right:4px;"></i>
                                        <?= htmlspecialchars($branch['location']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="branch-location-set">
                                    <?php if (!empty($branch['phone'])): ?>
                                        <i class="fas fa-phone" style="margin-right:4px;"></i>
                                        <?= htmlspecialchars($branch['phone']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($branch['email'])): ?>
                                        <span style="margin:0 8px;">|</span>
                                        <i class="fas fa-envelope" style="margin-right:4px;"></i>
                                        <?= htmlspecialchars($branch['email']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <a href="?tab=branches&branch=<?= $selected_branch_id ?>&edit_branch=<?= $branch['id'] ?>" class="btn-set btn-primary-set btn-sm-set">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state-set">
                    <i class="fas fa-store-alt"></i>
                    <p>No branches found.</p>
                </div>
            <?php endif; ?>
            
            <!-- Edit Branch Form -->
            <?php if ($edit_branch): ?>
            <div class="edit-branch-form-set">
                <div class="form-title-set">
                    <i class="fas fa-edit"></i> Edit Branch: <?= htmlspecialchars($edit_branch['name']) ?>
                    <a href="?tab=branches&branch=<?= $selected_branch_id ?>" style="font-size:0.8rem;color:var(--set-text-secondary);text-decoration:none;margin-left:8px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_branch">
                    <input type="hidden" name="branch_id" value="<?= $edit_branch['id'] ?>">
                    
                    <div class="form-row-set">
                        <label class="form-label-set">Branch Name <span class="required-set">*</span></label>
                        <input type="text" name="branch_name" class="form-control-set" value="<?= htmlspecialchars($edit_branch['name']) ?>" required>
                    </div>
                    
                    <div class="form-row-set">
                        <label class="form-label-set">Location</label>
                        <input type="text" name="branch_location" class="form-control-set" value="<?= htmlspecialchars($edit_branch['location'] ?? '') ?>">
                    </div>
                    
                    <div class="form-row-set">
                        <label class="form-label-set">Phone</label>
                        <input type="text" name="branch_phone" class="form-control-set" value="<?= htmlspecialchars($edit_branch['phone'] ?? '') ?>">
                    </div>
                    
                    <div class="form-row-set">
                        <label class="form-label-set">Email</label>
                        <input type="email" name="branch_email" class="form-control-set" value="<?= htmlspecialchars($edit_branch['email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-row-set">
                        <label class="form-label-set">Status</label>
                        <select name="branch_status" class="form-control-set">
                            <option value="active" <?= ($edit_branch['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($edit_branch['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-actions-set">
                        <button type="submit" class="btn-set btn-success-set"><i class="fas fa-save"></i> Update Branch</button>
                        <a href="?tab=branches&branch=<?= $selected_branch_id ?>" class="btn-set btn-secondary-set">Cancel</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- SECURITY TAB -->
            <!-- ================================================================ -->
            <?php if ($active_tab === 'security'): ?>
            
            <div class="section-header-set">
                <h2><i class="fas fa-shield-alt"></i> Security Settings</h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_security">
                
                <div class="form-row-set">
                    <label class="form-label-set">Password Policy</label>
                    <select name="password_policy" class="form-control-set">
                        <option value="low" <?= $settings['password_policy'] === 'low' ? 'selected' : '' ?>>Low (min 6 chars)</option>
                        <option value="medium" <?= $settings['password_policy'] === 'medium' ? 'selected' : '' ?>>Medium (min 8 chars, mixed case)</option>
                        <option value="high" <?= $settings['password_policy'] === 'high' ? 'selected' : '' ?>>High (min 10 chars, mixed case, numbers, special)</option>
                    </select>
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set">Session Timeout (minutes)</label>
                    <input type="number" name="session_timeout" class="form-control-set" min="5" max="1440" value="<?= htmlspecialchars($settings['session_timeout']) ?>">
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set">Max Login Attempts</label>
                    <input type="number" name="max_login_attempts" class="form-control-set" min="3" max="20" value="<?= htmlspecialchars($settings['max_login_attempts']) ?>">
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="two_factor_auth" value="1" <?= $settings['two_factor_auth'] ? 'checked' : '' ?>>
                        Enable Two-Factor Authentication
                    </label>
                </div>
                
                <div class="form-actions-set">
                    <button type="submit" class="btn-set btn-success-set"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
            
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- SYSTEM TAB -->
            <!-- ================================================================ -->
            <?php if ($active_tab === 'system'): ?>
            
            <div class="section-header-set">
                <h2><i class="fas fa-server"></i> System Settings</h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_system">
                
                <div class="form-row-set">
                    <label class="form-label-set" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="maintenance_mode" value="1" <?= $settings['maintenance_mode'] ? 'checked' : '' ?>>
                        Maintenance Mode (site will be inaccessible to non-admin users)
                    </label>
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="debug_mode" value="1" <?= $settings['debug_mode'] ? 'checked' : '' ?>>
                        Debug Mode (show detailed error messages)
                    </label>
                </div>
                
                <div class="form-row-set">
                    <label class="form-label-set" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="log_activities" value="1" <?= $settings['log_activities'] ? 'checked' : '' ?>>
                        Log User Activities
                    </label>
                </div>
                
                <div class="form-actions-set">
                    <button type="submit" class="btn-set btn-success-set"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
            
            <?php endif; ?>
            
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-set">
        <p>
            <span class="footer-brand-set">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            System Settings
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

    console.log('%c⚙️ Braick - System Settings', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ No duplicate CSS/JS', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Page Header: 100% BLUE - NO WHITE BACKGROUND', 'font-size:13px; color:#34D399;');
    console.log('%c📋 Tab: <?= ucfirst($active_tab) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>