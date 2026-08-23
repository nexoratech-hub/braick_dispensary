<?php
// ================================================================
// FILE: frontend/pages/pharmacy/inventory.php
// PHARMACY - COMPLETE INVENTORY (MEDICINE + EQUIPMENT)
// WITH AUTO-MONEY FORMAT & LOGIN PROTECTION
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
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK USER ACCESS (Pharmacy or Admin)
// ================================================================
$allowed_roles = ['pharmacy', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// MONEY FORMAT FUNCTIONS
// ================================================================
function formatMoney($amount) {
    if ($amount === null || $amount === '') {
        return '0.00';
    }
    return number_format((float)$amount, 2, '.', ',');
}

function formatMoneyNoDecimal($amount) {
    if ($amount === null || $amount === '') {
        return '0';
    }
    return number_format((float)$amount, 0, '.', ',');
}

function formatMoneyShort($amount) {
    if ($amount === null || $amount === '') {
        return '0';
    }
    $amount = (float)$amount;
    if ($amount >= 1000000000) {
        return number_format($amount / 1000000000, 1) . 'B';
    }
    if ($amount >= 1000000) {
        return number_format($amount / 1000000, 1) . 'M';
    }
    if ($amount >= 1000) {
        return number_format($amount / 1000, 1) . 'K';
    }
    return number_format($amount, 0);
}

function cleanMoney($value) {
    return str_replace(',', '', $value);
}

function getMoney($value) {
    $clean = cleanMoney($value);
    return floatval($clean);
}

// ================================================================
// DATABASE CONNECTION
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// AUTO-UPDATE EXPIRED ITEMS
// ================================================================
try {
    // Medicines
    $stmt = $db->prepare("
        UPDATE medications_inventory 
        SET status = 'inactive', updated_at = NOW()
        WHERE expiry_date IS NOT NULL 
        AND expiry_date < CURDATE() 
        AND status = 'active'
    ");
    $stmt->execute();
    
    // Equipment
    $stmt = $db->prepare("
        UPDATE medical_equipment 
        SET status = 'inactive', updated_at = NOW()
        WHERE expiry_date IS NOT NULL 
        AND expiry_date < CURDATE() 
        AND status = 'active'
    ");
    $stmt->execute();
} catch (Exception $e) {
    // Silent fail
}

// ================================================================
// GET CATEGORIES
// ================================================================
$med_categories = [];
$stmt = $db->query("SELECT DISTINCT category FROM medications_inventory WHERE category IS NOT NULL AND category != '' ORDER BY category");
$med_categories = $stmt->fetchAll();

$equip_categories = [];
$stmt = $db->query("SELECT DISTINCT category FROM medical_equipment WHERE category IS NOT NULL AND category != '' ORDER BY category");
$equip_categories = $stmt->fetchAll();

// ================================================================
// PRE-DEFINED CATEGORIES
// ================================================================
$predefined_med_categories = [
    'Antibiotics', 'Painkillers', 'Antipyretics', 'Antihistamines',
    'Antacids', 'Antivirals', 'Antifungals', 'Antimalarials',
    'Vitamins', 'Supplements', 'Respiratory', 'Cardiovascular',
    'Diabetes', 'Hypertension', 'Dermatological', 'Eye Drops',
    'Ear Drops', 'Injectables', 'IV Fluids', 'Other'
];

$predefined_equip_categories = [
    'Surgical Instruments', 'Diagnostic Tools', 'Lab Equipment',
    'Monitoring Devices', 'Sterilization Equipment', 'Patient Care',
    'IV Equipment', 'Wound Care', 'Orthopedic', 'Emergency',
    'Respiratory', 'Other'
];

// ================================================================
// PROCESS POST REQUESTS
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // ADD MEDICINE (Price accepts 0, auto-money format)
    // ================================================================
    if ($action === 'add_medicine') {
        $medication_name = trim($_POST['medication_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        if (empty($category) && !empty($_POST['category_manual'])) {
            $category = trim($_POST['category_manual']);
        }
        $unit = trim($_POST['unit'] ?? 'pcs');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reorder_level = (int)($_POST['reorder_level'] ?? 10);
        
        // Clean money values (remove commas)
        $unit_cost = getMoney($_POST['unit_cost'] ?? 0);
        $selling_price = getMoney($_POST['selling_price'] ?? 0);
        
        $supplier = trim($_POST['supplier'] ?? '');
        $expiry_date = $_POST['expiry_date'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        if (empty($batch_number)) {
            $batch_number = 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        }
        
        $errors = [];
        if (empty($medication_name)) { $errors[] = 'Medicine name is required'; }
        if ($quantity < 0) { $errors[] = 'Quantity cannot be negative'; }
        if ($selling_price < 0) { $errors[] = 'Selling price cannot be negative'; }
        if (!empty($expiry_date) && strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
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
                    $medication_name, $category, $unit, $quantity, $reorder_level,
                    $unit_cost, $selling_price, $supplier, $expiry_date, $batch_number,
                    $user_branch_id, $status
                ]);
                
                $message = "✅ Medicine added successfully! Batch: <strong>$batch_number</strong>";
                $message_type = 'success';
                $_SESSION['inventory_message'] = $message;
                $_SESSION['inventory_message_type'] = $message_type;
                header('Location: inventory.php?tab=medicines&added=1');
                exit;
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
    // ADD EQUIPMENT (Price accepts 0, auto-money format)
    // ================================================================
    if ($action === 'add_equipment') {
        $equipment_name = trim($_POST['equipment_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        if (empty($category) && !empty($_POST['category_manual'])) {
            $category = trim($_POST['category_manual']);
        }
        $unit = trim($_POST['unit'] ?? 'pcs');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reorder_level = (int)($_POST['reorder_level'] ?? 5);
        
        // Clean money values (remove commas)
        $unit_cost = getMoney($_POST['unit_cost'] ?? 0);
        $selling_price = getMoney($_POST['selling_price'] ?? 0);
        
        $supplier = trim($_POST['supplier'] ?? '');
        $expiry_date = $_POST['expiry_date'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        if (empty($batch_number)) {
            $batch_number = 'EQP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        }
        
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
                    INSERT INTO medical_equipment (
                        equipment_name, category, unit, quantity, reorder_level,
                        unit_cost, selling_price, supplier, expiry_date, batch_number,
                        branch_id, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $equipment_name, $category, $unit, $quantity, $reorder_level,
                    $unit_cost, $selling_price, $supplier, $expiry_date, $batch_number,
                    $user_branch_id, $status
                ]);
                
                $message = "✅ Equipment added successfully! Batch: <strong>$batch_number</strong>";
                $message_type = 'success';
                $_SESSION['inventory_message'] = $message;
                $_SESSION['inventory_message_type'] = $message_type;
                header('Location: inventory.php?tab=equipment&added=1');
                exit;
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
}

// ================================================================
// CHECK SESSION MESSAGES
// ================================================================
if (isset($_SESSION['inventory_message'])) {
    $message = $_SESSION['inventory_message'];
    $message_type = $_SESSION['inventory_message_type'] ?? 'success';
    unset($_SESSION['inventory_message']);
    unset($_SESSION['inventory_message_type']);
}

// ================================================================
// GET TAB FROM URL
// ================================================================
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'medicines';
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_type = isset($_GET['type']) ? $_GET['type'] : 'medicine';

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$stock_filter = isset($_GET['stock']) ? trim($_GET['stock']) : '';
$expiry_filter = isset($_GET['expiry']) ? trim($_GET['expiry']) : '';

// ================================================================
// BUILD MEDICINE QUERY
// ================================================================
$med_query = "
    SELECT *, 
        DATEDIFF(expiry_date, CURDATE()) as days_remaining
    FROM medications_inventory 
    WHERE branch_id = ?
";
$med_params = [$user_branch_id];

if (!empty($search)) {
    $med_query .= " AND medication_name LIKE ?";
    $med_params[] = "%$search%";
}
if (!empty($category_filter)) {
    $med_query .= " AND category = ?";
    $med_params[] = $category_filter;
}
if ($status_filter === 'active') {
    $med_query .= " AND status = 'active'";
} elseif ($status_filter === 'inactive') {
    $med_query .= " AND status = 'inactive'";
}
if ($stock_filter === 'low') {
    $med_query .= " AND quantity <= reorder_level AND quantity > 0 AND status = 'active'";
} elseif ($stock_filter === 'out') {
    $med_query .= " AND quantity = 0 AND status = 'active'";
}
if ($expiry_filter === 'expiring') {
    $med_query .= " AND expiry_date IS NOT NULL 
                    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                    AND status = 'active'";
}
if ($expiry_filter === 'expired') {
    $med_query .= " AND expiry_date IS NOT NULL 
                    AND expiry_date < CURDATE()";
}
$med_query .= " ORDER BY medication_name ASC";

$stmt = $db->prepare($med_query);
$stmt->execute($med_params);
$medicines = $stmt->fetchAll();

// ================================================================
// BUILD EQUIPMENT QUERY
// ================================================================
$equip_query = "
    SELECT *, 
        DATEDIFF(expiry_date, CURDATE()) as days_remaining
    FROM medical_equipment 
    WHERE branch_id = ?
";
$equip_params = [$user_branch_id];

if (!empty($search)) {
    $equip_query .= " AND equipment_name LIKE ?";
    $equip_params[] = "%$search%";
}
if (!empty($category_filter)) {
    $equip_query .= " AND category = ?";
    $equip_params[] = $category_filter;
}
if ($status_filter === 'active') {
    $equip_query .= " AND status = 'active'";
} elseif ($status_filter === 'inactive') {
    $equip_query .= " AND status = 'inactive'";
}
if ($stock_filter === 'low') {
    $equip_query .= " AND quantity <= reorder_level AND quantity > 0 AND status = 'active'";
} elseif ($stock_filter === 'out') {
    $equip_query .= " AND quantity = 0 AND status = 'active'";
}
if ($expiry_filter === 'expiring') {
    $equip_query .= " AND expiry_date IS NOT NULL 
                    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                    AND status = 'active'";
}
if ($expiry_filter === 'expired') {
    $equip_query .= " AND expiry_date IS NOT NULL 
                    AND expiry_date < CURDATE()";
}
$equip_query .= " ORDER BY equipment_name ASC";

$stmt = $db->prepare($equip_query);
$stmt->execute($equip_params);
$equipment = $stmt->fetchAll();

// ================================================================
// GET STATISTICS - MEDICINES
// ================================================================

// Total Medicines
$stmt = $db->prepare("SELECT COUNT(*) as count FROM medications_inventory WHERE branch_id = ? AND status = 'active'");
$stmt->execute([$user_branch_id]);
$total_medicines = $stmt->fetch()['count'] ?? 0;

// Medicine In Stock
$stmt = $db->prepare("SELECT COUNT(*) as count FROM medications_inventory WHERE branch_id = ? AND quantity > 0 AND status = 'active'");
$stmt->execute([$user_branch_id]);
$med_in_stock = $stmt->fetch()['count'] ?? 0;

// Medicine Out of Stock
$stmt = $db->prepare("SELECT COUNT(*) as count FROM medications_inventory WHERE branch_id = ? AND quantity = 0 AND status = 'active'");
$stmt->execute([$user_branch_id]);
$med_out_of_stock = $stmt->fetch()['count'] ?? 0;

// Medicine Low Stock
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND status = 'active' AND quantity > 0 AND quantity <= reorder_level
");
$stmt->execute([$user_branch_id]);
$med_low_stock = $stmt->fetch()['count'] ?? 0;

// Medicine Expiring Soon
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND expiry_date IS NOT NULL 
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute([$user_branch_id]);
$med_expiring = $stmt->fetch()['count'] ?? 0;

// Medicine Expired
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND expiry_date IS NOT NULL AND expiry_date < CURDATE()
");
$stmt->execute([$user_branch_id]);
$med_expired = $stmt->fetch()['count'] ?? 0;

// Medicine Inactive
$stmt = $db->prepare("SELECT COUNT(*) as count FROM medications_inventory WHERE branch_id = ? AND status = 'inactive'");
$stmt->execute([$user_branch_id]);
$med_inactive = $stmt->fetch()['count'] ?? 0;

// Medicine Total Value
$stmt = $db->prepare("
    SELECT SUM(quantity * selling_price) as total_value 
    FROM medications_inventory 
    WHERE branch_id = ? AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$med_value = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

// ================================================================
// GET STATISTICS - EQUIPMENT
// ================================================================

// Total Equipment
$stmt = $db->prepare("SELECT COUNT(*) as count FROM medical_equipment WHERE branch_id = ? AND status = 'active'");
$stmt->execute([$user_branch_id]);
$total_equipment = $stmt->fetch()['count'] ?? 0;

// Equipment In Stock
$stmt = $db->prepare("SELECT COUNT(*) as count FROM medical_equipment WHERE branch_id = ? AND quantity > 0 AND status = 'active'");
$stmt->execute([$user_branch_id]);
$equip_in_stock = $stmt->fetch()['count'] ?? 0;

// Equipment Out of Stock
$stmt = $db->prepare("SELECT COUNT(*) as count FROM medical_equipment WHERE branch_id = ? AND quantity = 0 AND status = 'active'");
$stmt->execute([$user_branch_id]);
$equip_out_of_stock = $stmt->fetch()['count'] ?? 0;

// Equipment Low Stock
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medical_equipment 
    WHERE branch_id = ? AND status = 'active' AND quantity > 0 AND quantity <= reorder_level
");
$stmt->execute([$user_branch_id]);
$equip_low_stock = $stmt->fetch()['count'] ?? 0;

// Equipment Expiring Soon
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medical_equipment 
    WHERE branch_id = ? AND expiry_date IS NOT NULL 
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute([$user_branch_id]);
$equip_expiring = $stmt->fetch()['count'] ?? 0;

// Equipment Expired
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medical_equipment 
    WHERE branch_id = ? AND expiry_date IS NOT NULL AND expiry_date < CURDATE()
");
$stmt->execute([$user_branch_id]);
$equip_expired = $stmt->fetch()['count'] ?? 0;

// Equipment Inactive
$stmt = $db->prepare("SELECT COUNT(*) as count FROM medical_equipment WHERE branch_id = ? AND status = 'inactive'");
$stmt->execute([$user_branch_id]);
$equip_inactive = $stmt->fetch()['count'] ?? 0;

// Equipment Total Value
$stmt = $db->prepare("
    SELECT SUM(quantity * selling_price) as total_value 
    FROM medical_equipment 
    WHERE branch_id = ? AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$equip_value = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

// Total Inventory Value
$total_inventory_value = $med_value + $equip_value;

// ================================================================
// GET VIEW DATA
// ================================================================
$view_data = null;
if ($view_id > 0) {
    if ($view_type === 'medicine') {
        $stmt = $db->prepare("SELECT * FROM medications_inventory WHERE id = ? AND branch_id = ?");
        $stmt->execute([$view_id, $user_branch_id]);
        $view_data = $stmt->fetch();
    } else {
        $stmt = $db->prepare("SELECT * FROM medical_equipment WHERE id = ? AND branch_id = ?");
        $stmt->execute([$view_id, $user_branch_id]);
        $view_data = $stmt->fetch();
    }
}

// ================================================================
// PROFILE & LOGO
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A3D8A;
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
            --teal: #0D9488;
            --teal-light: #CCFBF1;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #94A3B8;
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
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
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
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn-add-medicine {
            background: var(--success);
            color: white;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }
        
        .btn-add-medicine:hover {
            background: var(--success-dark);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35);
        }
        
        .btn-add-equipment {
            background: var(--purple);
            color: white;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
        }
        
        .btn-add-equipment:hover {
            background: #6D28D9;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(124, 58, 237, 0.35);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }
        
        .btn-save {
            background: var(--primary);
            color: white;
            padding: 10px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-save:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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
        }
        
        .btn-cancel:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .btn-search {
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            background: var(--primary);
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-reset {
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-reset:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .btn-generate {
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
            height: 42px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-generate:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-toggle {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            height: 42px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-toggle:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        /* ================================================================
           TABS
           ================================================================ */
        .tabs-container {
            display: flex;
            gap: 4px;
            background: var(--bg-card);
            border-radius: 12px;
            padding: 4px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
        }
        
        .tab-btn {
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            background: transparent;
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
        }
        
        .tab-btn:hover {
            background: var(--primary-light);
            color: var(--primary);
        }
        
        .tab-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .tab-btn.active:hover {
            background: var(--primary-dark);
        }
        
        .tab-btn .badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.65rem;
        }
        
        .tab-btn:not(.active) .badge {
            background: var(--border-color);
            color: var(--text-secondary);
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* ================================================================
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: 12px;
            padding: 14px 16px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            min-height: 80px;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card:active {
            transform: scale(0.97);
        }
        
        .stat-card .stat-number {
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.85);
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-card .stat-icon {
            font-size: 1.2rem;
            opacity: 0.8;
            float: right;
        }
        
        .stat-card .stat-value {
            font-size: 0.7rem;
            font-weight: 600;
            color: rgba(255,255,255,0.7);
            margin-top: 2px;
        }
        
        .stat-card .stat-sub {
            font-size: 0.55rem;
            color: rgba(255,255,255,0.5);
        }
        
        .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
        .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
        .stat-card.pink { background: linear-gradient(135deg, #DB2777, #BE185D); }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px 22px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 24px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-blue { color: var(--primary); }
        .card-title .title-purple { color: var(--purple); }
        
        .result-count {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .result-count strong {
            color: var(--primary);
        }
        
        /* ================================================================
           FILTERS
           ================================================================ */
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 14px;
        }
        
        .filter-btn {
            padding: 4px 14px;
            border-radius: 16px;
            font-size: 0.7rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .filter-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .filter-btn.active:hover {
            background: var(--primary-dark);
        }
        
        .filter-btn.clear-filter {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .filter-btn.clear-filter:hover {
            background: var(--danger);
            color: white;
        }
        
        .filter-btn.expired-filter {
            border-color: #7F1D1D;
            color: #7F1D1D;
        }
        
        .filter-btn.expired-filter:hover {
            background: #7F1D1D;
            color: white;
        }
        
        .filter-btn.expired-filter.active {
            background: #7F1D1D;
            border-color: #7F1D1D;
            color: white;
        }
        
        .search-form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-form input[type="text"],
        .search-form select {
            padding: 6px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
            flex: 1;
            min-width: 100px;
        }
        
        .search-form input:focus,
        .search-form select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-wrap {
            overflow-x: auto;
            max-height: 450px;
            overflow-y: auto;
        }
        
        .table-wrap::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }
        
        .table-wrap::-webkit-scrollbar-track {
            background: var(--bg-body);
            border-radius: 4px;
        }
        
        .table-wrap::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        .data-table {
            width: 100%;
            min-width: 1100px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.78rem;
        }
        
        .data-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--primary);
            color: white;
            padding: 8px 12px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            white-space: nowrap;
            text-align: left;
        }
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        .data-table tbody tr:nth-child(even) {
            background: var(--primary-light);
        }
        
        .data-table tbody tr:hover td {
            background: var(--success-light);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) {
            background: #1E293B;
        }
        
        [data-theme="dark"] .data-table tbody tr:hover td {
            background: #1A3A2A;
        }
        
        .data-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            white-space: nowrap;
        }
        
        .col-sno { width: 35px; text-align: center; }
        .col-name { min-width: 160px; }
        .col-category { min-width: 100px; }
        .col-qty { min-width: 60px; text-align: center; }
        .col-reorder { min-width: 70px; text-align: center; }
        .col-stock { min-width: 100px; }
        .col-price { min-width: 130px; font-family: 'Courier New', monospace; }
        .col-expiry { min-width: 100px; }
        .col-days { min-width: 70px; text-align: center; }
        .col-batch { min-width: 130px; }
        .col-supplier { min-width: 100px; }
        .col-status { min-width: 70px; text-align: center; }
        .col-actions { min-width: 60px; text-align: center; }
        
        /* ================================================================
           MONEY FORMAT STYLES
           ================================================================ */
        .money-amount {
            font-family: 'Courier New', 'Consolas', monospace;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            display: inline-flex;
            align-items: baseline;
            gap: 2px;
        }
        
        .money-amount .currency-symbol {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .money-amount .amount {
            font-size: inherit;
            font-weight: 600;
        }
        
        .money-amount .decimal-part {
            font-size: 0.65rem;
            color: var(--text-muted);
        }
        
        .col-price .currency {
            font-size: 0.6rem;
            color: var(--text-secondary);
            margin-right: 1px;
        }
        
        .col-price .amount {
            font-weight: 600;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .status-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .status-badge.active {
            background: var(--success-light);
            color: var(--success);
        }
        
        .status-badge.inactive {
            background: var(--danger-light);
            color: var(--danger);
        }
        
        .stock-badge {
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .stock-badge.ok {
            background: var(--success-light);
            color: var(--success);
        }
        
        .stock-badge.low {
            background: var(--warning-light);
            color: var(--warning);
            animation: pulse 1.5s infinite;
        }
        
        .stock-badge.out {
            background: var(--danger-light);
            color: var(--danger);
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        .expiry-badge {
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .expiry-badge.valid {
            background: var(--success-light);
            color: var(--success);
        }
        
        .expiry-badge.expiring {
            background: var(--warning-light);
            color: var(--warning);
            animation: pulse 1.5s infinite;
        }
        
        .expiry-badge.expired {
            background: var(--danger-light);
            color: var(--danger);
            animation: pulse 1s infinite;
        }
        
        .days-remaining {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .days-remaining.good {
            background: var(--success-light);
            color: var(--success);
        }
        
        .days-remaining.warning {
            background: var(--warning-light);
            color: var(--warning);
            animation: pulse 1.5s infinite;
        }
        
        .days-remaining.danger {
            background: var(--danger-light);
            color: var(--danger);
            animation: pulse 1s infinite;
        }
        
        .batch-number {
            font-family: monospace;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 4px;
            background: var(--primary-light);
            color: var(--primary);
        }
        
        [data-theme="dark"] .batch-number {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .action-btn {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .action-btn.view {
            background: var(--purple);
            color: white;
        }
        
        .action-btn.view:hover {
            background: #6D28D9;
            transform: scale(1.05);
        }
        
        /* ================================================================
           MESSAGE
           ================================================================ */
        .message-box {
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
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
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2.5rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 0.9rem;
        }
        
        .empty-state .sub {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        
        /* ================================================================
           MODAL
           ================================================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px 28px;
            max-width: 750px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 16px;
        }
        
        .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-title i { color: var(--primary); }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.4rem;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        
        .modal-close:hover {
            color: var(--danger);
            transform: rotate(90deg);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        
        .form-grid .full-width { grid-column: 1 / -1; }
        
        .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 3px;
            display: block;
        }
        
        .form-label .required {
            color: var(--danger);
            margin-left: 2px;
        }
        
        .form-control {
            width: 100%;
            padding: 7px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .help-text {
            font-size: 0.6rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 18px;
            padding-top: 14px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .category-input-group {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        
        .category-input-group .form-control { flex: 1; }
        
        .batch-input-group {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        
        .batch-input-group .form-control { flex: 1; }
        
        /* ================================================================
           VIEW MODAL
           ================================================================ */
        .view-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
        }
        
        .view-item {
            padding: 8px 12px;
            background: var(--bg-body);
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }
        
        .view-item .label {
            font-size: 0.55rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        
        .view-item .value {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 2px;
        }
        
        .view-item .value .money-amount {
            font-size: 0.95rem;
        }
        
        .view-item .value .money-amount .currency-symbol {
            font-size: 0.65rem;
        }
        
        .view-item.full-width { grid-column: 1 / -1; }
        
        [data-theme="dark"] .view-item {
            background: #1E293B;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 10px;
            z-index: 999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: var(--shadow-lg);
            font-size: 0.85rem;
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: #D97706; }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
        .animate-fade-in-up:nth-child(2) { animation-delay: 0.1s; }
        .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
        .animate-fade-in-up:nth-child(4) { animation-delay: 0.2s; }
        .animate-fade-in-up:nth-child(5) { animation-delay: 0.25s; }
        .animate-fade-in-up:nth-child(6) { animation-delay: 0.3s; }
        .animate-fade-in-up:nth-child(7) { animation-delay: 0.35s; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(4, 1fr); }
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .search-form { flex-direction: column; align-items: stretch; }
            .search-form input, .search-form select { min-width: 100%; }
            .filter-group { justify-content: center; }
            .card { padding: 12px 14px; }
            .modal-content { padding: 16px; }
            .form-grid { grid-template-columns: 1fr; }
            .form-grid .full-width { grid-column: 1; }
            .category-input-group { flex-direction: column; }
            .category-input-group .btn-toggle { width: 100%; justify-content: center; }
            .batch-input-group { flex-direction: column; }
            .batch-input-group .btn-generate { width: 100%; justify-content: center; }
            .tab-btn { font-size: 0.7rem; padding: 8px 12px; }
            .tab-btn .badge { display: none; }
            .page-header .page-title { font-size: 1.3rem; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .stat-card { padding: 10px 12px; min-height: 65px; }
            .header-actions { flex-direction: column; align-items: stretch; width: 100%; }
            .header-actions .btn-add-medicine,
            .header-actions .btn-add-equipment,
            .header-actions .btn-outline { width: 100%; justify-content: center; }
            .view-grid { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
            .form-actions .btn-save,
            .form-actions .btn-cancel { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card .stat-number { font-size: 0.9rem; }
            .stat-card { padding: 8px 10px; min-height: 55px; }
            .stat-card .stat-icon { font-size: 1rem; }
            .data-table { min-width: 750px; font-size: 0.65rem; }
            .data-table th, .data-table td { padding: 4px 6px; }
            .col-price { min-width: 90px; font-size: 0.7rem; }
            .modal-content { padding: 12px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-warehouse"></i> Inventory
            </h1>
            <p class="page-subtitle">
                Manage medicines and medical equipment
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="branch-tag" style="background:rgba(255,255,255,0.1);">
                    <i class="fas fa-coins"></i> TSh <?= formatMoneyShort($total_inventory_value) ?>
                </span>
            </p>
        </div>
        <div class="header-actions">
            <button onclick="openAddModal('medicine')" class="btn-add-medicine">
                <i class="fas fa-plus-circle"></i> Add Medicine
            </button>
            <button onclick="openAddModal('equipment')" class="btn-add-equipment">
                <i class="fas fa-plus-circle"></i> Add Equipment
            </button>
            <a href="dashboard.php" class="btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- TABS -->
    <!-- ================================================================ -->
    <div class="tabs-container animate-fade-in-up">
        <button class="tab-btn <?= $active_tab === 'medicines' ? 'active' : '' ?>" 
                onclick="switchTab('medicines')">
            <i class="fas fa-pills"></i> Medicines
            <span class="badge"><?= $total_medicines ?></span>
        </button>
        <button class="tab-btn <?= $active_tab === 'equipment' ? 'active' : '' ?>" 
                onclick="switchTab('equipment')">
            <i class="fas fa-tools"></i> Equipment
            <span class="badge"><?= $total_equipment ?></span>
        </button>
    </div>

    <!-- ================================================================ -->
    <!-- TAB CONTENT: MEDICINES -->
    <!-- ================================================================ -->
    <div id="tab-medicines" class="tab-content <?= $active_tab === 'medicines' ? 'active' : '' ?>">
        
        <!-- Medicine Stats -->
        <div class="stats-grid animate-fade-in-up">
            <a href="inventory.php?tab=medicines" class="stat-card blue">
                <span class="stat-icon"><i class="fas fa-pills"></i></span>
                <div class="stat-number"><?= $total_medicines ?></div>
                <div class="stat-label">Total Medicines</div>
                <div class="stat-value">💊 <?= formatMoneyShort($med_value) ?></div>
            </a>
            <a href="inventory.php?tab=medicines&stock=low" class="stat-card orange">
                <span class="stat-icon"><i class="fas fa-exclamation-triangle"></i></span>
                <div class="stat-number"><?= $med_low_stock ?></div>
                <div class="stat-label">Low Stock</div>
                <div class="stat-sub">Below reorder level</div>
            </a>
            <a href="inventory.php?tab=medicines&stock=out" class="stat-card red">
                <span class="stat-icon"><i class="fas fa-times-circle"></i></span>
                <div class="stat-number"><?= $med_out_of_stock ?></div>
                <div class="stat-label">Out of Stock</div>
                <div class="stat-sub">Quantity = 0</div>
            </a>
            <a href="inventory.php?tab=medicines&expiry=expiring" class="stat-card teal">
                <span class="stat-icon"><i class="fas fa-clock"></i></span>
                <div class="stat-number"><?= $med_expiring ?></div>
                <div class="stat-label">Expiring Soon</div>
                <div class="stat-sub">Within 30 days</div>
            </a>
            <a href="inventory.php?tab=medicines&expiry=expired" class="stat-card red">
                <span class="stat-icon"><i class="fas fa-skull"></i></span>
                <div class="stat-number"><?= $med_expired ?></div>
                <div class="stat-label">Expired</div>
                <div class="stat-sub">Past expiry date</div>
            </a>
            <a href="inventory.php?tab=medicines&status=active" class="stat-card green">
                <span class="stat-icon"><i class="fas fa-check-circle"></i></span>
                <div class="stat-number"><?= $med_in_stock ?></div>
                <div class="stat-label">In Stock</div>
                <div class="stat-sub">Available</div>
            </a>
            <a href="inventory.php?tab=medicines&status=inactive" class="stat-card purple">
                <span class="stat-icon"><i class="fas fa-archive"></i></span>
                <div class="stat-number"><?= $med_inactive ?></div>
                <div class="stat-label">Inactive</div>
                <div class="stat-sub">Not available</div>
            </a>
        </div>

        <!-- Filters -->
        <div class="card animate-fade-in-up">
            <div class="filter-group">
                <a href="inventory.php?tab=medicines" class="filter-btn <?= empty($status_filter) && empty($stock_filter) && empty($expiry_filter) ? 'active' : '' ?>">All</a>
                <a href="inventory.php?tab=medicines&status=active" class="filter-btn <?= $status_filter === 'active' ? 'active' : '' ?>">Active</a>
                <a href="inventory.php?tab=medicines&status=inactive" class="filter-btn <?= $status_filter === 'inactive' ? 'active' : '' ?>">Inactive</a>
                <a href="inventory.php?tab=medicines&stock=low" class="filter-btn <?= $stock_filter === 'low' ? 'active' : '' ?>">Low Stock</a>
                <a href="inventory.php?tab=medicines&stock=out" class="filter-btn <?= $stock_filter === 'out' ? 'active' : '' ?>">Out of Stock</a>
                <a href="inventory.php?tab=medicines&expiry=expiring" class="filter-btn <?= $expiry_filter === 'expiring' ? 'active' : '' ?>">Expiring Soon</a>
                <a href="inventory.php?tab=medicines&expiry=expired" class="filter-btn expired-filter <?= $expiry_filter === 'expired' ? 'active' : '' ?>">
                    <i class="fas fa-skull"></i> Expired
                </a>
                <?php if (!empty($stock_filter) || !empty($expiry_filter) || !empty($status_filter)): ?>
                    <a href="inventory.php?tab=medicines" class="filter-btn clear-filter">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
            
            <form method="GET" class="search-form">
                <input type="hidden" name="tab" value="medicines">
                <input type="text" name="search" placeholder="🔍 Search medicine..." value="<?= htmlspecialchars($search) ?>">
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($med_categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-search"><i class="fas fa-search"></i> Filter</button>
                <a href="inventory.php?tab=medicines" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </form>
        </div>

        <!-- Medicine Table -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list title-blue"></i> Medicine List
                    <span class="result-count">(<strong><?= count($medicines) ?></strong> records)</span>
                    <?php if ($total_medicines > 0): ?>
                        <span class="result-count ml-2">Total Value: <strong>TSh <?= formatMoney($med_value) ?></strong></span>
                    <?php endif; ?>
                </h3>
            </div>
            
            <?php if (count($medicines) > 0): ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="col-sno">#</th>
                                <th class="col-name">Name</th>
                                <th class="col-category">Category</th>
                                <th class="col-qty">Qty</th>
                                <th class="col-reorder">Reorder</th>
                                <th class="col-stock">Stock</th>
                                <th class="col-price">Price (TSh)</th>
                                <th class="col-expiry">Expiry</th>
                                <th class="col-days">Days</th>
                                <th class="col-batch">Batch</th>
                                <th class="col-status">Status</th>
                                <th class="col-actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($medicines as $item): ?>
                                <?php
                                    $stock_status = 'ok';
                                    $stock_label = 'In Stock';
                                    if ($item['quantity'] <= 0) {
                                        $stock_status = 'out';
                                        $stock_label = 'Out of Stock';
                                    } elseif ($item['quantity'] <= $item['reorder_level']) {
                                        $stock_status = 'low';
                                        $stock_label = 'Low Stock';
                                    }
                                    
                                    $expiry_status = 'valid';
                                    $days = '-';
                                    $days_class = 'good';
                                    if (!empty($item['expiry_date'])) {
                                        $days = $item['days_remaining'];
                                        if ($days < 0) {
                                            $expiry_status = 'expired';
                                            $days_class = 'danger';
                                        } elseif ($days <= 30) {
                                            $expiry_status = 'expiring';
                                            $days_class = 'warning';
                                        }
                                    }
                                ?>
                                <tr>
                                    <td class="col-sno"><?= $counter++ ?></td>
                                    <td class="col-name">
                                        <strong><?= htmlspecialchars($item['medication_name']) ?></strong>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></div>
                                    </td>
                                    <td class="col-category"><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                    <td class="col-qty"><strong><?= $item['quantity'] ?></strong></td>
                                    <td class="col-reorder"><?= $item['reorder_level'] ?></td>
                                    <td class="col-stock">
                                        <span class="stock-badge <?= $stock_status ?>">
                                            <i class="fas <?= $stock_status === 'ok' ? 'fa-check-circle' : ($stock_status === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                                            <?= $stock_label ?>
                                        </span>
                                    </td>
                                    <td class="col-price">
                                        <span class="money-amount">
                                            <span class="currency-symbol">TSh</span>
                                            <span class="amount"><?= formatMoney($item['selling_price'] ?? 0) ?></span>
                                        </span>
                                    </td>
                                    <td class="col-expiry">
                                        <?php if (!empty($item['expiry_date'])): ?>
                                            <span class="expiry-badge <?= $expiry_status ?>">
                                                <?= date('d/m/Y', strtotime($item['expiry_date'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-days">
                                        <?php if (!empty($item['expiry_date']) && $days !== '-'): ?>
                                            <span class="days-remaining <?= $days_class ?>">
                                                <?php if ($days < 0): ?>
                                                    <i class="fas fa-skull"></i> EXP
                                                <?php elseif ($days <= 30): ?>
                                                    <i class="fas fa-clock"></i> <?= $days ?>d
                                                <?php else: ?>
                                                    <i class="fas fa-check"></i> <?= $days ?>d
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-batch">
                                        <?php if (!empty($item['batch_number'])): ?>
                                            <span class="batch-number"><?= htmlspecialchars($item['batch_number']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-status">
                                        <span class="status-badge <?= $item['status'] ?? 'active' ?>">
                                            <?= ucfirst($item['status'] ?? 'Active') ?>
                                        </span>
                                    </td>
                                    <td class="col-actions">
                                        <a href="inventory.php?tab=medicines&view=<?= $item['id'] ?>&type=medicine" class="action-btn view">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-pills"></i>
                    <p>No medicines found</p>
                    <p class="sub">Click "Add Medicine" to get started</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TAB CONTENT: EQUIPMENT -->
    <!-- ================================================================ -->
    <div id="tab-equipment" class="tab-content <?= $active_tab === 'equipment' ? 'active' : '' ?>">
        
        <!-- Equipment Stats -->
        <div class="stats-grid animate-fade-in-up">
            <a href="inventory.php?tab=equipment" class="stat-card blue">
                <span class="stat-icon"><i class="fas fa-tools"></i></span>
                <div class="stat-number"><?= $total_equipment ?></div>
                <div class="stat-label">Total Equipment</div>
                <div class="stat-value">🔧 <?= formatMoneyShort($equip_value) ?></div>
            </a>
            <a href="inventory.php?tab=equipment&stock=low" class="stat-card orange">
                <span class="stat-icon"><i class="fas fa-exclamation-triangle"></i></span>
                <div class="stat-number"><?= $equip_low_stock ?></div>
                <div class="stat-label">Low Stock</div>
                <div class="stat-sub">Below reorder level</div>
            </a>
            <a href="inventory.php?tab=equipment&stock=out" class="stat-card red">
                <span class="stat-icon"><i class="fas fa-times-circle"></i></span>
                <div class="stat-number"><?= $equip_out_of_stock ?></div>
                <div class="stat-label">Out of Stock</div>
                <div class="stat-sub">Quantity = 0</div>
            </a>
            <a href="inventory.php?tab=equipment&expiry=expiring" class="stat-card teal">
                <span class="stat-icon"><i class="fas fa-clock"></i></span>
                <div class="stat-number"><?= $equip_expiring ?></div>
                <div class="stat-label">Expiring Soon</div>
                <div class="stat-sub">Within 30 days</div>
            </a>
            <a href="inventory.php?tab=equipment&expiry=expired" class="stat-card red">
                <span class="stat-icon"><i class="fas fa-skull"></i></span>
                <div class="stat-number"><?= $equip_expired ?></div>
                <div class="stat-label">Expired</div>
                <div class="stat-sub">Past expiry date</div>
            </a>
            <a href="inventory.php?tab=equipment&status=active" class="stat-card green">
                <span class="stat-icon"><i class="fas fa-check-circle"></i></span>
                <div class="stat-number"><?= $equip_in_stock ?></div>
                <div class="stat-label">In Stock</div>
                <div class="stat-sub">Available</div>
            </a>
            <a href="inventory.php?tab=equipment&status=inactive" class="stat-card purple">
                <span class="stat-icon"><i class="fas fa-archive"></i></span>
                <div class="stat-number"><?= $equip_inactive ?></div>
                <div class="stat-label">Inactive</div>
                <div class="stat-sub">Not available</div>
            </a>
        </div>

        <!-- Filters -->
        <div class="card animate-fade-in-up">
            <div class="filter-group">
                <a href="inventory.php?tab=equipment" class="filter-btn <?= empty($status_filter) && empty($stock_filter) && empty($expiry_filter) ? 'active' : '' ?>">All</a>
                <a href="inventory.php?tab=equipment&status=active" class="filter-btn <?= $status_filter === 'active' ? 'active' : '' ?>">Active</a>
                <a href="inventory.php?tab=equipment&status=inactive" class="filter-btn <?= $status_filter === 'inactive' ? 'active' : '' ?>">Inactive</a>
                <a href="inventory.php?tab=equipment&stock=low" class="filter-btn <?= $stock_filter === 'low' ? 'active' : '' ?>">Low Stock</a>
                <a href="inventory.php?tab=equipment&stock=out" class="filter-btn <?= $stock_filter === 'out' ? 'active' : '' ?>">Out of Stock</a>
                <a href="inventory.php?tab=equipment&expiry=expiring" class="filter-btn <?= $expiry_filter === 'expiring' ? 'active' : '' ?>">Expiring Soon</a>
                <a href="inventory.php?tab=equipment&expiry=expired" class="filter-btn expired-filter <?= $expiry_filter === 'expired' ? 'active' : '' ?>">
                    <i class="fas fa-skull"></i> Expired
                </a>
                <?php if (!empty($stock_filter) || !empty($expiry_filter) || !empty($status_filter)): ?>
                    <a href="inventory.php?tab=equipment" class="filter-btn clear-filter">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
            
            <form method="GET" class="search-form">
                <input type="hidden" name="tab" value="equipment">
                <input type="text" name="search" placeholder="🔍 Search equipment..." value="<?= htmlspecialchars($search) ?>">
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($equip_categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-search"><i class="fas fa-search"></i> Filter</button>
                <a href="inventory.php?tab=equipment" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </form>
        </div>

        <!-- Equipment Table -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list title-purple"></i> Equipment List
                    <span class="result-count">(<strong><?= count($equipment) ?></strong> records)</span>
                    <?php if ($total_equipment > 0): ?>
                        <span class="result-count ml-2">Total Value: <strong>TSh <?= formatMoney($equip_value) ?></strong></span>
                    <?php endif; ?>
                </h3>
            </div>
            
            <?php if (count($equipment) > 0): ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="col-sno">#</th>
                                <th class="col-name">Name</th>
                                <th class="col-category">Category</th>
                                <th class="col-qty">Qty</th>
                                <th class="col-reorder">Reorder</th>
                                <th class="col-stock">Stock</th>
                                <th class="col-price">Price (TSh)</th>
                                <th class="col-expiry">Expiry</th>
                                <th class="col-days">Days</th>
                                <th class="col-batch">Batch</th>
                                <th class="col-status">Status</th>
                                <th class="col-actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($equipment as $item): ?>
                                <?php
                                    $stock_status = 'ok';
                                    $stock_label = 'In Stock';
                                    if ($item['quantity'] <= 0) {
                                        $stock_status = 'out';
                                        $stock_label = 'Out of Stock';
                                    } elseif ($item['quantity'] <= $item['reorder_level']) {
                                        $stock_status = 'low';
                                        $stock_label = 'Low Stock';
                                    }
                                    
                                    $expiry_status = 'valid';
                                    $days = '-';
                                    $days_class = 'good';
                                    if (!empty($item['expiry_date'])) {
                                        $days = $item['days_remaining'];
                                        if ($days < 0) {
                                            $expiry_status = 'expired';
                                            $days_class = 'danger';
                                        } elseif ($days <= 30) {
                                            $expiry_status = 'expiring';
                                            $days_class = 'warning';
                                        }
                                    }
                                ?>
                                <tr>
                                    <td class="col-sno"><?= $counter++ ?></td>
                                    <td class="col-name">
                                        <strong><?= htmlspecialchars($item['equipment_name']) ?></strong>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></div>
                                    </td>
                                    <td class="col-category"><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                    <td class="col-qty"><strong><?= $item['quantity'] ?></strong></td>
                                    <td class="col-reorder"><?= $item['reorder_level'] ?></td>
                                    <td class="col-stock">
                                        <span class="stock-badge <?= $stock_status ?>">
                                            <i class="fas <?= $stock_status === 'ok' ? 'fa-check-circle' : ($stock_status === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                                            <?= $stock_label ?>
                                        </span>
                                    </td>
                                    <td class="col-price">
                                        <span class="money-amount">
                                            <span class="currency-symbol">TSh</span>
                                            <span class="amount"><?= formatMoney($item['selling_price'] ?? 0) ?></span>
                                        </span>
                                    </td>
                                    <td class="col-expiry">
                                        <?php if (!empty($item['expiry_date'])): ?>
                                            <span class="expiry-badge <?= $expiry_status ?>">
                                                <?= date('d/m/Y', strtotime($item['expiry_date'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-days">
                                        <?php if (!empty($item['expiry_date']) && $days !== '-'): ?>
                                            <span class="days-remaining <?= $days_class ?>">
                                                <?php if ($days < 0): ?>
                                                    <i class="fas fa-skull"></i> EXP
                                                <?php elseif ($days <= 30): ?>
                                                    <i class="fas fa-clock"></i> <?= $days ?>d
                                                <?php else: ?>
                                                    <i class="fas fa-check"></i> <?= $days ?>d
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-batch">
                                        <?php if (!empty($item['batch_number'])): ?>
                                            <span class="batch-number"><?= htmlspecialchars($item['batch_number']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-status">
                                        <span class="status-badge <?= $item['status'] ?? 'active' ?>">
                                            <?= ucfirst($item['status'] ?? 'Active') ?>
                                        </span>
                                    </td>
                                    <td class="col-actions">
                                        <a href="inventory.php?tab=equipment&view=<?= $item['id'] ?>&type=equipment" class="action-btn view">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tools"></i>
                    <p>No equipment found</p>
                    <p class="sub">Click "Add Equipment" to get started</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VIEW MODAL -->
    <!-- ================================================================ -->
    <?php if ($view_data): ?>
    <div class="modal-overlay show" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-eye"></i> 
                    <?= $view_type === 'medicine' ? 'Medicine' : 'Equipment' ?> Details
                </div>
                <a href="inventory.php?tab=<?= $active_tab ?>" class="modal-close">&times;</a>
            </div>
            
            <div class="view-grid">
                <div class="view-item full-width">
                    <div class="label">Name</div>
                    <div class="value"><?= htmlspecialchars($view_data['medication_name'] ?? $view_data['equipment_name'] ?? '') ?></div>
                </div>
                <div class="view-item">
                    <div class="label">Category</div>
                    <div class="value"><?= htmlspecialchars($view_data['category'] ?? 'N/A') ?></div>
                </div>
                <div class="view-item">
                    <div class="label">Unit</div>
                    <div class="value"><?= htmlspecialchars($view_data['unit'] ?? 'pcs') ?></div>
                </div>
                <div class="view-item">
                    <div class="label">Quantity</div>
                    <div class="value">
                        <strong><?= $view_data['quantity'] ?></strong>
                        <?php if ($view_data['quantity'] <= 0): ?>
                            <span class="stock-badge out">Out of Stock</span>
                        <?php elseif ($view_data['quantity'] <= $view_data['reorder_level']): ?>
                            <span class="stock-badge low">Low Stock</span>
                        <?php else: ?>
                            <span class="stock-badge ok">In Stock</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="view-item">
                    <div class="label">Reorder Level</div>
                    <div class="value"><?= $view_data['reorder_level'] ?></div>
                </div>
                <div class="view-item">
                    <div class="label">Buying Price</div>
                    <div class="value">
                        <span class="money-amount">
                            <span class="currency-symbol">TSh</span>
                            <span class="amount"><?= formatMoney($view_data['unit_cost'] ?? 0) ?></span>
                        </span>
                    </div>
                </div>
                <div class="view-item">
                    <div class="label">Selling Price</div>
                    <div class="value">
                        <span class="money-amount">
                            <span class="currency-symbol">TSh</span>
                            <span class="amount"><?= formatMoney($view_data['selling_price'] ?? 0) ?></span>
                        </span>
                    </div>
                </div>
                <div class="view-item">
                    <div class="label">Supplier</div>
                    <div class="value"><?= htmlspecialchars($view_data['supplier'] ?? 'N/A') ?></div>
                </div>
                <div class="view-item">
                    <div class="label">Batch Number</div>
                    <div class="value">
                        <?php if (!empty($view_data['batch_number'])): ?>
                            <span class="batch-number"><?= htmlspecialchars($view_data['batch_number']) ?></span>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                <div class="view-item">
                    <div class="label">Expiry Date</div>
                    <div class="value">
                        <?php if (!empty($view_data['expiry_date'])): ?>
                            <?php 
                                $days_left = (strtotime($view_data['expiry_date']) - time()) / 86400;
                            ?>
                            <span class="expiry-badge <?= $days_left < 0 ? 'expired' : ($days_left <= 30 ? 'expiring' : 'valid') ?>">
                                <?= date('d/m/Y', strtotime($view_data['expiry_date'])) ?>
                            </span>
                            <?php if ($days_left >= 0): ?>
                                <div class="days-remaining <?= $days_left <= 30 ? 'warning' : 'good' ?>">
                                    <?= round($days_left) ?> days remaining
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                <div class="view-item">
                    <div class="label">Status</div>
                    <div class="value">
                        <span class="status-badge <?= $view_data['status'] ?? 'active' ?>">
                            <?= ucfirst($view_data['status'] ?? 'Active') ?>
                        </span>
                    </div>
                </div>
                <div class="view-item full-width">
                    <div class="label">Branch</div>
                    <div class="value">
                        <span class="branch-tag" style="background:var(--primary-light);color:var(--primary);padding:2px 12px;border-radius:12px;font-size:0.75rem;">
                            <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="inventory.php?tab=<?= $active_tab ?>" class="btn-cancel">
                    <i class="fas fa-times"></i> Close
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ADD MEDICINE MODAL -->
    <!-- ================================================================ -->
    <div class="modal-overlay" id="addMedicineModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-pills"></i> Add New Medicine
                </div>
                <button class="modal-close" onclick="closeModal('addMedicineModal')">&times;</button>
            </div>
            
            <form method="POST" action="" id="addMedicineForm">
                <input type="hidden" name="action" value="add_medicine">
                
                <div class="form-grid">
                    <div class="full-width form-row">
                        <label class="form-label">Medicine Name <span class="required">*</span></label>
                        <input type="text" name="medication_name" class="form-control" placeholder="e.g. Paracetamol 500mg" required>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Category</label>
                        <div class="category-input-group">
                            <select name="category" id="medCategorySelect" class="form-control">
                                <option value="">Select</option>
                                <?php foreach ($predefined_med_categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                                <option value="__other__">+ Other</option>
                            </select>
                            <input type="text" name="category_manual" id="medCategoryManual" class="form-control" placeholder="Custom category..." style="display:none;">
                            <button type="button" class="btn-toggle" onclick="toggleCategory('med')">
                                <i class="fas fa-edit"></i> Manual
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Unit</label>
                        <select name="unit" class="form-control">
                            <option value="pcs">Pieces (pcs)</option>
                            <option value="tablets">Tablets</option>
                            <option value="capsules">Capsules</option>
                            <option value="ml">Milliliters (ml)</option>
                            <option value="mg">Milligrams (mg)</option>
                            <option value="g">Grams (g)</option>
                            <option value="bottle">Bottle</option>
                            <option value="box">Box</option>
                            <option value="strip">Strip</option>
                            <option value="vial">Vial</option>
                            <option value="sachet">Sachet</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Quantity <span class="required">*</span></label>
                        <input type="number" name="quantity" class="form-control" placeholder="0" min="0" required>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Reorder Level <span class="required">*</span></label>
                        <input type="number" name="reorder_level" class="form-control" value="10" min="0" required>
                        <div class="help-text">Alert when stock reaches this level</div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Buying Price (TSh)</label>
                        <input type="text" name="unit_cost" class="form-control money-input" 
                               placeholder="0" value="0">
                        <div class="help-text">Auto-format with commas (e.g., 1,000,000)</div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Selling Price (TSh) <span class="required">*</span></label>
                        <input type="text" name="selling_price" class="form-control money-input" 
                               placeholder="0" value="0" required>
                        <div class="help-text">Auto-format with commas (e.g., 1,000,000) | 0 = Free</div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Supplier</label>
                        <input type="text" name="supplier" class="form-control" placeholder="Supplier name">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control">
                        <div class="help-text">System will show days remaining</div>
                    </div>
                    
                    <div class="full-width form-row">
                        <label class="form-label">Batch Number</label>
                        <div class="batch-input-group">
                            <input type="text" name="batch_number" id="medBatchInput" class="form-control" 
                                   placeholder="BATCH-YYYYMMDD-XXXX" 
                                   value="<?= 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)) ?>">
                            <button type="button" class="btn-generate" onclick="generateBatch('med')">
                                <i class="fas fa-sync-alt"></i> Generate
                            </button>
                        </div>
                        <div class="help-text">Auto-generated. Click "Generate" for a new batch number.</div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Medicine</button>
                    <button type="button" class="btn-cancel" onclick="closeModal('addMedicineModal')"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ADD EQUIPMENT MODAL -->
    <!-- ================================================================ -->
    <div class="modal-overlay" id="addEquipmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-tools"></i> Add New Equipment
                </div>
                <button class="modal-close" onclick="closeModal('addEquipmentModal')">&times;</button>
            </div>
            
            <form method="POST" action="" id="addEquipmentForm">
                <input type="hidden" name="action" value="add_equipment">
                
                <div class="form-grid">
                    <div class="full-width form-row">
                        <label class="form-label">Equipment Name <span class="required">*</span></label>
                        <input type="text" name="equipment_name" class="form-control" placeholder="e.g. Stethoscope" required>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Category</label>
                        <div class="category-input-group">
                            <select name="category" id="equipCategorySelect" class="form-control">
                                <option value="">Select</option>
                                <?php foreach ($predefined_equip_categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                                <option value="__other__">+ Other</option>
                            </select>
                            <input type="text" name="category_manual" id="equipCategoryManual" class="form-control" placeholder="Custom category..." style="display:none;">
                            <button type="button" class="btn-toggle" onclick="toggleCategory('equip')">
                                <i class="fas fa-edit"></i> Manual
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Unit</label>
                        <select name="unit" class="form-control">
                            <option value="pcs">Pieces (pcs)</option>
                            <option value="set">Set</option>
                            <option value="box">Box</option>
                            <option value="pack">Pack</option>
                            <option value="each">Each</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Quantity <span class="required">*</span></label>
                        <input type="number" name="quantity" class="form-control" placeholder="0" min="0" required>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Reorder Level <span class="required">*</span></label>
                        <input type="number" name="reorder_level" class="form-control" value="5" min="0" required>
                        <div class="help-text">Alert when stock reaches this level</div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Buying Price (TSh)</label>
                        <input type="text" name="unit_cost" class="form-control money-input" 
                               placeholder="0" value="0">
                        <div class="help-text">Auto-format with commas (e.g., 1,000,000)</div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Selling Price (TSh) <span class="required">*</span></label>
                        <input type="text" name="selling_price" class="form-control money-input" 
                               placeholder="0" value="0" required>
                        <div class="help-text">Auto-format with commas (e.g., 1,000,000) | 0 = Free</div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Supplier</label>
                        <input type="text" name="supplier" class="form-control" placeholder="Supplier name">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control">
                        <div class="help-text">System will show days remaining</div>
                    </div>
                    
                    <div class="full-width form-row">
                        <label class="form-label">Batch Number</label>
                        <div class="batch-input-group">
                            <input type="text" name="batch_number" id="equipBatchInput" class="form-control" 
                                   placeholder="EQP-YYYYMMDD-XXXX" 
                                   value="<?= 'EQP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)) ?>">
                            <button type="button" class="btn-generate" onclick="generateBatch('equip')">
                                <i class="fas fa-sync-alt"></i> Generate
                            </button>
                        </div>
                        <div class="help-text">Auto-generated. Click "Generate" for a new batch number.</div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Equipment</button>
                    <button type="button" class="btn-cancel" onclick="closeModal('addEquipmentModal')"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-400 mx-2">|</span>
            Inventory Management
            <span class="text-gray-400 mx-2">|</span>
            Total Value: <strong>TSh <?= formatMoney($total_inventory_value) ?></strong>
            <span class="text-gray-400 mx-2">|</span>
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
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- COMPLETE JAVASCRIPT WITH AUTO-MONEY FORMAT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // AUTO MONEY FORMAT - COMMA ON TYPING
    // ================================================================
    (function() {
        'use strict';
        
        // Format number with commas
        function formatWithCommas(value) {
            if (!value) return '';
            
            var clean = value.toString().replace(/[^0-9.]/g, '');
            var parts = clean.split('.');
            var integerPart = parts[0] || '0';
            var decimalPart = parts.length > 1 ? '.' + parts[1] : '';
            
            if (integerPart.length > 3) {
                var formatted = '';
                var counter = 0;
                for (var i = integerPart.length - 1; i >= 0; i--) {
                    counter++;
                    formatted = integerPart[i] + formatted;
                    if (counter % 3 === 0 && i !== 0) {
                        formatted = ',' + formatted;
                    }
                }
                integerPart = formatted;
            }
            
            return integerPart + decimalPart;
        }
        
        function removeCommas(value) {
            if (!value) return '';
            return value.replace(/,/g, '');
        }
        
        function getRawValue(input) {
            if (!input || !input.value) return 0;
            return parseFloat(removeCommas(input.value)) || 0;
        }
        
        function autoFormat(input) {
            if (!input) return;
            
            var cursorPos = input.selectionStart;
            var lengthBefore = input.value.length;
            var formatted = formatWithCommas(input.value);
            
            if (formatted !== input.value) {
                input.value = formatted;
                var lengthAfter = formatted.length;
                var diff = lengthAfter - lengthBefore;
                input.setSelectionRange(cursorPos + diff, cursorPos + diff);
            }
        }
        
        function initMoneyInputs() {
            var moneyInputs = document.querySelectorAll('.money-input');
            
            moneyInputs.forEach(function(input) {
                if (input.dataset.moneyInitialized) return;
                input.dataset.moneyInitialized = 'true';
                
                // On input - format as user types
                input.addEventListener('input', function(e) {
                    autoFormat(this);
                });
                
                // On focus - remove commas for editing
                input.addEventListener('focus', function(e) {
                    if (this.value) {
                        var raw = removeCommas(this.value);
                        this.value = raw;
                    }
                    this.select();
                });
                
                // On blur - format with commas
                input.addEventListener('blur', function(e) {
                    if (this.value) {
                        this.value = formatWithCommas(this.value);
                    } else {
                        this.value = '0';
                    }
                });
                
                // On paste - clean and format
                input.addEventListener('paste', function(e) {
                    var clipboardData = e.clipboardData || window.clipboardData;
                    var pastedData = clipboardData.getData('text');
                    
                    if (pastedData) {
                        e.preventDefault();
                        var clean = pastedData.replace(/[^0-9.]/g, '');
                        if (clean) {
                            this.value = formatWithCommas(clean);
                            autoFormat(this);
                        }
                    }
                });
                
                // Restrict input to numbers and decimal
                input.addEventListener('keydown', function(e) {
                    var keys = [8, 9, 13, 27, 35, 36, 37, 38, 39, 40, 46];
                    if (keys.indexOf(e.keyCode) !== -1) {
                        return;
                    }
                    if (e.ctrlKey && ['a', 'c', 'v', 'x'].indexOf(e.key.toLowerCase()) !== -1) {
                        return;
                    }
                    if (!/[0-9.]/.test(e.key) && e.key !== 'Backspace') {
                        e.preventDefault();
                    }
                    if (e.key === '.' && this.value.indexOf('.') !== -1) {
                        e.preventDefault();
                    }
                });
            });
        }
        
        // Initialize on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initMoneyInputs);
        } else {
            initMoneyInputs();
        }
        
        // Re-initialize when modals open
        document.addEventListener('modalOpened', function() {
            setTimeout(initMoneyInputs, 150);
        });
        
        // Observer for dynamic inputs
        var observer = new MutationObserver(function() {
            setTimeout(initMoneyInputs, 100);
        });
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Make functions globally available
        window.moneyFormat = {
            format: formatWithCommas,
            removeCommas: removeCommas,
            getRawValue: getRawValue,
            init: initMoneyInputs
        };
        
        console.log('%c💰 Auto Money Format initialized', 'font-size:13px; color:#D97706;');
        console.log('%c📝 Type numbers and commas will appear automatically', 'font-size:13px; color:#059669;');
    })();

    // ================================================================
    // TAB SWITCHING
    // ================================================================
    function switchTab(tab) {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        url.searchParams.delete('search');
        url.searchParams.delete('category');
        url.searchParams.delete('status');
        url.searchParams.delete('stock');
        url.searchParams.delete('expiry');
        url.searchParams.delete('view');
        window.location.href = url.toString();
    }

    // ================================================================
    // MODAL FUNCTIONS
    // ================================================================
    function openAddModal(type) {
        var modalId = type === 'medicine' ? 'addMedicineModal' : 'addEquipmentModal';
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Trigger event for money format initialization
            var event = new CustomEvent('modalOpened');
            document.dispatchEvent(event);
        }
    }
    
    function closeModal(id) {
        var modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    }
    
    document.querySelectorAll('.modal-overlay').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        });
    });

    // ================================================================
    // CATEGORY TOGGLE
    // ================================================================
    function toggleCategory(type) {
        var select, manual, btn;
        
        if (type === 'med') {
            select = document.getElementById('medCategorySelect');
            manual = document.getElementById('medCategoryManual');
            btn = document.querySelector('#addMedicineModal .category-input-group .btn-toggle');
        } else {
            select = document.getElementById('equipCategorySelect');
            manual = document.getElementById('equipCategoryManual');
            btn = document.querySelector('#addEquipmentModal .category-input-group .btn-toggle');
        }
        
        if (!select || !manual || !btn) return;
        
        if (manual.style.display === 'none') {
            manual.style.display = 'block';
            select.style.display = 'none';
            btn.innerHTML = '<i class="fas fa-list"></i> Select';
            manual.focus();
        } else {
            manual.style.display = 'none';
            select.style.display = 'block';
            btn.innerHTML = '<i class="fas fa-edit"></i> Manual';
        }
    }

    // ================================================================
    // CATEGORY SELECT CHANGE
    // ================================================================
    document.getElementById('medCategorySelect')?.addEventListener('change', function() {
        if (this.value === '__other__') {
            document.getElementById('medCategoryManual').style.display = 'block';
            document.getElementById('medCategoryManual').focus();
        }
    });
    
    document.getElementById('equipCategorySelect')?.addEventListener('change', function() {
        if (this.value === '__other__') {
            document.getElementById('equipCategoryManual').style.display = 'block';
            document.getElementById('equipCategoryManual').focus();
        }
    });

    // ================================================================
    // GENERATE BATCH NUMBER
    // ================================================================
    function generateBatch(type) {
        var now = new Date();
        var dateStr = now.getFullYear() + 
                      String(now.getMonth() + 1).padStart(2, '0') + 
                      String(now.getDate()).padStart(2, '0');
        var random = Math.random().toString(36).substring(2, 8).toUpperCase();
        
        var prefix = type === 'med' ? 'BATCH' : 'EQP';
        var batch = prefix + '-' + dateStr + '-' + random;
        
        var inputId = type === 'med' ? 'medBatchInput' : 'equipBatchInput';
        var input = document.getElementById(inputId);
        if (input) {
            input.value = batch;
        }
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') {
            return;
        }
        
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.querySelector('.search-form input[type="text"]');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.show').forEach(function(modal) {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
            });
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DARK MODE SYNC
    // ================================================================
    document.addEventListener('darkModeChanged', function(e) {
        var isDark = e.detail && e.detail.isDark;
        var html = document.documentElement;
        
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }
    });

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('%c💊 Braick - Complete Inventory', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Medicines: <?= $total_medicines ?> | Equipment: <?= $total_equipment ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total Value: TSh <?= formatMoney($total_inventory_value) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c✅ Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Auto-Money Format: Type numbers and commas appear automatically', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Price accepts 0 (Free)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>