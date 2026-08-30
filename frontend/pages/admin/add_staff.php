<?php
// ================================================================
// FILE: frontend/pages/admin/add_staff.php
// SUPER ADMIN - ADD STAFF
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
// WITH AUTO-GENERATE PASSWORD
// BRAICK DISPENSARY - FIXED FOR EXISTING DATABASE
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
// CHECK IF USER HAS ADMIN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET BRANCH ID FROM URL
// ================================================================
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
$branch_name = '';

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
// GET BRANCH INFO
// ================================================================
if ($branch_id > 0) {
    $stmt = $db->prepare("SELECT name, location FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch_info = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_info) {
        $branch_name = $branch_info['name'];
    }
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $branch_id > 0 ? $branch_id : ($_GET['branch'] ?? 'all');

// ================================================================
// GET STATISTICS FOR SIDEBAR
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
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branches[] = $row;
}

// ================================================================
// AVAILABLE ROLES - From users table ENUM
// ================================================================
$available_roles = [
    'doctor' => 'Medical Doctor',
    'pharmacy' => 'Pharmacy Staff',
    'reception' => 'Receptionist',
    'laboratory' => 'Lab Technician',
    'cashier' => 'Cashier',
    'admin' => 'Administrator'
];

// ================================================================
// AUTO-GENERATE PASSWORD FUNCTION
// ================================================================
function generatePassword($full_name, $branch_id, $user_id) {
    $clean_name = preg_replace('/[^a-zA-Z]/', '', $full_name);
    $name_part = strtoupper(substr($clean_name, 0, 4));
    if (strlen($name_part) < 3) {
        $name_part = 'USER';
    }
    
    $branch_code = 'BR' . str_pad($branch_id, 2, '0', STR_PAD_LEFT);
    $user_code = 'UID' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
    
    $password = $name_part . $branch_code . $user_code;
    
    if (strlen($password) < 8) {
        $password .= rand(100, 999);
    }
    
    return $password;
}

