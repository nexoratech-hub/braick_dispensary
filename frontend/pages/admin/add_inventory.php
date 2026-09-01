<?php
// ================================================================
// FILE: frontend/pages/admin/add_inventory.php
// ADMIN - ADD INVENTORY ITEM (MEDICINE) - GROUPED BY NAME
// WITH AUTO-SEARCH AND BLUE THEME
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
// BRANCH SELECTION
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
// GET ALL MEDICINE NAMES FOR AUTO-SEARCH (GROUPED)
// ================================================================
$all_medicine_names = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT medication_name, category, selling_price 
        FROM medications_inventory 
        WHERE branch_id = ? 
        ORDER BY medication_name
    ");
    $stmt->execute([$user_branch_id]);
    $all_medicine_names = $stmt->fetchAll();
} catch (Exception $e) {
    $all_medicine_names = [];
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
            
            echo '<script>setTimeout(function(){ window.location.href = "inventory.php?branch=' . $form_data['branch_id'] . '&success=1"; }, 2000);</script>';
            
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

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Medicine - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BLUE THEME
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.12);
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --border-color: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.4);
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
            background: var(--primary);
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
        
        .top-nav .datetime i {
            color: #6EA8FE;
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
        .notif-dot.no-notif { background: var(--text-secondary); animation: none; }
        
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
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .branch-selector:focus {
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
           PAGE HEADER - BLUE THEME
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: var(--radius-lg);
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
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.02);
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
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
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
            border-radius: var(--radius);
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
           FORM CARD - BLUE THEME
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
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
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
            color: #0B5ED7;
        }
        
        /* Auto-complete */
        .autocomplete-container {
            position: relative;
            width: 100%;
        }
        
        .autocomplete-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-top: none;
            border-radius: 0 0 8px 8px;
            z-index: 100;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            box-shadow: var(--shadow-md);
        }
        
        .autocomplete-list.show {
            display: block;
        }
        
        .autocomplete-item {
            padding: 8px 14px;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.82rem;
            transition: all 0.2s ease;
            color: var(--text-primary);
        }
        
        .autocomplete-item:hover {
            background: var(--primary-light);
            color: var(--primary);
        }
        
        .autocomplete-item.active {
            background: var(--primary);
            color: white;
        }
        
        .autocomplete-item .item-detail {
            font-size: 0.65rem;
            color: var(--text-muted);
            display: block;
        }
        
        .autocomplete-item.active .item-detail {
            color: rgba(255,255,255,0.7);
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
            background: #0B5ED7;
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
            background: #0A4CA8;
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
            background: #0B5ED7;
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
            background: #0A4CA8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        /* Buttons - BLUE THEME */
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
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #0A4CA8, #083A8A);
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
        
        .btn-danger-outline {
            background: transparent;
            color: #DC2626;
            border: 2px solid #DC2626;
        }
        
        .btn-danger-outline:hover {
            background: #DC2626;
            color: white;
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
        
        /* Tips Cards - BLUE THEME */
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
            border-color: #0B5ED7;
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
        
        .tip-card .tip-icon.blue { 
            background: #E8F0FE; 
            color: #0B5ED7; 
        }
        .tip-card .tip-icon.green { 
            background: #E6F7EE; 
            color: #059669; 
        }
        .tip-card .tip-icon.yellow { 
            background: #FEF3C7; 
            color: #F59E0B; 
        }
        .tip-card .tip-icon.purple { 
            background: #F3E8FF; 
            color: #7C3AED; 
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
        [data-theme="dark"] .tip-card .tip-icon.blue { 
            background: #1E3A5F; 
            color: #6EA8FE; 
        }
        [data-theme="dark"] .tip-card .tip-icon.green { 
            background: #1A3A2A; 
            color: #34D399; 
        }
        [data-theme="dark"] .tip-card .tip-icon.yellow { 
            background: #3A2A1A; 
            color: #FBBF24; 
        }
        [data-theme="dark"] .tip-card .tip-icon.purple { 
            background: #2A1A3A; 
            color: #9B4DCA; 
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
        
        .help-text {
            font-size: 0.6rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        /* Footer */
        .footer {
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
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
            .main-content {
                padding: 16px;
            }
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; }
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
            <span class="notif-dot <?= $unread_notifications ?? 0 > 0 ? 'has-notif' : 'no-notif' ?>"></span>
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
                <i class="fas fa-plus-circle mr-2"></i> Add Medicine
            </h1>
            <p class="page-subtitle">
                Add new medicine to inventory
                <span class="header-badge">
                    <i class="fas fa-prescription-bottle mr-1"></i> Inventory Management
                </span>
            </p>
        </div>
        <div>
            <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
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
    <!-- FORM - BLUE THEME -->
    <!-- ================================================================ -->
    <div class="form-card">
        <!-- Form Header -->
        <div class="form-header">
            <div class="form-header-icon">
                <i class="fas fa-prescription-bottle"></i>
            </div>
            <div>
                <h3>Medicine Information</h3>
                <p>Type medicine name to search existing. If found, it will add a new batch.</p>
            </div>
        </div>
        
        <form method="POST" action="" id="addMedicineForm">
            <input type="hidden" name="action" value="add_medicine">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Medicine Name - Full Width with Auto-Search -->
                <div class="md:col-span-2">
                    <label class="form-label">
                        <i class="fas fa-capsules text-blue-600"></i> Medicine Name
                        <span class="required">*</span>
                    </label>
                    <div class="autocomplete-container">
                        <div class="form-row-icon">
                            <input type="text" name="medication_name" id="medicineNameInput" class="form-control" 
                                   placeholder="e.g. Paracetamol 500mg, Amoxicillin 250mg" 
                                   value="<?= htmlspecialchars($form_data['medication_name']) ?>" required autocomplete="off">
                            <span class="input-icon"><i class="fas fa-capsules"></i></span>
                        </div>
                        <div class="autocomplete-list" id="medicineAutocomplete"></div>
                    </div>
                    <p class="help-text mt-1">
                        <i class="fas fa-info-circle text-blue-500"></i> 
                        Type to search existing medicine. If found, a new batch will be added. If new, it will be created.
                    </p>
                </div>
                
                <!-- Category -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-tags text-blue-600"></i> Category
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
                        <i class="fas fa-ruler text-blue-600"></i> Unit
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
                        <i class="fas fa-boxes text-blue-600"></i> Current Quantity
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
                        <i class="fas fa-shopping-cart text-green-600"></i> Buying Price (TSh)
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
                    <p class="help-text mt-1">Minimum TSh 1</p>
                </div>
                
                <!-- Supplier -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-truck text-blue-600"></i> Supplier
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
                        <i class="fas fa-store text-blue-600"></i> Branch
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
                        <i class="fas fa-barcode text-blue-600"></i> Batch Number
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
                        <i class="fas fa-info-circle text-blue-500"></i> 
                        Auto-generated. Click "Generate" for a new batch number.
                    </p>
                </div>
                
                <!-- Status -->
                <div class="md:col-span-2">
                    <label class="form-label">
                        <i class="fas fa-circle text-blue-600"></i> Status
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
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Medicine
                </button>
                <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK TIPS - BLUE THEME -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-5" style="max-width:900px;margin:20px auto 0;">
        <div class="tip-card">
            <div class="tip-icon blue">
                <i class="fas fa-search"></i>
            </div>
            <div class="tip-text">
                <h4>Tip #1</h4>
                <p>Auto-search existing medicines</p>
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
            <div class="tip-icon purple">
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="tip-text">
                <h4>Tip #4</h4>
                <p>Grouped by name - adds new batch</p>
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
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
// ================================================================
// AUTO-SEARCH - Medicine Name (Grouped)
// ================================================================
(function() {
    var medicineData = <?= json_encode($all_medicine_names) ?>;
    var input = document.getElementById('medicineNameInput');
    var autocomplete = document.getElementById('medicineAutocomplete');
    
    if (!input || !autocomplete) return;
    
    input.addEventListener('input', function() {
        var query = this.value.toLowerCase().trim();
        
        if (query.length < 1) {
            autocomplete.classList.remove('show');
            return;
        }
        
        var matches = medicineData.filter(function(item) {
            return item.medication_name.toLowerCase().includes(query);
        });
        
        if (matches.length === 0) {
            autocomplete.classList.remove('show');
            return;
        }
        
        var html = '';
        matches.forEach(function(item) {
            html += `
                <div class="autocomplete-item" data-name="${escapeHtml(item.medication_name)}">
                    <strong>${escapeHtml(item.medication_name)}</strong>
                    <span class="item-detail">
                        Category: ${escapeHtml(item.category || 'N/A')} | 
                        Price: TSh ${Number(item.selling_price || 0).toLocaleString()}
                    </span>
                </div>
            `;
        });
        
        autocomplete.innerHTML = html;
        autocomplete.classList.add('show');
        
        autocomplete.querySelectorAll('.autocomplete-item').forEach(function(item) {
            item.addEventListener('click', function() {
                input.value = this.dataset.name;
                autocomplete.classList.remove('show');
            });
        });
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.autocomplete-container')) {
            autocomplete.classList.remove('show');
        }
    });
    
    var selectedIndex = -1;
    input.addEventListener('keydown', function(e) {
        var items = autocomplete.querySelectorAll('.autocomplete-item');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
            updateSelection(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            updateSelection(items);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && items.length > 0) {
                var selectedItem = items[selectedIndex];
                input.value = selectedItem.dataset.name;
                autocomplete.classList.remove('show');
                selectedIndex = -1;
            }
        } else if (e.key === 'Escape') {
            autocomplete.classList.remove('show');
            selectedIndex = -1;
        }
    });
    
    function updateSelection(items) {
        items.forEach(function(item, index) {
            if (index === selectedIndex) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
        if (selectedIndex >= 0 && items.length > 0) {
            items[selectedIndex].scrollIntoView({ block: 'nearest' });
        }
    }
})();

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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

console.log('%c💊 Braick - Add Medicine (ADMIN)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
console.log('%c✅ Table: medications_inventory', 'font-size:13px; color:#0B5ED7;');
console.log('%c✅ Grouped by Name - Auto-search enabled', 'font-size:13px; color:#0B5ED7;');
console.log('%c✅ Price minimum: TSh 1', 'font-size:13px; color:#059669;');
console.log('%c✅ Batch auto-generate enabled', 'font-size:13px; color:#0B5ED7;');
console.log('%c🎨 Blue Theme', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>