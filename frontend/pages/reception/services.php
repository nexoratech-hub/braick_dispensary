<?php
// ================================================================
// FILE: frontend/pages/reception/services.php
// SERVICES MANAGEMENT - ADD ONLY WITH VIEW BUTTON
// AUTO BRANCH ASSIGNMENT - NEWEST FIRST
// WITH LOGIN PROTECTION
// WITH MONEY FORMAT (1,000,000,000)
// BRAICK DISPENSARY
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
// CHECK IF USER HAS ACCESS (Reception or Admin)
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'reception';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? '';
$user_email = $_SESSION['email'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// DATABASE CONNECTION
// ================================================================
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET ALL CATEGORIES FOR DROPDOWN
// ================================================================
$categories = [];
try {
    $stmt = $db->prepare("SELECT id, category_name FROM service_categories ORDER BY category_name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// ================================================================
// HANDLE ADD SERVICE FORM
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_service') {
        $service_name = trim($_POST['service_name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $branch_id = $user_branch_id;
        // Remove commas from price before saving
        $price_raw = str_replace(',', '', $_POST['price'] ?? '0');
        $price = (float)$price_raw;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($service_name)) {
            $message = "❌ Service name is required";
            $message_type = 'error';
        } elseif ($price < 0) {
            $message = "❌ Price cannot be negative";
            $message_type = 'error';
        } elseif ($category_id <= 0) {
            $message = "❌ Please select a category";
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO services (
                        service_name, category_id, description, branch_id, 
                        price, is_active, created_by, 
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $service_name, $category_id, $description, $branch_id,
                    $price, $is_active, $user_id
                ]);
                
                $message = "✅ Service added successfully to your branch!";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// GET SERVICE DETAILS FOR VIEW MODAL
// ================================================================
$view_service = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $view_id = (int)$_GET['view'];
    try {
        $stmt = $db->prepare("
            SELECT s.*, 
                   c.category_name,
                   b.name as branch_name,
                   u.full_name as created_by_name
            FROM services s
            LEFT JOIN service_categories c ON s.category_id = c.id
            LEFT JOIN branches b ON s.branch_id = b.id
            LEFT JOIN users u ON s.created_by = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$view_id]);
        $view_service = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $view_service = null;
    }
}

// ================================================================
// GET ALL SERVICES WITH CATEGORY NAMES (Filtered by user's branch)
// ORDER BY NEWEST FIRST (created_at DESC)
// ================================================================
$services = [];
try {
    $stmt = $db->prepare("
        SELECT s.*, 
               c.category_name,
               b.name as branch_name,
               u.full_name as created_by_name
        FROM services s
        LEFT JOIN service_categories c ON s.category_id = c.id
        LEFT JOIN branches b ON s.branch_id = b.id
        LEFT JOIN users u ON s.created_by = u.id
        WHERE s.branch_id = ? OR s.branch_id IS NULL
        ORDER BY s.created_at DESC, s.id DESC
    ");
    $stmt->execute([$user_branch_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $services = [];
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// GET BRANCH NAME
// ================================================================
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$user_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $user_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $user_branch_name = 'Branch';
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/reception_header.php';
include_once __DIR__ . '/../../components/reception_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Management - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
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
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
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
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
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
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
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
            position: relative;
            z-index: 1;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .btn-outline-light-scroll {
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
            position: relative;
            z-index: 1;
            cursor: pointer;
            background: transparent;
        }
        
        .btn-outline-light-scroll:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           CARDS
           ================================================================ */
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
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title i {
            margin-right: 8px;
        }
        .title-blue { color: var(--primary); }
        .title-green { color: var(--success); }
        
        /* ================================================================
           TABLE - BLUE HEADER
           ================================================================ */
        .table-container {
            overflow-x: auto;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 650px;
        }
        
        .table-container thead {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: #ffffff;
        }
        
        .table-container thead th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            border-bottom: 3px solid #0A4CA8;
        }
        
        .table-container thead th i {
            margin-right: 6px;
            opacity: 0.8;
        }
        
        .table-container tbody tr {
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }
        
        .table-container tbody tr:last-child {
            border-bottom: none;
        }
        
        .table-container tbody tr:hover {
            background: var(--primary-bg);
        }
        
        [data-theme="dark"] .table-container tbody tr:hover {
            background: #1E3A5F;
        }
        
        .table-container tbody td {
            padding: 12px 16px;
            vertical-align: middle;
            color: var(--text-primary);
        }
        
        .table-container .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .table-container .status-badge.active {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .table-container .status-badge.inactive {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        .table-container .price-display {
            font-weight: 600;
            color: var(--primary);
        }
        
        [data-theme="dark"] .table-container .price-display {
            color: var(--primary-light);
        }
        
        .table-container .action-btns {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            text-decoration: none;
        }
        
        .btn-icon:hover {
            transform: scale(1.1);
        }
        
        .btn-icon.view {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .btn-icon.view:hover {
            background: var(--primary);
            color: white;
        }
        
        /* ================================================================
           FORM - WITH MONEY FORMAT
           ================================================================ */
        .form-group {
            margin-bottom: 14px;
        }
        
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .form-label .required {
            color: var(--danger);
            margin-left: 2px;
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
        
        .form-control.price-input {
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .form-control.price-input:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 60px;
        }
        
        select.form-control {
            appearance: auto;
            cursor: pointer;
        }
        
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        
        .branch-info {
            background: var(--primary-bg);
            padding: 10px 16px;
            border-radius: var(--radius);
            border: 1px solid var(--primary-light);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            color: var(--primary);
        }
        
        [data-theme="dark"] .branch-info {
            background: #1E3A5F;
            border-color: var(--primary);
            color: var(--primary-light);
        }
        
        /* Price display preview */
        .price-preview {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .price-preview .formatted-price {
            font-weight: 700;
            color: var(--success);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            background: var(--success-bg);
            padding: 2px 12px;
            border-radius: 4px;
        }
        
        [data-theme="dark"] .price-preview .formatted-price {
            background: #1A3A2A;
            color: #34D399;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
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
        
        .btn-sm {
            padding: 4px 12px;
            font-size: 0.7rem;
        }
        
        /* ================================================================
           ALERT
           ================================================================ */
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
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
        
        /* ================================================================
           STATS ROW
           ================================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-item {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            text-align: center;
        }
        
        [data-theme="dark"] .stat-item {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .stat-item .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-item .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .stat-item .stat-number.active {
            color: var(--success);
        }
        
        .stat-item .stat-number.inactive {
            color: var(--danger);
        }
        
        /* ================================================================
           MODAL / VIEW SERVICE
           ================================================================ */
        .modal-overlay {
            display: <?= $view_service ? 'flex' : 'none' ?>;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            padding: 30px 35px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        [data-theme="dark"] .modal-content {
            background: var(--gray-800);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .modal-header h2 i {
            color: var(--primary);
        }
        
        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--danger-bg);
            color: var(--danger);
            cursor: pointer;
            font-size: 1rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            background: var(--danger);
            color: white;
            transform: rotate(90deg);
        }
        
        .modal-body .detail-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-body .detail-row:last-child {
            border-bottom: none;
        }
        
        .modal-body .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 140px;
            flex-shrink: 0;
            font-size: 0.85rem;
        }
        
        .modal-body .detail-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        
        .modal-body .detail-value .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .modal-body .detail-value .status-badge.active {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .modal-body .detail-value .status-badge.inactive {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        /* ================================================================
           NEW TAG
           ================================================================ */
        .new-tag {
            display: inline-block;
            background: var(--success);
            color: white;
            padding: 1px 10px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 700;
            margin-left: 6px;
            animation: pulse-new 2s infinite;
            text-transform: uppercase;
        }
        
        @keyframes pulse-new {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--gray-300);
            display: block;
            margin-bottom: 12px;
        }
        
        [data-theme="dark"] .empty-state i {
            color: var(--gray-600);
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
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
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .form-row-2 { grid-template-columns: 1fr; }
            .modal-content { padding: 20px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .card { padding: 14px 16px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .table-container table { font-size: 0.75rem; }
            .table-container thead th, .table-container tbody td { padding: 8px 10px; }
            .modal-body .detail-row { flex-direction: column; }
            .modal-body .detail-label { width: 100%; margin-bottom: 4px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .card { padding: 10px 12px; }
            .stats-row { grid-template-columns: 1fr; }
            .modal-content { padding: 15px; }
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
        
        [data-theme="dark"] .footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
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
            <input type="text" id="searchInput" placeholder="Search services..." onkeyup="filterTable()">
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
        <a href="../notifications.php" class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </a>
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
                <i class="fas fa-concierge-bell"></i>
                Services Management
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-list"></i>
                Manage services for <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <span class="separator">|</span>
                Total: <strong><?= count($services) ?></strong> services
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="scrollToForm()" class="btn-outline-light-scroll">
                <i class="fas fa-plus"></i> Add Service
            </button>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="stats-row">
        <div class="stat-item">
            <div class="stat-number"><?= count($services) ?></div>
            <div class="stat-label">Total Services</div>
        </div>
        <div class="stat-item">
            <div class="stat-number active"><?= count(array_filter($services, function($s) { return $s['is_active'] == 1; })) ?></div>
            <div class="stat-label">Active Services</div>
        </div>
        <div class="stat-item">
            <div class="stat-number inactive"><?= count(array_filter($services, function($s) { return $s['is_active'] == 0; })) ?></div>
            <div class="stat-label">Inactive Services</div>
        </div>
        <div class="stat-item">
            <?php 
            $total_value = array_sum(array_column($services, 'price'));
            ?>
            <div class="stat-number">TSh <?= number_format($total_value, 0) ?></div>
            <div class="stat-label">Total Value</div>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ADD SERVICE FORM - WITH MONEY FORMAT -->
    <!-- ================================================================ -->
    <div id="serviceForm" class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-plus title-blue"></i>
                Add New Service
            </h3>
            <span class="text-sm text-gray-400">Fill in the details below</span>
        </div>
        
        <div class="branch-info">
            <i class="fas fa-store"></i>
            <strong>Branch:</strong> <?= htmlspecialchars($user_branch_name) ?>
            <span class="text-xs text-gray-500">(Auto-assigned to your branch)</span>
        </div>
        
        <form method="POST" action="" style="margin-top:16px;" id="serviceForm">
            <input type="hidden" name="action" value="add_service">
            
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Service Name <span class="required">*</span></label>
                    <input type="text" name="service_name" class="form-control" 
                           placeholder="Enter service name..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Category <span class="required">*</span></label>
                    <select name="category_id" class="form-control" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>">
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2" 
                          placeholder="Service description..."></textarea>
            </div>
            
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Price (TSh) <span class="required">*</span></label>
                    <input type="text" name="price" class="form-control price-input" 
                           id="priceInput" placeholder="e.g. 1,000,000" 
                           value="0" required
                           oninput="formatPriceInput(this)">
                    <div class="price-preview">
                        <span>Formatted:</span>
                        <span class="formatted-price" id="pricePreview">TSh 0</span>
                    </div>
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
                    <label class="form-label" style="margin-bottom:0;display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <span>Active</span>
                    </label>
                </div>
            </div>
            
            <div class="mt-3 flex flex-wrap gap-3">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Add Service
                </button>
                <button type="reset" class="btn btn-outline" onclick="resetPriceInput()">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- SERVICES TABLE - BLUE HEADER (NEWEST FIRST) -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-table title-blue"></i>
                All Services
                <span class="text-sm font-normal text-gray-400" id="serviceCount">(<?= count($services) ?> services)</span>
                <span class="text-xs text-gray-400">(Newest first)</span>
            </h3>
            <div class="flex gap-2 flex-wrap">
                <button onclick="exportServices()" class="btn btn-outline btn-sm">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
        
        <div class="table-container">
            <table id="servicesTable">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> ID</th>
                        <th><i class="fas fa-tag"></i> Service Name</th>
                        <th><i class="fas fa-folder"></i> Category</th>
                        <th><i class="fas fa-money-bill"></i> Price</th>
                        <th><i class="fas fa-calendar-plus"></i> Added</th>
                        <th><i class="fas fa-toggle-on"></i> Status</th>
                        <th><i class="fas fa-eye"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($services) > 0): ?>
                        <?php foreach ($services as $service): 
                            $is_new = (strtotime($service['created_at']) > strtotime('-7 days'));
                        ?>
                            <tr>
                                <td><span class="font-mono text-xs"><?= $service['id'] ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($service['service_name']) ?></strong>
                                    <?php if ($is_new): ?>
                                        <span class="new-tag">New</span>
                                    <?php endif; ?>
                                    <?php if (!empty($service['description'])): ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars(substr($service['description'], 0, 50)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge" style="background:var(--primary-bg);color:var(--primary);padding:2px 12px;border-radius:12px;font-size:0.65rem;">
                                        <?= htmlspecialchars($service['category_name'] ?? 'Uncategorized') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="price-display">TSh <?= number_format($service['price'] ?? 0, 0) ?></span>
                                </td>
                                <td>
                                    <span class="text-xs"><?= date('M d, Y', strtotime($service['created_at'] ?? 'now')) ?></span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $service['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $service['is_active'] ? '✅ Active' : '❌ Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="?view=<?= $service['id'] ?>" class="btn-icon view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-concierge-bell"></i>
                                    <p>No services found</p>
                                    <p class="text-sm text-gray-400">Click "Add Service" to create one</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VIEW SERVICE MODAL -->
    <!-- ================================================================ -->
    <?php if ($view_service): ?>
    <div class="modal-overlay" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-eye"></i>
                    Service Details
                </h2>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="detail-row">
                    <span class="detail-label">ID</span>
                    <span class="detail-value">#<?= $view_service['id'] ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Service Name</span>
                    <span class="detail-value"><strong><?= htmlspecialchars($view_service['service_name']) ?></strong></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Category</span>
                    <span class="detail-value"><?= htmlspecialchars($view_service['category_name'] ?? 'Uncategorized') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Description</span>
                    <span class="detail-value"><?= nl2br(htmlspecialchars($view_service['description'] ?? 'No description')) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Price</span>
                    <span class="detail-value"><strong>TSh <?= number_format($view_service['price'] ?? 0, 0) ?></strong></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Branch</span>
                    <span class="detail-value"><?= htmlspecialchars($view_service['branch_name'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">
                        <span class="status-badge <?= $view_service['is_active'] ? 'active' : 'inactive' ?>">
                            <?= $view_service['is_active'] ? '✅ Active' : '❌ Inactive' ?>
                        </span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Created By</span>
                    <span class="detail-value"><?= htmlspecialchars($view_service['created_by_name'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Created At</span>
                    <span class="detail-value"><?= date('M d, Y h:i A', strtotime($view_service['created_at'] ?? 'now')) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Updated At</span>
                    <span class="detail-value"><?= date('M d, Y h:i A', strtotime($view_service['updated_at'] ?? 'now')) ?></span>
                </div>
            </div>
            <div class="mt-4 text-center">
                <button class="btn btn-primary" onclick="closeModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Services Management
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT - MONEY FORMAT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // MONEY FORMAT - Format price with commas (1,000,000,000)
    // ================================================================
    function formatPriceInput(input) {
        // Remove all non-digit characters
        var raw = input.value.replace(/[^0-9]/g, '');
        
        if (raw === '') {
            input.value = '';
            document.getElementById('pricePreview').textContent = 'TSh 0';
            return;
        }
        
        // Format with commas
        var formatted = parseInt(raw).toLocaleString('en-US');
        input.value = formatted;
        
        // Update preview
        document.getElementById('pricePreview').textContent = 'TSh ' + formatted;
    }
    
    // ================================================================
    // RESET PRICE INPUT
    // ================================================================
    function resetPriceInput() {
        var input = document.getElementById('priceInput');
        if (input) {
            input.value = '0';
            document.getElementById('pricePreview').textContent = 'TSh 0';
        }
    }
    
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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SCROLL TO FORM
    // ================================================================
    function scrollToForm() {
        var form = document.getElementById('serviceForm');
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        var firstInput = form.querySelector('input[name="service_name"]');
        if (firstInput) {
            setTimeout(function() { firstInput.focus(); }, 500);
        }
    }

    // ================================================================
    // CLOSE MODAL
    // ================================================================
    function closeModal() {
        var modal = document.getElementById('viewModal');
        if (modal) {
            modal.style.display = 'none';
        }
        var url = new URL(window.location.href);
        url.searchParams.delete('view');
        window.history.replaceState({}, document.title, url);
    }

    // ================================================================
    // TABLE SEARCH/FILTER
    // ================================================================
    function filterTable() {
        var input = document.getElementById('searchInput');
        var filter = input.value.toLowerCase();
        var table = document.getElementById('servicesTable');
        var rows = table.getElementsByTagName('tr');
        var visibleCount = 0;
        
        for (var i = 1; i < rows.length; i++) {
            var cells = rows[i].getElementsByTagName('td');
            var found = false;
            for (var j = 0; j < cells.length; j++) {
                var text = cells[j].textContent || cells[j].innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
            rows[i].style.display = found ? '' : 'none';
            if (found) visibleCount++;
        }
        
        var countEl = document.getElementById('serviceCount');
        if (countEl) {
            countEl.textContent = '(' + visibleCount + ' services)';
        }
    }

    // ================================================================
    // EXPORT SERVICES
    // ================================================================
    function exportServices() {
        var table = document.getElementById('servicesTable');
        var rows = table.getElementsByTagName('tr');
        var csv = [];
        
        var headers = ['ID', 'Service Name', 'Category', 'Price (TSh)', 'Date Added', 'Status'];
        csv.push(headers.join(','));
        
        for (var i = 1; i < rows.length; i++) {
            var row = rows[i];
            var cells = row.getElementsByTagName('td');
            if (row.style.display === 'none') continue;
            
            var rowData = [];
            for (var j = 0; j < 6 && j < cells.length; j++) {
                var text = cells[j].textContent || cells[j].innerText;
                text = text.replace(/,/g, ';').trim();
                rowData.push('"' + text + '"');
            }
            csv.push(rowData.join(','));
        }
        
        var blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'services_export_' + new Date().toISOString().slice(0,10) + '.csv';
        a.click();
        URL.revokeObjectURL(url);
        
        showToast('Export', 'Services exported successfully!', 'success');
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
    
    <?php if ($message && $message_type): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? 'Success' : ($message_type === 'warning' ? 'Warning' : 'Error') ?>', 
                '<?= addslashes($message) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    // Close modal on background click
    document.addEventListener('click', function(e) {
        var modal = document.getElementById('viewModal');
        if (modal && e.target === modal) {
            closeModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    console.log('%c🛠️ Services Management (With Login Protection)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Services: <?= count($services) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Money Format: 1,000,000,000 with commas', 'font-size:13px; color:#7C3AED;');
    console.log('%c💵 Price input auto-formats with commas', 'font-size:13px; color:#34D399;');
    console.log('%c🔒 Login protection: Active', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>