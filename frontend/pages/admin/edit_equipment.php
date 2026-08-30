<?php
// ================================================================
// FILE: frontend/pages/admin/edit_equipment.php
// ADMIN - EDIT EQUIPMENT
// EDIT ALL EQUIPMENT DETAILS
// BRAICK DISPENSARY - BLUE THEME
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
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

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$equipment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$return_branch = isset($_GET['branch']) ? $_GET['branch'] : 'all';

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
// GET EQUIPMENT DETAILS
// ================================================================
$equipment = null;
$error_message = '';

try {
    $stmt = $db->prepare("
        SELECT 
            e.*,
            b.name as branch_name
        FROM medical_equipment e
        LEFT JOIN branches b ON e.branch_id = b.id
        WHERE e.id = ?
    ");
    $stmt->execute([$equipment_id]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$equipment) {
        $error_message = "Equipment not found. It may have been deleted.";
    }
} catch (Exception $e) {
    $error_message = "Error loading equipment: " . $e->getMessage();
    $equipment = null;
}

// ================================================================
// GET BRANCHES FOR DROPDOWN
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// GET SERVICE CATEGORIES FOR DROPDOWN
// ================================================================
$categories = [];
try {
    $stmt = $db->query("SELECT id, category_name FROM service_categories ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// ================================================================
// PROCESS FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_equipment') {
    $equipment_id = (int)($_POST['equipment_id'] ?? 0);
    $equipment_name = trim($_POST['equipment_name'] ?? '');
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $category_name = trim($_POST['category_name'] ?? '');
    $unit = trim($_POST['unit'] ?? 'pcs');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reorder_level = (int)($_POST['reorder_level'] ?? 5);
    $selling_price = isset($_POST['selling_price']) ? (float)str_replace(',', '', $_POST['selling_price']) : 0;
    $supplier = trim($_POST['supplier'] ?? '');
    $expiry_date = $_POST['expiry_date'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $branch_id = isset($_POST['branch_id']) ? $_POST['branch_id'] : null;
    
    if ($branch_id === 'all' || $branch_id === '' || $branch_id === 'NULL') {
        $branch_id = null;
    } elseif (is_numeric($branch_id)) {
        $branch_id = (int)$branch_id;
    } else {
        $branch_id = null;
    }
    
    // Determine category
    $final_category = '';
    if ($category_id > 0) {
        foreach ($categories as $cat) {
            if ($cat['id'] == $category_id) {
                $final_category = $cat['category_name'];
                break;
            }
        }
    } elseif (!empty($category_name)) {
        $final_category = $category_name;
    }
    
    // Validate
    $errors = [];
    if (empty($equipment_name)) { $errors[] = 'Equipment name is required'; }
    if ($quantity < 0) { $errors[] = 'Quantity cannot be negative'; }
    if ($selling_price < 0) { $errors[] = 'Selling price cannot be negative'; }
    if (!empty($expiry_date) && strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Expiry date cannot be in the past';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE medical_equipment 
                SET 
                    equipment_name = ?,
                    category = ?,
                    unit = ?,
                    quantity = ?,
                    reorder_level = ?,
                    selling_price = ?,
                    supplier = ?,
                    expiry_date = ?,
                    status = ?,
                    branch_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $equipment_name,
                $final_category,
                $unit,
                $quantity,
                $reorder_level,
                $selling_price,
                $supplier,
                $expiry_date ?: null,
                $status,
                $branch_id,
                $equipment_id
            ]);
            
            $message = "✅ Equipment updated successfully!";
            $message_type = 'success';
            
            // Refresh equipment data
            $stmt = $db->prepare("
                SELECT 
                    e.*,
                    b.name as branch_name
                FROM medical_equipment e
                LEFT JOIN branches b ON e.branch_id = b.id
                WHERE e.id = ?
            ");
            $stmt->execute([$equipment_id]);
            $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
            
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
// HELPER FUNCTIONS
// ================================================================
function getStockStatus($quantity, $reorder_level) {
    if ($quantity <= 0) return ['class' => 'out', 'label' => 'Out of Stock'];
    if ($quantity <= $reorder_level) return ['class' => 'low', 'label' => 'Low Stock'];
    return ['class' => 'ok', 'label' => 'In Stock'];
}

function getExpiryStatus($expiry_date) {
    if (empty($expiry_date) || $expiry_date === '0000-00-00') {
        return ['class' => 'no-expiry', 'label' => 'No Expiry', 'days' => null];
    }
    $days = floor((strtotime($expiry_date) - time()) / 86400);
    if ($days < 0) return ['class' => 'expired', 'label' => 'Expired', 'days' => $days];
    if ($days <= 30) return ['class' => 'expiring', 'label' => 'Expiring Soon', 'days' => $days];
    return ['class' => 'valid', 'label' => 'Valid', 'days' => $days];
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
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Equipment - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    
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
            --teal: #0D9488;
            --teal-bg: #ECFDF5;
            
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
            
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-bg: #1E3A5F;
            --primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
            --primary-gradient-strong: linear-gradient(135deg, #1D4ED8, #1E40AF);
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
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
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
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
            background: var(--primary-gradient);
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
        
        .top-nav .datetime i {
            color: var(--primary-light);
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER - BLUE BACKGROUND
           ================================================================ */
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
        }
        
        .page-header .btn-outline-light:hover {
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
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
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
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-title i { color: var(--primary); }
        
        /* ================================================================
           FORM
           ================================================================ */
        .form-group {
            margin-bottom: 16px;
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
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
        }
        
        .form-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        select.form-control {
            appearance: auto;
            cursor: pointer;
        }
        
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
        }
        
        .form-help {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .form-help i { font-size: 0.6rem; }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .status-badge.active { background: var(--success-bg); color: var(--success); }
        .status-badge.inactive { background: var(--danger-bg); color: var(--danger); }
        
        .stock-badge {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .stock-badge.ok { background: var(--success-bg); color: var(--success); }
        .stock-badge.low { background: var(--warning-bg); color: var(--warning); }
        .stock-badge.out { background: var(--danger-bg); color: var(--danger); }
        
        .expiry-badge {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .expiry-badge.valid { background: var(--success-bg); color: var(--success); }
        .expiry-badge.expiring { background: var(--warning-bg); color: var(--warning); }
        .expiry-badge.expired { background: var(--danger-bg); color: var(--danger); }
        .expiry-badge.no-expiry { background: var(--gray-200); color: var(--gray-500); }
        
        .branch-tag {
            display: inline-block;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 1px 10px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .branch-tag.all-branches {
            background: #FEF3C7;
            color: #D97706;
        }
        
        [data-theme="dark"] .branch-tag.all-branches {
            background: #3D2E0A;
            color: #FBBF24;
        }
        
        .batch-number {
            font-family: monospace;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 4px;
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        [data-theme="dark"] .batch-number {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .price-input {
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
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
            transition: all 0.3s ease;
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
        
        .btn-danger {
            background: var(--danger);
            color: white;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.2);
        }
        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.3);
        }
        
        .btn-sm { padding: 4px 12px; font-size: 0.7rem; }
        
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
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 700;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
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
            .form-row-2, .form-row-3 { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .card { padding: 14px 16px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
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
            <input type="text" id="searchInput" placeholder="Search equipment..." value="">
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

    <?php if ($equipment && empty($error_message)): 
        $stock = getStockStatus($equipment['quantity'], $equipment['reorder_level']);
        $expiry = getExpiryStatus($equipment['expiry_date']);
    ?>
        <!-- ================================================================ -->
        <!-- PAGE HEADER -->
        <!-- ================================================================ -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-edit"></i>
                    Edit Equipment
                    <span class="role-badge-display">ADMIN</span>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-hashtag"></i> #<?= $equipment['id'] ?>
                    </span>
                </h1>
                <p class="page-subtitle">
                    <i class="fas fa-tools"></i>
                    Editing <strong><?= htmlspecialchars($equipment['equipment_name']) ?></strong>
                    <span class="header-badge">
                        <i class="fas fa-boxes"></i> <?= number_format($equipment['quantity']) ?> in stock
                    </span>
                    <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                        <i class="fas fa-barcode"></i> <?= htmlspecialchars($equipment['batch_number']) ?>
                    </span>
                </p>
            </div>
            <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
                <a href="view_equipment.php?id=<?= $equipment['id'] ?>&branch=<?= urlencode($return_branch) ?>" class="btn-outline-light">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- MESSAGE -->
        <!-- ================================================================ -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- EDIT FORM -->
        <!-- ================================================================ -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-pen"></i> Edit Equipment Details
                </h3>
                <div>
                    <span class="status-badge <?= getStatusBadge($equipment['status']) ?>">
                        <?= getStatusLabel($equipment['status']) ?>
                    </span>
                    <span class="stock-badge <?= $stock['class'] ?>" style="margin-left:8px;">
                        <i class="fas <?= $stock['class'] === 'ok' ? 'fa-check-circle' : ($stock['class'] === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                        <?= $stock['label'] ?>
                    </span>
                </div>
            </div>
            
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="action" value="update_equipment">
                <input type="hidden" name="equipment_id" value="<?= $equipment['id'] ?>">
                
                <!-- Branch -->
                <div class="form-group">
                    <label class="form-label">Branch <span class="required">*</span></label>
                    <select name="branch_id" class="form-control" required>
                        <option value="">-- Select Branch --</option>
                        <option value="all" <?= $equipment['branch_id'] === null ? 'selected' : '' ?>>🌐 All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $equipment['branch_id'] == $b['id'] ? 'selected' : '' ?>>
                                🏥 <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-help">
                        <i class="fas fa-info-circle"></i> Select "All Branches" if this equipment is available in all branches
                    </div>
                </div>
                
                <!-- Equipment Name -->
                <div class="form-group">
                    <label class="form-label">Equipment Name <span class="required">*</span></label>
                    <input type="text" name="equipment_name" class="form-control" value="<?= htmlspecialchars($equipment['equipment_name']) ?>" required>
                </div>
                
                <!-- Category -->
                <div class="form-group">
                    <label class="form-label">Category <span class="required">*</span></label>
                    <select name="category_id" class="form-control" id="categorySelect">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $equipment['category'] == $cat['category_name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="0">-- Other (Type manually) --</option>
                    </select>
                    <input type="text" name="category_name" class="form-control" style="margin-top:6px;display:none;" id="categoryManual" placeholder="Enter custom category..." value="<?= htmlspecialchars($equipment['category'] ?? '') ?>">
                    <div class="form-help">
                        <i class="fas fa-info-circle"></i> Select existing category or type a new one
                    </div>
                </div>
                
                <!-- Unit & Quantity -->
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Unit <span class="required">*</span></label>
                        <select name="unit" class="form-control">
                            <option value="pcs" <?= $equipment['unit'] === 'pcs' ? 'selected' : '' ?>>Pieces (pcs)</option>
                            <option value="box" <?= $equipment['unit'] === 'box' ? 'selected' : '' ?>>Box</option>
                            <option value="pack" <?= $equipment['unit'] === 'pack' ? 'selected' : '' ?>>Pack</option>
                            <option value="set" <?= $equipment['unit'] === 'set' ? 'selected' : '' ?>>Set</option>
                            <option value="each" <?= $equipment['unit'] === 'each' ? 'selected' : '' ?>>Each</option>
                            <option value="roll" <?= $equipment['unit'] === 'roll' ? 'selected' : '' ?>>Roll</option>
                            <option value="bottle" <?= $equipment['unit'] === 'bottle' ? 'selected' : '' ?>>Bottle</option>
                            <option value="pair" <?= $equipment['unit'] === 'pair' ? 'selected' : '' ?>>Pair</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantity <span class="required">*</span></label>
                        <input type="number" name="quantity" class="form-control" required min="0" value="<?= $equipment['quantity'] ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reorder Level <span class="required">*</span></label>
                        <input type="number" name="reorder_level" class="form-control" required min="0" value="<?= $equipment['reorder_level'] ?>">
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i> Alert when stock falls below this number
                        </div>
                    </div>
                </div>
                
                <!-- Price & Supplier -->
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Selling Price (TSh)</label>
                        <input type="text" name="selling_price" class="form-control price-input" value="<?= number_format($equipment['selling_price'] ?? 0, 0) ?>" oninput="formatPriceInput(this)">
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i> Leave 0 for FREE equipment
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supplier</label>
                        <input type="text" name="supplier" class="form-control" value="<?= htmlspecialchars($equipment['supplier'] ?? '') ?>">
                    </div>
                </div>
                
                <!-- Batch Number (Read Only) -->
                <div class="form-group">
                    <label class="form-label">Batch Number</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($equipment['batch_number']) ?>" disabled>
                    <div class="form-help">
                        <i class="fas fa-info-circle"></i> Batch number cannot be changed
                    </div>
                </div>
                
                <!-- Expiry Date & Status -->
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control" value="<?= $equipment['expiry_date'] ?? '' ?>">
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i> Leave empty for no expiry (Active Forever)
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status <span class="required">*</span></label>
                        <select name="status" class="form-control">
                            <option value="active" <?= $equipment['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $equipment['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i> Inactive equipment will not appear in lab test equipment lists
                        </div>
                    </div>
                </div>
                
                <!-- Current Status Info -->
                <div style="background:var(--bg-body);border-radius:var(--radius);padding:12px 16px;margin-top:8px;display:flex;flex-wrap:wrap;gap:16px;">
                    <div>
                        <span style="font-size:0.6rem;color:var(--text-secondary);text-transform:uppercase;font-weight:600;">Current Stock</span>
                        <div style="font-weight:700;font-size:1.1rem;"><?= number_format($equipment['quantity']) ?> <?= $equipment['unit'] ?></div>
                    </div>
                    <div>
                        <span style="font-size:0.6rem;color:var(--text-secondary);text-transform:uppercase;font-weight:600;">Stock Status</span>
                        <div><span class="stock-badge <?= $stock['class'] ?>"><?= $stock['label'] ?></span></div>
                    </div>
                    <div>
                        <span style="font-size:0.6rem;color:var(--text-secondary);text-transform:uppercase;font-weight:600;">Expiry</span>
                        <div><span class="expiry-badge <?= $expiry['class'] ?>"><?= $expiry['label'] ?></span></div>
                    </div>
                    <div>
                        <span style="font-size:0.6rem;color:var(--text-secondary);text-transform:uppercase;font-weight:600;">Linked Lab Tests</span>
                        <div style="font-weight:700;font-size:1.1rem;">
                            <?php 
                                $stmt = $db->prepare("SELECT COUNT(*) FROM lab_test_equipment WHERE equipment_id = ?");
                                $stmt->execute([$equipment['id']]);
                                $linked_count = $stmt->fetchColumn();
                                echo $linked_count;
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div style="display:flex;gap:10px;margin-top:20px;border-top:2px solid var(--border-color);padding-top:16px;flex-wrap:wrap;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="view_equipment.php?id=<?= $equipment['id'] ?>&branch=<?= urlencode($return_branch) ?>" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </form>
        </div>

    <?php else: ?>
        <!-- ================================================================ -->
        <!-- EQUIPMENT NOT FOUND -->
        <!-- ================================================================ -->
        <div class="page-header" style="background:var(--danger);">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Equipment Not Found
                    <span class="role-badge-display">ERROR</span>
                </h1>
                <p class="page-subtitle">
                    <i class="fas fa-tools"></i>
                    The equipment you are trying to edit could not be found.
                </p>
            </div>
            <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
                <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
        
        <div class="card">
            <div class="empty-state" style="text-align:center;padding:40px 20px;color:var(--text-secondary);">
                <i class="fas fa-tools" style="font-size:3rem;color:var(--gray-300);display:block;margin-bottom:12px;"></i>
                <h3 style="font-size:1.2rem;color:var(--text-primary);margin-bottom:8px;">Equipment Not Found</h3>
                <p style="font-size:0.9rem;">The equipment with ID #<?= $equipment_id ?> could not be found in the system.</p>
                <p style="font-size:0.8rem;color:var(--text-secondary);margin-top:4px;">It may have been deleted or the ID may be incorrect.</p>
                <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn btn-primary" style="margin-top:16px;">
                    <i class="fas fa-arrow-left"></i> Back to Equipment List
                </a>
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
            Edit Equipment
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

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
    // SEARCH
    // ================================================================
    function performSearch() {
        var query = searchInput.value.trim();
        var branch = '<?= urlencode($return_branch) ?>';
        var url = 'equipment_inventory.php?branch=' + encodeURIComponent(branch);
        if (query.length > 0) {
            url += '&search=' + encodeURIComponent(query);
        }
        window.location.href = url;
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // CATEGORY - Toggle manual input
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var categorySelect = document.getElementById('categorySelect');
        var categoryManual = document.getElementById('categoryManual');
        
        if (categorySelect && categoryManual) {
            // Check if current value is custom (not in dropdown)
            var isCustom = true;
            var options = categorySelect.options;
            for (var i = 0; i < options.length; i++) {
                if (options[i].text === categoryManual.value) {
                    isCustom = false;
                    break;
                }
            }
            
            if (isCustom && categoryManual.value !== '') {
                categorySelect.value = '0';
                categoryManual.style.display = 'block';
            }
            
            categorySelect.addEventListener('change', function() {
                if (this.value === '0') {
                    categoryManual.style.display = 'block';
                    categoryManual.required = true;
                    categoryManual.focus();
                } else {
                    categoryManual.style.display = 'none';
                    categoryManual.required = false;
                    categoryManual.value = '';
                }
            });
        }
    });

    // ================================================================
    // FORMAT PRICE INPUT
    // ================================================================
    function formatPriceInput(input) {
        var raw = input.value.replace(/[^0-9]/g, '');
        if (raw === '') {
            input.value = '';
            return;
        }
        var formatted = parseInt(raw).toLocaleString('en-US');
        input.value = formatted;
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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🔧 Braick Dispensary - Edit Equipment', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    <?php if ($equipment): ?>
        console.log('%c📦 Editing: <?= htmlspecialchars($equipment['equipment_name']) ?> (ID: <?= $equipment['id'] ?>)', 'font-size:13px; color:#0B5ED7;');
        console.log('%c📊 Quantity: <?= $equipment['quantity'] ?> | Batch: <?= htmlspecialchars($equipment['batch_number']) ?>', 'font-size:13px; color:#D97706;');
    <?php else: ?>
        console.log('%c❌ Equipment not found (ID: <?= $equipment_id ?>)', 'font-size:13px; color:#DC2626;');
    <?php endif; ?>
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>