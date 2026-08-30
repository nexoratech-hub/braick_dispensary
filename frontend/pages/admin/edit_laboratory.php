<?php
// ================================================================
// FILE: frontend/pages/admin/edit_laboratory.php
// ADMIN - EDIT LABORATORY BRANCH
// BRAICK DISPENSARY - FIXED FOR EXISTING DATABASE
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK ADMIN ACCESS
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
// GET ADMIN DATA
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
// GET PARAMETERS
// ================================================================
$lab_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($lab_id <= 0) {
    header('Location: laboratories.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH LABORATORY DETAILS (from branches table)
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            b.*,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory' AND status = 'active') as active_technicians,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory') as total_technicians,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory' AND status = 'inactive') as inactive_technicians
        FROM branches b
        WHERE b.id = ?
    ");
    $stmt->execute([$lab_id]);
    $lab = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lab) {
        header('Location: laboratories.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching laboratory: " . $e->getMessage());
    header('Location: laboratories.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// PROCESS FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';
$update_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_laboratory') {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $logo = trim($_POST['logo'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    if (empty($name)) {
        $errors[] = "Laboratory name is required";
    }
    if (empty($location)) {
        $errors[] = "Location is required";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address";
    }
    
    // Check if name already exists (excluding current lab)
    if (!empty($name)) {
        try {
            $stmt = $db->prepare("SELECT id FROM branches WHERE name = ? AND id != ?");
            $stmt->execute([$name, $lab_id]);
            if ($stmt->fetch()) {
                $errors[] = "A branch with this name already exists";
            }
        } catch (Exception $e) {
            // Skip duplicate check on error
        }
    }
    
    // Check if email already exists (excluding current lab)
    if (!empty($email)) {
        try {
            $stmt = $db->prepare("SELECT id FROM branches WHERE email = ? AND id != ?");
            $stmt->execute([$email, $lab_id]);
            if ($stmt->fetch()) {
                $errors[] = "A branch with this email already exists";
            }
        } catch (Exception $e) {
            // Skip duplicate check on error
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE branches 
                SET 
                    name = ?,
                    location = ?,
                    phone = ?,
                    email = ?,
                    logo = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $location, $phone, $email, $logo, $status, $lab_id]);
            
            // Log activity
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                    VALUES (?, ?, 'laboratory_updated', ?, NOW())
                ");
                $details = "Laboratory branch updated: {$name} (ID: {$lab_id}) by " . $user_full_name;
                $stmt->execute([$user_id, $lab_id, $details]);
            } catch (Exception $e) {
                // Log error but don't stop
                error_log("Activity log error: " . $e->getMessage());
            }
            
            $update_success = true;
            $message = "✅ Laboratory updated successfully!";
            $message_type = 'success';
            
            // Refresh data
            $stmt = $db->prepare("
                SELECT 
                    b.*,
                    (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory' AND status = 'active') as active_technicians,
                    (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory') as total_technicians,
                    (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'laboratory' AND status = 'inactive') as inactive_technicians
                FROM branches b
                WHERE b.id = ?
            ");
            $stmt->execute([$lab_id]);
            $lab = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Redirect after success
            echo '<script>
                setTimeout(function(){ 
                    window.location.href = "laboratories.php?branch=' . $selected_branch_id . '&updated=1"; 
                }, 2000);
            </script>';
            
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
            error_log("Error updating laboratory: " . $e->getMessage());
        }
    }
    
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// STATUS FUNCTIONS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success',
        'inactive' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADERS
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Laboratory - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #073B8A);
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
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
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
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
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            flex: 1;
            max-width: 500px;
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
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
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
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: var(--primary-gradient-strong);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
            position: relative;
            overflow: hidden;
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
        
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
        
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
        
        .form-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 32px 36px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: var(--shadow-md);
        }
        
        .form-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
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
            width: 52px;
            height: 52px;
            background: var(--primary-gradient);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.25);
        }
        
        .form-card .form-header .form-title {
            font-size: 1.2rem;
            font-weight: 600;
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
            margin-bottom: 5px;
            display: block;
        }
        
        .form-label .required { color: var(--danger); margin-left: 2px; }
        .form-label .label-icon { margin-right: 4px; color: var(--primary); }
        
        .form-control {
            width: 100%;
            padding: 10px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
        }
        
        .form-control.is-invalid {
            border-color: var(--danger);
        }
        
        .form-control.is-valid {
            border-color: var(--success);
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        select.form-control {
            appearance: auto;
            cursor: pointer;
        }
        
        .form-row { margin-bottom: 20px; }
        .form-row:last-child { margin-bottom: 0; }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.82rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 2px solid transparent;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border-color: #34D399;
        }
        
        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
            border-color: #F87171;
        }
        
        [data-theme="dark"] .alert-success {
            background: #1A3A2A;
            color: #34D399;
            border-color: #059669;
        }
        
        [data-theme="dark"] .alert-danger {
            background: #3A1A1A;
            color: #F87171;
            border-color: #DC2626;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn:hover { transform: translateY(-2px); }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        .btn-primary:hover { box-shadow: 0 6px 24px rgba(11, 94, 215, 0.35); }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
        
        .btn-danger {
            background: var(--danger);
            color: white;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
        }
        .btn-danger:hover { box-shadow: 0 6px 24px rgba(220, 38, 38, 0.35); }
        
        .form-actions {
            display: flex;
            gap: 12px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
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
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 700; }
        
        .grid { display: grid; }
        .grid-cols-2 { grid-template-columns: 1fr 1fr; }
        .md\:grid-cols-3 { grid-template-columns: 1fr 1fr 1fr; }
        .gap-4 { gap: 16px; }
        .text-center { text-align: center; }
        .p-3 { padding: 12px; }
        .text-2xl { font-size: 1.5rem; }
        .text-sm { font-size: 0.75rem; }
        .font-bold { font-weight: 700; }
        .font-semibold { font-weight: 600; }
        .text-primary { color: var(--primary); }
        .text-gray-400 { color: var(--text-secondary); }
        .text-gray-500 { color: var(--text-secondary); }
        .text-blue-600 { color: #0B5ED7; }
        .text-green-600 { color: #059669; }
        .text-red-600 { color: #DC2626; }
        .text-purple-600 { color: #7C3AED; }
        .bg-blue-50 { background: #EFF6FF; }
        .bg-green-50 { background: #D1FAE5; }
        .bg-red-50 { background: #FEE2E2; }
        .bg-purple-50 { background: #F5F3FF; }
        
        [data-theme="dark"] .bg-blue-50 { background: #1E3A5F; }
        [data-theme="dark"] .bg-green-50 { background: #1A3A2A; }
        [data-theme="dark"] .bg-red-50 { background: #3A1A1A; }
        [data-theme="dark"] .bg-purple-50 { background: #2D1B4E; }
        
        .mt-4 { margin-top: 16px; }
        .mb-4 { margin-bottom: 16px; }
        .mr-2 { margin-right: 8px; }
        .ml-auto { margin-left: auto; }
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .form-card { padding: 16px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .grid-cols-2 { grid-template-columns: 1fr; }
            .md\:grid-cols-3 { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .md\:grid-cols-3 { grid-template-columns: 1fr; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            color: white;
        }
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .form-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .page-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .header-badge, .role-badge-display {
                color: white !important;
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
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
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
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Edit Laboratory
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-microscope"></i>
                <strong><?= htmlspecialchars($lab['name'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-<?= ($lab['status'] ?? 'active') === 'active' ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= ucfirst($lab['status'] ?? 'Active') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-user-md"></i>
                    <?= ($lab['active_technicians'] ?? 0) ?> Active Technicians
                </span>
                <span class="header-badge" style="background:rgba(248,113,113,0.2);border-color:rgba(248,113,113,0.3);color:#F87171;">
                    <i class="fas fa-user-slash"></i>
                    <?= ($lab['inactive_technicians'] ?? 0) ?> Inactive
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="view_laboratory.php?id=<?= $lab_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="laboratories.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>" style="max-width:800px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- EDIT FORM -->
    <!-- ================================================================ -->
    <div class="form-card animate-fade-in-up">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-edit"></i>
            </div>
            <div>
                <h3 class="form-title">Edit Laboratory Details</h3>
                <p class="form-subtitle">Update laboratory branch information</p>
            </div>
        </div>
        
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="action" value="update_laboratory">
            
            <!-- Laboratory Name -->
            <div class="form-row">
                <label class="form-label">
                    <i class="fas fa-flask label-icon"></i> Laboratory Name <span class="required">*</span>
                </label>
                <input type="text" name="name" class="form-control" 
                       value="<?= htmlspecialchars($lab['name'] ?? '') ?>" 
                       placeholder="e.g. Dodoma Laboratory"
                       required>
            </div>
            
            <!-- Location -->
            <div class="form-row">
                <label class="form-label">
                    <i class="fas fa-map-marker-alt label-icon"></i> Location <span class="required">*</span>
                </label>
                <input type="text" name="location" class="form-control" 
                       value="<?= htmlspecialchars($lab['location'] ?? '') ?>" 
                       placeholder="e.g. Dodoma City, Tanzania"
                       required>
            </div>
            
            <!-- Phone -->
            <div class="form-row">
                <label class="form-label">
                    <i class="fas fa-phone label-icon"></i> Phone Number
                </label>
                <input type="text" name="phone" class="form-control" 
                       value="<?= htmlspecialchars($lab['phone'] ?? '') ?>" 
                       placeholder="e.g. +255 700 000 001">
            </div>
            
            <!-- Email -->
            <div class="form-row">
                <label class="form-label">
                    <i class="fas fa-envelope label-icon"></i> Email Address
                </label>
                <input type="email" name="email" class="form-control" 
                       value="<?= htmlspecialchars($lab['email'] ?? '') ?>" 
                       placeholder="e.g. lab@braick.com">
            </div>
            
            <!-- Logo URL -->
            <div class="form-row">
                <label class="form-label">
                    <i class="fas fa-image label-icon"></i> Logo URL
                </label>
                <input type="text" name="logo" class="form-control" 
                       value="<?= htmlspecialchars($lab['logo'] ?? '') ?>" 
                       placeholder="e.g. /path/to/logo.png">
                <small class="text-gray-400 text-xs">Optional - URL to laboratory logo</small>
            </div>
            
            <!-- Status -->
            <div class="form-row">
                <label class="form-label">
                    <i class="fas fa-toggle-on label-icon"></i> Status <span class="required">*</span>
                </label>
                <select name="status" class="form-control" required>
                    <option value="active" <?= ($lab['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>
                        ✅ Active
                    </option>
                    <option value="inactive" <?= ($lab['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>
                        ❌ Inactive
                    </option>
                </select>
                <small class="text-gray-400 text-xs">Active laboratories can receive and process lab tests</small>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Laboratory
                </button>
                <a href="view_laboratory.php?id=<?= $lab_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()" style="margin-left:auto;">
                    <i class="fas fa-trash"></i> Delete Laboratory
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- LABORATORY STATISTICS -->
    <!-- ================================================================ -->
    <div class="form-card animate-fade-in-up" style="animation-delay:0.1s;">
        <h3 class="text-lg font-semibold text-primary mb-4">
            <i class="fas fa-chart-bar mr-2"></i> Laboratory Statistics
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <p class="text-2xl font-bold text-blue-600"><?= number_format($lab['total_technicians'] ?? 0) ?></p>
                <p class="text-sm text-gray-500">Total Technicians</p>
            </div>
            <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <p class="text-2xl font-bold text-green-600"><?= number_format($lab['active_technicians'] ?? 0) ?></p>
                <p class="text-sm text-gray-500">Active Technicians</p>
            </div>
            <div class="text-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                <p class="text-2xl font-bold text-red-600"><?= number_format($lab['inactive_technicians'] ?? 0) ?></p>
                <p class="text-sm text-gray-500">Inactive Technicians</p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Edit Laboratory - <?= htmlspecialchars($lab['name'] ?? 'N/A') ?>
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
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

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

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('branch_id');
        window.location.href = url.toString();
    }

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

    // ================================================================
    // CONFIRM DELETE
    // ================================================================
    function confirmDelete() {
        var confirmed = confirm(
            '⚠️ Are you sure you want to delete this laboratory?\n\n' +
            'Laboratory: <?= htmlspecialchars($lab['name'] ?? 'N/A') ?>\n' +
            'ID: #<?= $lab_id ?>\n\n' +
            'This action cannot be undone. All associated data will be affected.'
        );
        
        if (confirmed) {
            window.location.href = 'delete_laboratory.php?id=<?= $lab_id ?>&branch=<?= urlencode($selected_branch_id) ?>&confirm=yes';
        }
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
    // FORM VALIDATION
    // ================================================================
    document.getElementById('editForm')?.addEventListener('submit', function(e) {
        var name = document.querySelector('input[name="name"]').value.trim();
        var location = document.querySelector('input[name="location"]').value.trim();
        var email = document.querySelector('input[name="email"]').value.trim();
        var isValid = true;
        
        document.querySelectorAll('.form-control').forEach(function(el) {
            el.classList.remove('is-invalid');
            el.classList.remove('is-valid');
        });
        
        if (!name) {
            document.querySelector('input[name="name"]').classList.add('is-invalid');
            isValid = false;
        } else {
            document.querySelector('input[name="name"]').classList.add('is-valid');
        }
        
        if (!location) {
            document.querySelector('input[name="location"]').classList.add('is-invalid');
            isValid = false;
        } else {
            document.querySelector('input[name="location"]').classList.add('is-valid');
        }
        
        if (email && !isValidEmail(email)) {
            document.querySelector('input[name="email"]').classList.add('is-invalid');
            isValid = false;
        } else if (email) {
            document.querySelector('input[name="email"]').classList.add('is-valid');
        }
        
        if (!isValid) {
            e.preventDefault();
            var firstError = document.querySelector('.is-invalid');
            if (firstError) {
                firstError.focus();
            }
            showToast('⚠️ Validation Error', 'Please fill in all required fields correctly', 'warning');
        }
    });
    
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    // ================================================================
    // REAL-TIME VALIDATION
    // ================================================================
    document.querySelector('input[name="name"]')?.addEventListener('blur', function() {
        if (this.value.trim()) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
        }
    });
    
    document.querySelector('input[name="location"]')?.addEventListener('blur', function() {
        if (this.value.trim()) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
        }
    });
    
    document.querySelector('input[name="email"]')?.addEventListener('blur', function() {
        var val = this.value.trim();
        if (val && !isValidEmail(val)) {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
        } else if (val) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-invalid');
            this.classList.remove('is-valid');
        }
    });

    console.log('%c🧪 Braick Dispensary - Edit Laboratory', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔬 Laboratory: <?= htmlspecialchars($lab['name'] ?? 'N/A') ?> (ID: <?= $lab_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c📋 Status: <?= ucfirst($lab['status'] ?? 'Active') ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c👥 Technicians: <?= ($lab['total_technicians'] ?? 0) ?> total, <?= ($lab['active_technicians'] ?? 0) ?> active', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Using tables: branches, users, activity_logs', 'font-size:13px; color:#34D399;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>