// ================================================================
// HANDLE AJAX REQUEST FOR AUTO-GENERATE PASSWORD
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_password') {
    header('Content-Type: application/json');
    
    $full_name = $_POST['full_name'] ?? '';
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    $username = $_POST['username'] ?? '';
    
    if (empty($full_name) || $branch_id <= 0) {
        echo json_encode(['success' => false, 'password' => '', 'error' => 'Name and branch required']);
        exit;
    }
    
    if ($user_id <= 0) {
        $user_id = rand(1000, 9999);
    }
    
    $password = generatePassword($full_name, $branch_id, $user_id);
    
    echo json_encode(['success' => true, 'password' => $password, 'user_id' => $user_id]);
    exit;
}

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';
$errors = [];
$form_data = [
    'full_name' => '',
    'username' => '',
    'email' => '',
    'phone' => '',
    'password' => '',
    'branch_id' => $branch_id > 0 ? $branch_id : 1,
    'role' => 'doctor',
    'specialty' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $form_data['full_name'] = trim($_POST['full_name'] ?? '');
    $form_data['username'] = trim($_POST['username'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $form_data['password'] = $_POST['password'] ?? '';
    $form_data['branch_id'] = (int)($_POST['branch_id'] ?? 0);
    $form_data['role'] = $_POST['role'] ?? 'doctor';
    $form_data['specialty'] = trim($_POST['specialty'] ?? '');
    
    // Validation
    if (empty($form_data['full_name'])) {
        $errors[] = 'Full name is required';
    }
    if (empty($form_data['username'])) {
        $errors[] = 'Username is required';
    }
    if (empty($form_data['email'])) {
        $errors[] = 'Email is required';
    }
    if (empty($form_data['password'])) {
        $errors[] = 'Password is required';
    }
    if (empty($form_data['role'])) {
        $errors[] = 'Role is required';
    }
    if ($form_data['branch_id'] <= 0) {
        $errors[] = 'Branch is required';
    }
    
    // Validate email format
    if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    // Check if username exists
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$form_data['username']]);
        if ($stmt->fetch()) {
            $errors[] = 'Username already exists';
        }
    }
    
    // Check if email exists
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$form_data['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already exists';
        }
    }
    
    // Save employee
    if (empty($errors)) {
        $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
        
        try {
            $stmt = $db->prepare("
                INSERT INTO users (username, password, full_name, email, phone, role, branch_id, specialty, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ");
            
            $stmt->execute([
                $form_data['username'], 
                $hashed_password, 
                $form_data['full_name'], 
                $form_data['email'], 
                $form_data['phone'], 
                $form_data['role'],
                $form_data['branch_id'],
                $form_data['specialty']
            ]);
            
            $new_user_id = $db->lastInsertId();
            
            // Log activity
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                    VALUES (?, ?, 'employee_added', ?, NOW())
                ");
                $details = "Staff {$form_data['full_name']} added with role: {$form_data['role']}";
                $stmt->execute([$_SESSION['user_id'], $form_data['branch_id'], $details]);
            } catch (Exception $e) {
                // Ignore logging errors
            }
            
            $message = "✅ Staff added successfully with role: <strong>{$form_data['role']}</strong>! Password: <strong>{$form_data['password']}</strong>";
            $message_type = 'success';
            
            // Clear form data on success
            $form_data = [
                'full_name' => '',
                'username' => '',
                'email' => '',
                'phone' => '',
                'password' => '',
                'branch_id' => $branch_id > 0 ? $branch_id : 1,
                'role' => 'doctor',
                'specialty' => ''
            ];
            
            echo '<script>
                setTimeout(function(){ 
                    window.location.href = "branch_staff.php?id=' . $form_data['branch_id'] . '&success=1"; 
                }, 3000);
            </script>';
            
        } catch (PDOException $e) {
            $errors[] = 'Failed to add staff: ' . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
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

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';

$page_title = 'Add Staff';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Staff - <?= htmlspecialchars($branch_name) ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
        :root {
            --primary: #1A56DB;
            --primary-dark: #1A3E8C;
            --primary-light: #3B82F6;
            --primary-bg: #E8EFF9;
            --primary-solid: #1A56DB;
            
            --success: #1A56DB;
            --success-dark: #1A3E8C;
            --success-light: #3B82F6;
            --success-bg: #E8EFF9;
            
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            
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
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 30px rgba(0,0,0,0.12);
            
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --table-hover: #F8FAFC;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-light: #60A5FA;
            --primary-bg: #1E3A5F;
            --primary-solid: #2563EB;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --purple-bg: #2D1B5F;
            --table-hover: #1E293B;
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
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(26, 86, 219, 0.12);
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
            background: var(--primary-solid);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.02);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .datetime i { color: var(--primary-light); }
        
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
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
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
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: var(--primary-solid);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(26, 86, 219, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.03);
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
            background: rgba(255,255,255,0.12);
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
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: var(--radius);
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
            cursor: pointer;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           FORM CARD
           ================================================================ */
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
            border-color: #0B5ED7;
            box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
        }
        
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
            background: linear-gradient(135deg, #0B5ED7, #1A73E8);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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
            border-color: #0B5ED7;
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
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
        
        .password-input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .password-input-group .form-control {
            padding-right: 50px;
        }
        
        .password-input-group .password-toggle {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 6px 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        
        .password-input-group .password-toggle:hover {
            color: #0B5ED7;
            background: var(--bg-body);
        }
        
        .password-input-group .password-toggle i {
            pointer-events: none;
        }
        
        .btn-generate {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            background: #0B5ED7;
            color: white;
            white-space: nowrap;
            min-height: 34px;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
        }
        
        .btn-generate:hover {
            background: #0A4CA8;
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(11, 94, 215, 0.35);
        }
        
        .btn-generate:active {
            transform: translateY(0px);
        }
        
        .btn-generate i {
            font-size: 0.8rem;
        }
        
        .password-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 4px;
        }
        
        .password-actions .help-text {
            margin-top: 0;
            font-size: 0.65rem;
        }
        
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
            color: #0B5ED7;
        }
        
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
        
        .btn-primary:active {
            transform: translateY(0px);
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
            padding: 6px 16px;
            font-size: 0.8rem;
            min-height: 36px;
            min-width: 90px;
        }
        
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 24px;
            margin-top: 24px;
            border-top: 2px solid var(--border-color);
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #0B5ED7;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        [data-theme="dark"] .section-title {
            color: #6EA8FE;
        }
        
        .section-divider {
            border: none;
            border-top: 2px dashed var(--border-color);
            margin: 12px 0 16px;
        }
        
        .help-text {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        
        .alert-modern {
            padding: 14px 18px;
            border-radius: var(--radius);
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .alert-modern-success {
            background: var(--primary-bg);
            color: var(--primary-dark);
            border: 1px solid var(--primary-solid);
        }
        
        .alert-modern-error {
            background: var(--danger-bg);
            color: var(--danger-dark);
            border: 1px solid var(--danger);
        }
        
        .alert-modern i { font-size: 1.1rem; margin-top: 2px; }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .footer .footer-brand {
            color: var(--primary-solid);
            font-weight: 500;
        }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 9999;
            max-width: 420px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: #059669; }
        .toast-custom.error { background: #EF4444; }
        .toast-custom.warning { background: #F59E0B; color: #1E293B; }
        .toast-custom.info { background: #0B5ED7; }
        
        /* Sidebar */
        .sidebar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            bottom: 0 !important;
            width: 270px !important;
            background: #0B4EA8 !important;
            color: white !important;
            z-index: 50 !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            transition: transform 0.3s ease-in-out !important;
            transform: translateX(0) !important;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15) !important;
        }
        
        #sidebarOverlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 45;
            display: none;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }
        
        #sidebarOverlay.active {
            display: block !important;
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .sidebar { transform: translateX(-100%) !important; }
            .sidebar.open { transform: translateX(0) !important; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .form-card { padding: 18px 16px; }
            .form-header { flex-direction: column; text-align: center; }
            .form-header-icon { width: 48px; height: 48px; font-size: 1.2rem; }
            .btn { padding: 8px 16px; font-size: 0.8rem; min-height: 38px; min-width: 100%; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .password-actions { flex-direction: column; align-items: stretch; }
            .btn-generate { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        .grid { display: grid; }
        .grid-cols-1 { grid-template-columns: 1fr; }
        .md\:grid-cols-2 { grid-template-columns: 1fr 1fr; }
        .md\:col-span-2 { grid-column: span 2; }
        .gap-6 { gap: 24px; }
        .mt-2 { margin-top: 8px; }
        .mb-2 { margin-bottom: 8px; }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SIDEBAR OVERLAY -->
<!-- ================================================================ -->
<div id="sidebarOverlay"></div>

<!-- ================================================================ -->
<!-- SIDEBAR -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    <div style="padding:18px 16px 14px;border-bottom:2px solid #0B3D8A;background:#0B4EA8;position:sticky;top:0;z-index:5;">
        <div style="display:flex;align-items:center;gap:12px;">
            <img src="<?= $logo_url ?>" alt="Braick Logo" style="width:42px;height:42px;border-radius:10px;object-fit:cover;background:white;padding:4px;border:2px solid rgba(255,255,255,0.1);"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p style="color:white;font-weight:700;font-size:0.95rem;line-height:1.2;margin:0;">Braick Dispensary</p>
                <p style="color:#9EC5FE;font-size:0.65rem;font-weight:500;margin:0;">Super Admin</p>
            </div>
        </div>
    </div>
    
    <div style="padding:10px 14px;border-bottom:2px solid #0B3D8A;background:#0B4EA8;">
        <select id="sidebarBranchSelector" onchange="switchBranch(this.value)" style="width:100%;padding:7px 10px;border-radius:8px;border:none;background:rgba(255,255,255,0.12);color:white;font-size:0.75rem;cursor:pointer;outline:none;transition:all 0.3s ease;appearance:none;-webkit-appearance:none;background-image:url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%2212%22 viewBox=%220 0 12 12%22%3E%3Cpath fill=%22white%22 d=%22M6 8L1 3h10z%22/%3E%3C/svg%3E');background-repeat:no-repeat;background-position:right 10px center;">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?> style="background:#0B4EA8;color:white;padding:8px;">
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <nav style="padding:10px 8px 20px;">
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Main Menu</div>
        
        <a href="/dispensary_system/frontend/pages/admin/dashboard.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-home"></i> Dashboard
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/employees.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-users"></i> Employees
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/patients.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-user-injured"></i> Patients
        </a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Modules</div>
        
        <a href="/dispensary_system/frontend/pages/admin/doctors_list.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-user-md"></i> Doctors
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_pharmacy.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-prescription"></i> Pharmacy
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_reception.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-headset"></i> Reception
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_laboratory.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-flask"></i> Laboratory
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_cashier.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-cash-register"></i> Cashier
        </a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Management</div>
        
        <a href="/dispensary_system/frontend/pages/admin/branches.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-store-alt"></i> Branches
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/departments.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-building"></i> Departments
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/reports.php?branch=<?= $selected_branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-chart-bar"></i> Reports
        </a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Account</div>
        
        <a href="/dispensary_system/frontend/pages/admin/profile.php" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-user-circle"></i> Profile
        </a>
        
        <a href="/dispensary_system/frontend/pages/logout.php" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;border-top:2px solid rgba(255,255,255,0.08);padding-top:10px;margin-top:6px;color:#FCA5A5;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn" style="background:transparent;border:none;cursor:pointer;color:var(--text-secondary);font-size:1.2rem;padding:8px;">
            <i class="fas fa-bars"></i>
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
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-plus mr-2"></i> Add New Staff
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store"></i>
                Add staff member to <?= htmlspecialchars($branch_name) ?>
                <span class="header-badge">
                    <i class="fas fa-building"></i> Branch #<?= $branch_id ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-users"></i> <?= $total_employees ?> employees
                </span>
                <span class="header-badge">
                    <i class="fas fa-user-md"></i> <?= $total_doctors ?> doctors
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="branch_staff.php?id=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Staff
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert-modern alert-modern-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FORM -->
    <!-- ================================================================ -->
    <div class="form-card animate-fade-in-up">
        <!-- Form Header -->
        <div class="form-header">
            <div class="form-header-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <div>
                <h3>Staff Information</h3>
                <p>Enter staff details and assign role</p>
            </div>
        </div>
        
        <form method="POST" action="" id="addStaffForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
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
                        <input type="text" name="full_name" id="fullName" class="form-control" 
                               placeholder="Enter full name" 
                               value="<?= htmlspecialchars($form_data['full_name']) ?>" required>
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                    </div>
                </div>
                
                <!-- Username -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-at text-blue-600"></i> Username
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="username" id="username" class="form-control" 
                               placeholder="Enter username" 
                               value="<?= htmlspecialchars($form_data['username']) ?>" required>
                        <span class="input-icon"><i class="fas fa-at"></i></span>
                    </div>
                </div>
                
                <!-- Email -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-envelope text-green-600"></i> Email
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="email" name="email" class="form-control" 
                               placeholder="Enter email" 
                               value="<?= htmlspecialchars($form_data['email']) ?>" required>
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                    </div>
                </div>
                
                <!-- Phone -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-phone text-blue-600"></i> Phone Number
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="phone" class="form-control" 
                               placeholder="Enter phone number" 
                               value="<?= htmlspecialchars($form_data['phone']) ?>">
                        <span class="input-icon"><i class="fas fa-phone"></i></span>
                    </div>
                </div>
                
                <!-- Password with Auto-Generate -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-key text-yellow-600"></i> Password
                        <span class="required">*</span>
                    </label>
                    <div class="password-input-group">
                        <input type="password" name="password" id="passwordField" class="form-control" 
                               placeholder="Enter password or generate one" 
                               value="<?= htmlspecialchars($form_data['password']) ?>" required>
                        <button type="button" class="password-toggle" id="togglePassword" title="Show/Hide Password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-actions">
                        <button type="button" class="btn-generate" id="generatePasswordBtn">
                            <i class="fas fa-sync-alt"></i> Generate Password
                        </button>
                        <span class="help-text">Format: NAME + BRCODE + UID + ID</span>
                    </div>
                    <p class="help-text" id="passwordStrength" style="margin-top:4px;">
                        <i class="fas fa-info-circle"></i> 
                        Password includes: <strong>Name + Branch ID + User ID</strong>
                    </p>
                </div>
                
                <!-- Branch -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-store-alt text-green-600"></i> Branch
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <select name="branch_id" id="branchSelect" class="form-control" required>
                            <option value="">Select Branch</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>" <?= $branch['id'] == $form_data['branch_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($branch['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="input-icon"><i class="fas fa-store-alt"></i></span>
                    </div>
                </div>
                
                <!-- ================================================================ -->
                <!-- Role Selection -->
                <!-- ================================================================ -->
                <div class="md:col-span-2 mt-2">
                    <h3 class="section-title">
                        <i class="fas fa-user-tag"></i> Select Role
                        <span class="required">*</span>
                    </h3>
                    <p class="help-text mb-2">Select the primary role for this staff member.</p>
                    <hr class="section-divider">
                    
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:10px;">
                        <?php foreach ($available_roles as $role_key => $role_desc): ?>
                            <div style="display:flex; align-items:center; gap:10px; padding:10px 14px; border:2px solid <?= $form_data['role'] === $role_key ? '#0B5ED7' : 'var(--border-color)' ?>; border-radius:12px; background: <?= $form_data['role'] === $role_key ? 'var(--primary-bg)' : 'var(--bg-card)' ?>; cursor:pointer; transition:all 0.3s ease;"
                                 onclick="selectRole(this, '<?= $role_key ?>')"
                                 onmouseover="this.style.borderColor='#0B5ED7'"
                                 onmouseout="this.style.borderColor='<?= $form_data['role'] === $role_key ? '#0B5ED7' : 'var(--border-color)' ?>'">
                                <input type="radio" name="role" value="<?= $role_key ?>" id="role_<?= $role_key ?>" 
                                       <?= $form_data['role'] === $role_key ? 'checked' : '' ?>
                                       style="width:18px; height:18px; accent-color:#0B5ED7; cursor:pointer;">
                                <label for="role_<?= $role_key ?>" style="font-size:0.85rem; font-weight:500; color:var(--text-primary); cursor:pointer;">
                                    <i class="fas fa-circle text-[6px] text-blue-600 mr-1"></i>
                                    <?= htmlspecialchars($role_desc) ?>
                                    <span style="display:block; font-size:0.6rem; color:var(--text-secondary); font-weight:400;">
                                        <?= ucfirst($role_key) ?>
                                    </span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- ================================================================ -->
                <!-- Specialty (for doctors) -->
                <!-- ================================================================ -->
                <div class="md:col-span-2 mt-2" id="specialtySection" style="display: <?= $form_data['role'] === 'doctor' ? 'block' : 'none' ?>;">
                    <h3 class="section-title">
                        <i class="fas fa-stethoscope"></i> Specialty (for Doctors)
                    </h3>
                    <hr class="section-divider">
                    <div class="form-row-icon">
                        <input type="text" name="specialty" class="form-control" 
                               placeholder="Enter medical specialty (e.g. General Medicine, Pediatrics)" 
                               value="<?= htmlspecialchars($form_data['specialty']) ?>">
                        <span class="input-icon"><i class="fas fa-stethoscope"></i></span>
                    </div>
                    <p class="help-text">Optional - Only required for doctors</p>
                </div>
                
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Save Staff
                </button>
                <a href="branch_staff.php?id=<?= $branch_id ?>" class="btn btn-outline">
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
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; max-width:900px; margin:20px auto 0;">
        <div style="background:var(--bg-card); border-radius:16px; padding:14px 18px; border:2px solid var(--border-color); display:flex; align-items:center; gap:12px; transition:all 0.3s ease;" onmouseover="this.style.borderColor='#0B5ED7'" onmouseout="this.style.borderColor='var(--border-color)'">
            <div style="width:40px; height:40px; border-radius:10px; background:#E8F0FE; color:#0B5ED7; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0;">
                <i class="fas fa-lightbulb"></i>
            </div>
            <div>
                <h4 style="font-size:0.8rem; font-weight:600; color:var(--text-primary); margin:0;">Tip #1</h4>
                <p style="font-size:0.7rem; color:var(--text-secondary); margin:0;">Select the correct role</p>
            </div>
        </div>
        <div style="background:var(--bg-card); border-radius:16px; padding:14px 18px; border:2px solid var(--border-color); display:flex; align-items:center; gap:12px; transition:all 0.3s ease;" onmouseover="this.style.borderColor='#0B5ED7'" onmouseout="this.style.borderColor='var(--border-color)'">
            <div style="width:40px; height:40px; border-radius:10px; background:#E6F7EE; color:#059669; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <h4 style="font-size:0.8rem; font-weight:600; color:var(--text-primary); margin:0;">Tip #2</h4>
                <p style="font-size:0.7rem; color:var(--text-secondary); margin:0;">One role per staff</p>
            </div>
        </div>
        <div style="background:var(--bg-card); border-radius:16px; padding:14px 18px; border:2px solid var(--border-color); display:flex; align-items:center; gap:12px; transition:all 0.3s ease;" onmouseover="this.style.borderColor='#0B5ED7'" onmouseout="this.style.borderColor='var(--border-color)'">
            <div style="width:40px; height:40px; border-radius:10px; background:#FEF3C7; color:#F59E0B; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0;">
                <i class="fas fa-key"></i>
            </div>
            <div>
                <h4 style="font-size:0.8rem; font-weight:600; color:var(--text-primary); margin:0;">Tip #3</h4>
                <p style="font-size:0.7rem; color:var(--text-secondary); margin:0;">Click Generate for strong password</p>
            </div>
        </div>
        <div style="background:var(--bg-card); border-radius:16px; padding:14px 18px; border:2px solid var(--border-color); display:flex; align-items:center; gap:12px; transition:all 0.3s ease;" onmouseover="this.style.borderColor='#0B5ED7'" onmouseout="this.style.borderColor='var(--border-color)'">
            <div style="width:40px; height:40px; border-radius:10px; background:#E8F0FE; color:#0B5ED7; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0;">
                <i class="fas fa-eye"></i>
            </div>
            <div>
                <h4 style="font-size:0.8rem; font-weight:600; color:var(--text-primary); margin:0;">Tip #4</h4>
                <p style="font-size:0.7rem; color:var(--text-secondary); margin:0;">Click 👁️ to view password</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Add Staff - <?= htmlspecialchars($branch_name) ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
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
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    // ================================================================
    // SELECT ROLE (Radio Button)
    // ================================================================
    function selectRole(element, roleKey) {
        // Uncheck all radio buttons
        document.querySelectorAll('input[name="role"]').forEach(function(radio) {
            radio.checked = false;
        });
        
        // Check the selected one
        var radio = document.getElementById('role_' + roleKey);
        if (radio) {
            radio.checked = true;
        }
        
        // Update UI - remove border from all
        document.querySelectorAll('[onclick*="selectRole"]').forEach(function(el) {
            el.style.borderColor = 'var(--border-color)';
            el.style.background = 'var(--bg-card)';
        });
        
        // Add border to selected
        element.style.borderColor = '#0B5ED7';
        element.style.background = 'var(--primary-bg)';
        
        // Show/hide specialty section
        var specialtySection = document.getElementById('specialtySection');
        if (roleKey === 'doctor') {
            specialtySection.style.display = 'block';
        } else {
            specialtySection.style.display = 'none';
        }
        
        // Update form_data role
        document.querySelector('input[name="role"]').value = roleKey;
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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) {
            dtEl.textContent = now.toLocaleDateString('en-US', { 
                weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' 
            }) + ' • ' + now.toLocaleTimeString('en-US', { 
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true 
            });
        }
        var ftEl = document.getElementById('footerTime');
        if (ftEl) {
            ftEl.textContent = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true 
            });
        }
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
    // PASSWORD TOGGLE SHOW/HIDE
    // ================================================================
    document.getElementById('togglePassword')?.addEventListener('click', function() {
        var passwordField = document.getElementById('passwordField');
        var icon = this.querySelector('i');
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            passwordField.type = 'password';
            icon.className = 'fas fa-eye';
        }
    });

    // ================================================================
    // GENERATE PASSWORD BUTTON
    // ================================================================
    document.getElementById('generatePasswordBtn')?.addEventListener('click', function() {
        var fullName = document.getElementById('fullName').value.trim();
        var branchId = document.getElementById('branchSelect').value;
        var username = document.getElementById('username').value.trim();
        
        if (!fullName) {
            showToast('⚠️ Warning', 'Please enter the full name first', 'warning');
            document.getElementById('fullName').focus();
            return;
        }
        
        if (!branchId || branchId === '') {
            showToast('⚠️ Warning', 'Please select a branch first', 'warning');
            document.getElementById('branchSelect').focus();
            return;
        }
        
        var btn = this;
        var originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;
        
        var formData = new FormData();
        formData.append('action', 'generate_password');
        formData.append('full_name', fullName);
        formData.append('branch_id', branchId);
        formData.append('user_id', 0);
        formData.append('username', username);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                document.getElementById('passwordField').value = data.password;
                showToast('✅ Success', 'Password generated successfully!', 'success');
                
                var passwordField = document.getElementById('passwordField');
                passwordField.type = 'text';
                var icon = document.querySelector('#togglePassword i');
                if (icon) icon.className = 'fas fa-eye-slash';
                
                setTimeout(function() {
                    passwordField.type = 'password';
                    if (icon) icon.className = 'fas fa-eye';
                }, 3000);
            } else {
                showToast('❌ Error', data.error || 'Failed to generate password', 'error');
            }
        })
        .catch(function(error) {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
        })
        .finally(function() {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    });

    // ================================================================
    // PREVENT DOUBLE SUBMIT
    // ================================================================
    document.getElementById('addStaffForm')?.addEventListener('submit', function(e) {
        var submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            setTimeout(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Staff';
            }, 10000);
        }
    });

    console.log('%c👤 Braick - Add Staff', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏥 Branch: <?= htmlspecialchars($branch_name) ?> (ID: <?= $branch_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c✅ Using role as ENUM value (doctor, pharmacy, etc.)', 'font-size:13px; color:#059669;');
    console.log('%c✅ Table: users - columns: id, username, password, full_name, email, phone, role, branch_id, specialty, status', 'font-size:13px; color:#059669;');
    console.log('%c🔑 Password format: NAME + BRCODE + UID + ID', 'font-size:13px; color:#7C3AED;');
    console.log('%c🎨 Design: Modern, responsive, dark mode ready', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>