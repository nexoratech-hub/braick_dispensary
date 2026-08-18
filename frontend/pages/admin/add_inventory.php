<?php
// ================================================================
// FILE: frontend/pages/admin/add_inventory.php
// ADMIN - ADD INVENTORY ITEM (MATCHES PHARMACY STYLE)
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
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

$db = Database::getInstance()->getConnection();

// ================================================================
// GET BRANCH ID FROM URL
// ================================================================
$branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
$branch_name = '';

if ($branch_id > 0) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch) {
        $branch_name = $branch['name'];
    }
}

// If no branch specified or invalid, use user's branch
if ($branch_id <= 0 || empty($branch_name)) {
    $branch_id = $user_branch_id;
    $branch_name = $user_branch_name;
}

// ================================================================
// GET BRANCHES FOR SELECTOR
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    if (!empty($form_data['expiry_date']) && strtotime($form_data['expiry_date']) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Expiry date cannot be in the past';
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
                $branch_id,
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
                    $branch_id,
                    "Added new medicine: " . $form_data['medication_name'] . " (Batch: " . $form_data['batch_number'] . ") - " . $form_data['quantity'] . " units"
                ]);
            } catch (Exception $e) {
                // Silent fail
            }
            
            $message = "✅ Medicine added successfully! Batch: <strong>" . htmlspecialchars($form_data['batch_number']) . "</strong>";
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
                'status' => 'active'
            ];
            
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
    <title>Add Medicine - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-light: #D1FAE5;
            --warning: #D97706;
            --warning-light: #FEF3C7;
            --danger: #DC2626;
            --danger-light: #FEE2E2;
            --purple: #7C3AED;
            --purple-light: #EDE9FE;
            
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #94A3B8;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --border-color: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.4);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        /* ================================================================
           TOP NAV - SHARED HEADER
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
            border-radius: 10px;
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
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
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
        
        .notif-dot.has-notif { background: #EF4444; }
        .notif-dot.no-notif { background: #94A3B8; animation: none; }
        
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
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        .branch-selector-top {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .branch-selector-top:focus {
            border-color: var(--primary);
        }
        
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
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 24px rgba(11, 94, 215, 0.2);
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
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 1.8rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.15);
            padding: 6px 16px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           FORM CARD
           ================================================================ */
        .form-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 28px 32px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            max-width: 900px;
            margin: 0 auto;
        }
        
        .form-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }
        
        .form-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding-bottom: 16px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .form-header .form-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .form-header .form-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .form-header .form-subtitle {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .form-grid .full-width {
            grid-column: 1 / -1;
        }
        
        .form-row {
            margin-bottom: 0;
        }
        
        .form-row .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: block;
        }
        
        .form-row .form-label .required {
            color: var(--danger);
            margin-left: 2px;
        }
        
        .form-row .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.88rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-row .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
        }
        
        .form-row .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        .form-row .form-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .form-row select.form-control {
            appearance: auto;
            cursor: pointer;
        }
        
        .form-row .help-text {
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        .form-row .error-text {
            font-size: 0.7rem;
            color: var(--danger);
            margin-top: 2px;
            display: block;
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
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
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
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
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
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .batch-help-text {
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        /* ================================================================
           FORM ACTIONS
           ================================================================ */
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .btn-save {
            background: var(--success);
            color: white;
            padding: 10px 28px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }
        
        .btn-save:hover {
            background: var(--success-dark);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35);
        }
        
        .btn-cancel {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-cancel:hover {
            border-color: var(--danger);
            color: var(--danger);
            transform: translateY(-2px);
        }
        
        /* ================================================================
           MESSAGE BOX
           ================================================================ */
        .message-box {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.4s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-box.success {
            background: var(--success-light);
            color: #065F46;
            border: 2px solid #6EE7B7;
        }
        
        .message-box.error {
            background: var(--danger-light);
            color: #991B1B;
            border: 2px solid #FCA5A5;
        }
        
        .message-box i {
            font-size: 1.3rem;
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
            font-weight: 600;
        }
        
        /* ================================================================
           SIDEBAR FIX
           ================================================================ */
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
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 768px) {
            .main-content { padding: 12px; }
            .form-card { padding: 16px 18px; }
            .form-grid { grid-template-columns: 1fr; }
            .form-grid .full-width { grid-column: 1; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.2rem; }
            .category-input-group { flex-direction: column; }
            .category-input-group .btn-category-toggle { width: 100%; justify-content: center; }
            .batch-input-group { flex-direction: column; }
            .batch-input-group .btn-generate-batch { width: 100%; justify-content: center; }
            .form-actions { flex-direction: column; }
            .form-actions .btn-save,
            .form-actions .btn-cancel { width: 100%; justify-content: center; }
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .form-card { padding: 12px 14px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .form-header { flex-direction: column; text-align: center; }
            .form-header .form-icon { width: 40px; height: 40px; font-size: 1rem; }
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
<!-- SIDEBAR OVERLAY (Mobile) -->
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
            <option value="all" <?= $branch_id == 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?> style="background:#0B4EA8;color:white;padding:8px;">
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <nav style="padding:10px 8px 20px;">
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Main Menu</div>
        
        <a href="/dispensary_system/frontend/pages/admin/dashboard.php?branch=<?= $branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-home"></i> Dashboard
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/employees.php?branch=<?= $branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-users"></i> Employees
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/patients.php?branch=<?= $branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-user-injured"></i> Patients
        </a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Modules</div>
        
        <a href="/dispensary_system/frontend/pages/admin/doctors_list.php?branch=<?= $branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-user-md"></i> Doctors
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_pharmacy.php?branch=<?= $branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-prescription"></i> Pharmacy
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_reception.php?branch=<?= $branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-headset"></i> Reception
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_laboratory.php?branch=<?= $branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-flask"></i> Laboratory
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_cashier.php?branch=<?= $branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-cash-register"></i> Cashier
        </a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Services</div>
        
        <a href="/dispensary_system/frontend/pages/admin/services.php?branch=<?= $branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-concierge-bell"></i> Services
        </a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">Management</div>
        
        <a href="/dispensary_system/frontend/pages/admin/branches.php?branch=<?= $branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-store-alt"></i> Branches
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/departments.php?branch=<?= $branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-building"></i> Departments
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/reports.php?branch=<?= $branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-chart-bar"></i> Reports
        </a>
        
        <div style="font-size:0.5rem;text-transform:uppercase;letter-spacing:0.08em;color:#6EA8FE;padding:0 10px;margin:12px 0 4px;font-weight:700;">System</div>
        
        <a href="/dispensary_system/frontend/pages/admin/settings.php?branch=<?= $branch_id ?>" 
           style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#D2E3FC;text-decoration:none;transition:all 0.25s ease;font-size:0.8rem;font-weight:500;margin:1px 0;background:transparent;cursor:pointer;border:none;width:100%;text-align:left;position:relative;">
            <i style="width:20px;text-align:center;font-size:0.9rem;flex-shrink:0;" class="fas fa-cog"></i> Settings
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
<!-- TOP NAVIGATION - SHARED HEADER -->
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
        <span class="branch-badge" style="background:rgba(255,255,255,0.1);color:white;padding:4px 14px;border-radius:20px;font-size:0.75rem;border:1px solid rgba(255,255,255,0.15);backdrop-filter:blur(4px);">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
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
                <i class="fas fa-plus-circle"></i>
                Add Medicine
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-prescription-bottle"></i>
                Add new medicine to inventory
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="pharmacy_inventory.php?id=<?= $branch_id ?>&branch=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?>" style="max-width:900px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ADD MEDICINE FORM - MATCHES PHARMACY STYLE -->
    <!-- ================================================================ -->
    <div class="form-card animate-fade-in-up">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-prescription-bottle"></i>
            </div>
            <div>
                <h3 class="form-title">Medicine Information</h3>
                <p class="form-subtitle">Fill in the details to add a new medicine to inventory</p>
            </div>
        </div>
        
        <form method="POST" action="" id="addMedicineForm">
            <input type="hidden" name="action" value="add_medicine">
            
            <div class="form-grid">
                <!-- Medicine Name - Full Width -->
                <div class="full-width form-row">
                    <label class="form-label">Medicine Name <span class="required">*</span></label>
                    <input type="text" name="medication_name" class="form-control" 
                           placeholder="Enter medicine name (e.g. Paracetamol 500mg)" 
                           value="<?= htmlspecialchars($form_data['medication_name']) ?>" required>
                    <div class="help-text">Enter the full name of the medicine including strength if applicable</div>
                </div>
                
                <!-- Category -->
                <div class="form-row">
                    <label class="form-label">Category</label>
                    <div class="category-input-group">
                        <select name="category" id="categorySelect" class="form-control">
                            <option value="">Select or type manually</option>
                            <?php foreach ($predefined_categories as $cat): ?>
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
                    <div class="help-text">Select an existing category or type a new one</div>
                </div>
                
                <!-- Unit -->
                <div class="form-row">
                    <label class="form-label">Unit <span class="required">*</span></label>
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
                    <div class="help-text">Unit of measurement for this medicine</div>
                </div>
                
                <!-- Quantity -->
                <div class="form-row">
                    <label class="form-label">Current Quantity <span class="required">*</span></label>
                    <input type="number" name="quantity" class="form-control" 
                           placeholder="0" min="0" 
                           value="<?= htmlspecialchars($form_data['quantity']) ?>" required>
                    <div class="help-text">Current available stock in units</div>
                </div>
                
                <!-- Reorder Level -->
                <div class="form-row">
                    <label class="form-label">Reorder Level <span class="required">*</span></label>
                    <input type="number" name="reorder_level" class="form-control" 
                           placeholder="10" min="0" 
                           value="<?= htmlspecialchars($form_data['reorder_level']) ?>" required>
                    <div class="help-text">Alert when stock reaches this level</div>
                </div>
                
                <!-- Buying Price -->
                <div class="form-row">
                    <label class="form-label">Buying Price (TSh)</label>
                    <input type="number" name="unit_cost" class="form-control" 
                           placeholder="0" step="1" min="0" 
                           value="<?= htmlspecialchars($form_data['unit_cost']) ?>">
                    <div class="help-text">Cost price per unit (minimum TSh 1)</div>
                </div>
                
                <!-- Selling Price -->
                <div class="form-row">
                    <label class="form-label">Selling Price (TSh) <span class="required">*</span></label>
                    <input type="number" name="selling_price" class="form-control" 
                           placeholder="1" step="1" min="1" 
                           value="<?= htmlspecialchars($form_data['selling_price'] ?: '1') ?>" required>
                    <div class="help-text">Selling price per unit (minimum TSh 1)</div>
                </div>
                
                <!-- Supplier -->
                <div class="form-row">
                    <label class="form-label">Supplier</label>
                    <input type="text" name="supplier" class="form-control" 
                           placeholder="Supplier name" 
                           value="<?= htmlspecialchars($form_data['supplier']) ?>">
                    <div class="help-text">Name of the supplier or vendor</div>
                </div>
                
                <!-- Expiry Date -->
                <div class="form-row">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-control" 
                           value="<?= htmlspecialchars($form_data['expiry_date']) ?>">
                    <div class="help-text">System will show days remaining until expiry</div>
                </div>
                
                <!-- Branch (Admin only) -->
                <div class="form-row">
                    <label class="form-label">Branch <span class="required">*</span></label>
                    <select name="branch_id" class="form-control" required>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="help-text">Select the branch for this inventory item</div>
                </div>
                
                <!-- Batch Number - Full Width -->
                <div class="full-width form-row">
                    <label class="form-label">Batch Number</label>
                    <div class="batch-input-group">
                        <input type="text" name="batch_number" id="batchNumberInput" class="form-control" 
                               placeholder="BATCH-YYYYMMDD-XXXX" 
                               value="<?= htmlspecialchars($form_data['batch_number'] ?: 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6))) ?>">
                        <button type="button" class="btn-generate-batch" onclick="generateBatchNumber()">
                            <i class="fas fa-sync-alt"></i> Generate
                        </button>
                    </div>
                    <div class="batch-help-text">
                        <i class="fas fa-info-circle"></i> Auto-generated. Click "Generate" for a new batch number.
                    </div>
                </div>
                
                <!-- Status -->
                <div class="full-width form-row">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="active" <?= $form_data['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $form_data['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <div class="help-text">Active items will appear in inventory</div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Add Medicine
                </button>
                <a href="pharmacy_inventory.php?id=<?= $branch_id ?>&branch=<?= $branch_id ?>" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="reset" class="btn-cancel" style="border-color:var(--border-color);">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK TIPS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5" style="max-width:900px;margin:20px auto 0;">
        <div style="background:var(--bg-card);border-radius:12px;padding:14px 18px;border:2px solid var(--border-color);display:flex;align-items:center;gap:12px;">
            <div style="width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:var(--primary-light);color:var(--primary);font-size:1rem;flex-shrink:0;">
                <i class="fas fa-tag"></i>
            </div>
            <div>
                <p style="font-size:0.75rem;font-weight:600;color:var(--text-primary);margin:0;">Batch Number</p>
                <p style="font-size:0.65rem;color:var(--text-secondary);margin:0;">Click "Generate" for unique batch</p>
            </div>
        </div>
        <div style="background:var(--bg-card);border-radius:12px;padding:14px 18px;border:2px solid var(--border-color);display:flex;align-items:center;gap:12px;">
            <div style="width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:var(--success-light);color:var(--success);font-size:1rem;flex-shrink:0;">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div>
                <p style="font-size:0.75rem;font-weight:600;color:var(--text-primary);margin:0;">Price</p>
                <p style="font-size:0.65rem;color:var(--text-secondary);margin:0;">Minimum TSh 1 (not 100)</p>
            </div>
        </div>
        <div style="background:var(--bg-card);border-radius:12px;padding:14px 18px;border:2px solid var(--border-color);display:flex;align-items:center;gap:12px;">
            <div style="width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:var(--warning-light);color:var(--warning);font-size:1rem;flex-shrink:0;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <p style="font-size:0.75rem;font-weight:600;color:var(--text-primary);margin:0;">Reorder Level</p>
                <p style="font-size:0.65rem;color:var(--text-secondary);margin:0;">Set alert threshold</p>
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
            Add Medicine
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
            // If there's a value in manual, copy to select temporarily
        } else {
            manual.style.display = 'none';
            select.style.display = 'block';
            btn.innerHTML = '<i class="fas fa-edit"></i> Manual';
            // If manual has value, set it to select
            if (manual.value) {
                select.value = manual.value;
                // Check if value exists in options
                var found = false;
                for (var i = 0; i < select.options.length; i++) {
                    if (select.options[i].value === manual.value) {
                        found = true;
                        break;
                    }
                }
                if (!found) {
                    // Add as new option
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
        if (url.searchParams.has('id')) {
            url.searchParams.delete('id');
        }
        window.location.href = url.toString();
    }

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $branch_id ?>';
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

        // Confirm before adding
        var confirmMsg = '📋 Confirm adding this medicine:\n\n';
        confirmMsg += 'Name: ' + name.value + '\n';
        confirmMsg += 'Quantity: ' + quantity.value + '\n';
        confirmMsg += 'Selling Price: TSh ' + sellingPrice.value + '\n';
        confirmMsg += 'Reorder Level: ' + reorderLevel.value + '\n';
        confirmMsg += 'Branch: ' + document.querySelector('select[name="branch_id"]').selectedOptions[0].text + '\n\n';
        confirmMsg += 'Proceed to add to inventory?';

        if (!confirm(confirmMsg)) {
            e.preventDefault();
            return false;
        }

        return true;
    });

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
        }
    });

    console.log('%c💊 Braick Dispensary - Add Medicine (ADMIN)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Form matches Pharmacy style', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Price minimum: TSh 1 (not 100)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Batch auto-generate enabled', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Branch selector for Admin', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Login protection: Active', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>