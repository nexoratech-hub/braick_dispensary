<?php
// ================================================================
// FILE: frontend/pages/pharmacy/inventory.php
// PHARMACY - COMPLETE INVENTORY V8.9.1
// ✅ Modal ipo MBELE ya sidebar (z-index 999999)
// ✅ Batch count badge ni BLUE
// ✅ Stock low, days warning, expiry expiring ni BLUE
// ✅ Table header search (auto-filter)
// ✅ View modal inaonyesha batches zote + added by
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

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

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

function formatMoney($amount) {
    if ($amount === null || $amount === '') return '0.00';
    return number_format((float)$amount, 2, '.', ',');
}
function formatMoneyNoDecimal($amount) {
    if ($amount === null || $amount === '') return '0';
    return number_format((float)$amount, 0, '.', ',');
}
function formatMoneyShort($amount) {
    if ($amount === null || $amount === '') return '0';
    $amount = (float)$amount;
    if ($amount >= 1000000000) return number_format($amount / 1000000000, 1) . 'B';
    if ($amount >= 1000000) return number_format($amount / 1000000, 1) . 'M';
    if ($amount >= 1000) return number_format($amount / 1000, 1) . 'K';
    return number_format($amount, 0);
}
function cleanMoney($value) { return str_replace(',', '', $value); }
function getMoney($value) { return floatval(cleanMoney($value)); }

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// SAFE AUTO-MIGRATION
// ================================================================
try {
    $stmt = $db->query("SHOW COLUMNS FROM medications_inventory LIKE 'added_by'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `medications_inventory` ADD COLUMN `added_by` INT NULL AFTER `status`");
        $db->exec("ALTER TABLE `medications_inventory` ADD COLUMN `added_by_name` VARCHAR(100) NULL AFTER `added_by`");
    }
} catch (Exception $e) {}

try {
    $stmt = $db->query("SHOW COLUMNS FROM medical_equipment LIKE 'added_by'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `medical_equipment` ADD COLUMN `added_by` INT NULL AFTER `status`");
        $db->exec("ALTER TABLE `medical_equipment` ADD COLUMN `added_by_name` VARCHAR(100) NULL AFTER `added_by`");
    }
} catch (Exception $e) {}

try {
    $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'branch_id'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `purchases` ADD COLUMN `branch_id` INT NULL AFTER `created_by_name`");
        $db->exec("UPDATE `purchases` SET `branch_id` = 1 WHERE `branch_id` IS NULL");
    }
} catch (Exception $e) {}

try {
    $stmt = $db->query("SHOW INDEX FROM purchases WHERE Key_name = 'invoice_number' AND Non_unique = 0");
    if ($stmt->rowCount() > 0) {
        $db->exec("ALTER TABLE `purchases` DROP INDEX `invoice_number`");
        try {
            $db->exec("ALTER TABLE `purchases` ADD INDEX `idx_invoice_number` (`invoice_number`)");
        } catch (Exception $ex) {}
    }
} catch (Exception $e) {}

try {
    $stmt = $db->query("SHOW INDEX FROM purchases WHERE Key_name = 'uk_invoice_branch'");
    if ($stmt->rowCount() > 0) {
        $db->exec("ALTER TABLE `purchases` DROP INDEX `uk_invoice_branch`");
    }
} catch (Exception $e) {}

// ================================================================
// GET CATEGORIES
// ================================================================
$med_categories = [];
$stmt = $db->query("SELECT DISTINCT category FROM medications_inventory WHERE category IS NOT NULL AND category != '' AND category != '__other__' ORDER BY category");
$med_categories = $stmt->fetchAll();

$equip_categories = [];
$stmt = $db->query("SELECT DISTINCT category FROM medical_equipment WHERE category IS NOT NULL AND category != '' AND category != '__other__' ORDER BY category");
$equip_categories = $stmt->fetchAll();

$message = '';
$message_type = '';

if (isset($_SESSION['purchase_message'])) {
    $message = $_SESSION['purchase_message'];
    $message_type = $_SESSION['purchase_message_type'] ?? 'success';
    unset($_SESSION['purchase_message']);
    unset($_SESSION['purchase_message_type']);
}

if (isset($_SESSION['inventory_message'])) {
    $message = $_SESSION['inventory_message'];
    $message_type = $_SESSION['inventory_message_type'] ?? 'success';
    unset($_SESSION['inventory_message']);
    unset($_SESSION['inventory_message_type']);
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'medicines';
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_type = isset($_GET['type']) ? $_GET['type'] : 'medicine';

$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$stock_filter = isset($_GET['stock']) ? trim($_GET['stock']) : '';
$expiry_filter = isset($_GET['expiry']) ? trim($_GET['expiry']) : '';

// ================================================================
// MEDICINES QUERY
// ================================================================
$med_query = "
    SELECT 
        MIN(m.id) as id, m.medication_name, m.category, m.unit, m.branch_id, m.added_by, m.added_by_name,
        u.full_name as added_by_full_name,
        SUM(CASE WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE() OR m.expiry_date = '0000-00-00') THEN m.quantity ELSE 0 END) as total_quantity,
        MIN(m.reorder_level) as reorder_level, MIN(m.unit_cost) as unit_cost, MIN(m.selling_price) as selling_price,
        MIN(m.supplier) as supplier, MIN(m.expiry_date) as expiry_date,
        GROUP_CONCAT(m.id) as batch_ids, GROUP_CONCAT(m.batch_number SEPARATOR '|') as batch_numbers,
        GROUP_CONCAT(m.quantity SEPARATOR '|') as batch_quantities, GROUP_CONCAT(m.expiry_date SEPARATOR '|') as batch_expiries,
        GROUP_CONCAT(m.status SEPARATOR '|') as batch_statuses,
        GROUP_CONCAT(m.category SEPARATOR '|') as batch_categories,
        GROUP_CONCAT(m.supplier SEPARATOR '|') as batch_suppliers,
        GROUP_CONCAT(m.added_by_name SEPARATOR '|') as batch_added_bys,
        MIN(DATEDIFF(CASE WHEN m.expiry_date = '0000-00-00' THEN NULL ELSE m.expiry_date END, CURDATE())) as days_remaining,
        CASE 
            WHEN SUM(CASE WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE() OR m.expiry_date = '0000-00-00') THEN 1 ELSE 0 END) > 0 
            THEN 'active' 
            ELSE 'inactive' 
        END as computed_status
    FROM medications_inventory m
    LEFT JOIN users u ON m.added_by = u.id
    WHERE m.branch_id = ?
";
$med_params = [$user_branch_id];

if (!empty($category_filter)) { 
    $med_query .= " AND m.category = ?"; 
    $med_params[] = $category_filter; 
}

if ($expiry_filter === 'expiring') { 
    $med_query .= " AND m.expiry_date IS NOT NULL AND m.expiry_date != '0000-00-00' AND m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"; 
}
if ($expiry_filter === 'expired') { 
    $med_query .= " AND m.expiry_date IS NOT NULL AND m.expiry_date != '0000-00-00' AND m.expiry_date < CURDATE()"; 
}

$med_query .= " GROUP BY m.medication_name, m.category, m.unit, m.branch_id";

if ($status_filter === 'active') { 
    $med_query .= " HAVING computed_status = 'active'"; 
} elseif ($status_filter === 'inactive') { 
    $med_query .= " HAVING computed_status = 'inactive'"; 
}

if ($stock_filter === 'low') { 
    $med_query .= " HAVING total_quantity > 0 AND total_quantity <= reorder_level AND computed_status = 'active'"; 
} elseif ($stock_filter === 'out') { 
    $med_query .= " HAVING total_quantity = 0 AND computed_status = 'active'"; 
}

$med_query .= " ORDER BY m.medication_name ASC";

$stmt = $db->prepare($med_query);
$stmt->execute($med_params);
$medicines = $stmt->fetchAll();

// ================================================================
// EQUIPMENT QUERY
// ================================================================
$equip_query = "
    SELECT 
        MIN(e.id) as id, e.equipment_name, e.category, e.unit, e.branch_id, e.added_by, e.added_by_name,
        u.full_name as added_by_full_name,
        SUM(CASE WHEN e.status = 'active' AND (e.expiry_date IS NULL OR e.expiry_date >= CURDATE() OR e.expiry_date = '0000-00-00') THEN e.quantity ELSE 0 END) as total_quantity,
        MIN(e.reorder_level) as reorder_level, MIN(e.unit_cost) as unit_cost, MIN(e.selling_price) as selling_price,
        MIN(e.supplier) as supplier, MIN(e.expiry_date) as expiry_date,
        GROUP_CONCAT(e.id) as batch_ids, GROUP_CONCAT(e.batch_number SEPARATOR '|') as batch_numbers,
        GROUP_CONCAT(e.quantity SEPARATOR '|') as batch_quantities, GROUP_CONCAT(e.expiry_date SEPARATOR '|') as batch_expiries,
        GROUP_CONCAT(e.status SEPARATOR '|') as batch_statuses,
        GROUP_CONCAT(e.category SEPARATOR '|') as batch_categories,
        GROUP_CONCAT(e.supplier SEPARATOR '|') as batch_suppliers,
        GROUP_CONCAT(e.added_by_name SEPARATOR '|') as batch_added_bys,
        MIN(DATEDIFF(CASE WHEN e.expiry_date = '0000-00-00' THEN NULL ELSE e.expiry_date END, CURDATE())) as days_remaining,
        CASE 
            WHEN SUM(CASE WHEN e.status = 'active' AND (e.expiry_date IS NULL OR e.expiry_date >= CURDATE() OR e.expiry_date = '0000-00-00') THEN 1 ELSE 0 END) > 0 
            THEN 'active' 
            ELSE 'inactive' 
        END as computed_status
    FROM medical_equipment e
    LEFT JOIN users u ON e.added_by = u.id
    WHERE e.branch_id = ?
";
$equip_params = [$user_branch_id];

if (!empty($category_filter)) { 
    $equip_query .= " AND e.category = ?"; 
    $equip_params[] = $category_filter; 
}

if ($expiry_filter === 'expiring') { 
    $equip_query .= " AND e.expiry_date IS NOT NULL AND e.expiry_date != '0000-00-00' AND e.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"; 
}
if ($expiry_filter === 'expired') { 
    $equip_query .= " AND e.expiry_date IS NOT NULL AND e.expiry_date != '0000-00-00' AND e.expiry_date < CURDATE()"; 
}

$equip_query .= " GROUP BY e.equipment_name, e.category, e.unit, e.branch_id";

if ($status_filter === 'active') { 
    $equip_query .= " HAVING computed_status = 'active'"; 
} elseif ($status_filter === 'inactive') { 
    $equip_query .= " HAVING computed_status = 'inactive'"; 
}

if ($stock_filter === 'low') { 
    $equip_query .= " HAVING total_quantity > 0 AND total_quantity <= reorder_level AND computed_status = 'active'"; 
} elseif ($stock_filter === 'out') { 
    $equip_query .= " HAVING total_quantity = 0 AND computed_status = 'active'"; 
}

$equip_query .= " ORDER BY e.equipment_name ASC";

$stmt = $db->prepare($equip_query);
$stmt->execute($equip_params);
$equipment = $stmt->fetchAll();

// ================================================================
// STATISTICS - MEDICINES
// ================================================================
$stmt = $db->prepare("SELECT COUNT(DISTINCT medication_name) as count FROM medications_inventory WHERE branch_id = ? AND status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')");
$stmt->execute([$user_branch_id]);
$total_medicines = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(DISTINCT medication_name) as count FROM medications_inventory WHERE branch_id = ? AND status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') AND quantity > 0");
$stmt->execute([$user_branch_id]);
$med_in_stock = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(DISTINCT medication_name) as count FROM medications_inventory WHERE branch_id = ? AND status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') AND quantity = 0");
$stmt->execute([$user_branch_id]);
$med_out_of_stock = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(DISTINCT medication_name) as count FROM medications_inventory WHERE branch_id = ? AND status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') AND quantity > 0 AND quantity <= reorder_level");
$stmt->execute([$user_branch_id]);
$med_low_stock = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(DISTINCT medication_name) as count FROM medications_inventory WHERE branch_id = ? AND expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'active'");
$stmt->execute([$user_branch_id]);
$med_expiring = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(DISTINCT medication_name) as count FROM medications_inventory WHERE branch_id = ? AND expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date < CURDATE()");
$stmt->execute([$user_branch_id]);
$med_expired = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(DISTINCT medication_name) as count FROM medications_inventory WHERE branch_id = ? AND (status = 'inactive' OR (expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date < CURDATE()))");
$stmt->execute([$user_branch_id]);
$med_inactive = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COALESCE(SUM(quantity * selling_price), 0) as total_value FROM medications_inventory WHERE branch_id = ? AND status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')");
$stmt->execute([$user_branch_id]);
$med_value = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

// ================================================================
// STATISTICS - EQUIPMENT
// ================================================================
$stmt = $db->prepare("SELECT COUNT(DISTINCT equipment_name) as count FROM medical_equipment WHERE branch_id = ? AND status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')");
$stmt->execute([$user_branch_id]);
$total_equipment = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(DISTINCT equipment_name) as count FROM medical_equipment WHERE branch_id = ? AND status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') AND quantity > 0");
$stmt->execute([$user_branch_id]);
$equip_in_stock = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(DISTINCT equipment_name) as count FROM medical_equipment WHERE branch_id = ? AND status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') AND quantity = 0");
$stmt->execute([$user_branch_id]);
$equip_out_of_stock = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(DISTINCT equipment_name) as count FROM medical_equipment WHERE branch_id = ? AND status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') AND quantity > 0 AND quantity <= reorder_level");
$stmt->execute([$user_branch_id]);
$equip_low_stock = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(DISTINCT equipment_name) as count FROM medical_equipment WHERE branch_id = ? AND expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'active'");
$stmt->execute([$user_branch_id]);
$equip_expiring = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(DISTINCT equipment_name) as count FROM medical_equipment WHERE branch_id = ? AND expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date < CURDATE()");
$stmt->execute([$user_branch_id]);
$equip_expired = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(DISTINCT equipment_name) as count FROM medical_equipment WHERE branch_id = ? AND (status = 'inactive' OR (expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date < CURDATE()))");
$stmt->execute([$user_branch_id]);
$equip_inactive = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COALESCE(SUM(quantity * selling_price), 0) as total_value FROM medical_equipment WHERE branch_id = ? AND status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')");
$stmt->execute([$user_branch_id]);
$equip_value = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

$total_inventory_value = $med_value + $equip_value;

// ================================================================
// IN PROGRESS PURCHASES
// ================================================================
$in_progress_purchases = [];
$stmt = $db->prepare("SELECT id, invoice_number, purchase_type, status, total_items, total_quantity, created_at, created_by FROM purchases WHERE created_by = ? AND status = 'IN_PROGRESS' AND branch_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id, $user_branch_id]);
$in_progress_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

$view_data = null;
$view_batches = [];
$view_name = '';

if ($view_id > 0) {
    if ($view_type === 'medicine') {
        $stmt = $db->prepare("SELECT medication_name FROM medications_inventory WHERE id = ? AND branch_id = ?");
        $stmt->execute([$view_id, $user_branch_id]);
        $name_row = $stmt->fetch();
        if ($name_row) {
            $view_name = $name_row['medication_name'];
            $stmt = $db->prepare("SELECT m.*, u.full_name as added_by_full_name FROM medications_inventory m LEFT JOIN users u ON m.added_by = u.id WHERE m.medication_name = ? AND m.branch_id = ? ORDER BY m.expiry_date ASC, m.id ASC");
            $stmt->execute([$view_name, $user_branch_id]);
            $view_batches = $stmt->fetchAll();
            $view_data = $view_batches[0] ?? null;
        }
    } else {
        $stmt = $db->prepare("SELECT equipment_name FROM medical_equipment WHERE id = ? AND branch_id = ?");
        $stmt->execute([$view_id, $user_branch_id]);
        $name_row = $stmt->fetch();
        if ($name_row) {
            $view_name = $name_row['equipment_name'];
            $stmt = $db->prepare("SELECT e.*, u.full_name as added_by_full_name FROM medical_equipment e LEFT JOIN users u ON e.added_by = u.id WHERE e.equipment_name = ? AND e.branch_id = ? ORDER BY e.expiry_date ASC, e.id ASC");
            $stmt->execute([$view_name, $user_branch_id]);
            $view_batches = $stmt->fetchAll();
            $view_data = $view_batches[0] ?? null;
        }
    }
}

$profile_pic_url = !empty($profile_pic) ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<style>
    :root {
        --primary: #0B5ED7; --primary-dark: #0A3D8A; --primary-light: #E8F0FE;
        --success: #059669; --success-dark: #047857; --success-light: #D1FAE5;
        --warning: #D97706; --warning-light: #FEF3C7;
        --danger: #DC2626; --danger-light: #FEE2E2;
        --purple: #7C3AED; --purple-light: #EDE9FE;
        --teal: #0D9488; --teal-light: #CCFBF1;
        --bg-body: #F1F5F9; --bg-card: #FFFFFF;
        --border-color: #E2E8F0;
        --text-primary: #0F172A; --text-secondary: #475569; --text-muted: #94A3B8;
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A; --bg-card: #1E293B;
        --border-color: #334155;
        --text-primary: #F1F5F9; --text-secondary: #94A3B8; --text-muted: #64748B;
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
        top: -50%; right: -20%;
        width: 300px; height: 300px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }
    
    .page-header .page-title {
        color: white; font-size: 1.8rem; font-weight: 700;
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        position: relative; z-index: 1;
    }
    
    .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
    
    .page-header .page-subtitle {
        color: rgba(255,255,255,0.85); font-size: 0.95rem;
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        position: relative; z-index: 1;
    }
    
    .page-header .branch-tag {
        background: rgba(255,255,255,0.15); color: white;
        padding: 3px 14px; border-radius: 20px;
        font-size: 0.7rem; font-weight: 600;
        display: inline-flex; align-items: center; gap: 4px;
        backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1);
    }
    
    .page-header .header-actions {
        display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
        position: relative; z-index: 1;
    }
    
    .btn-add-medicine {
        background: var(--success); color: white;
        padding: 10px 24px; border-radius: 10px;
        font-weight: 600; font-size: 0.9rem; border: none;
        cursor: pointer; transition: all 0.3s ease;
        display: inline-flex; align-items: center; gap: 8px;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        text-decoration: none;
    }
    .btn-add-medicine:hover { background: var(--success-dark); transform: translateY(-3px); box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35); color: white; }
    
    .btn-add-equipment {
        background: var(--purple); color: white;
        padding: 10px 24px; border-radius: 10px;
        font-weight: 600; font-size: 0.9rem; border: none;
        cursor: pointer; transition: all 0.3s ease;
        display: inline-flex; align-items: center; gap: 8px;
        box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
        text-decoration: none;
    }
    .btn-add-equipment:hover { background: #6D28D9; transform: translateY(-3px); box-shadow: 0 6px 20px rgba(124, 58, 237, 0.35); color: white; }
    
    .btn-purchase-history {
        background: var(--warning); color: white;
        padding: 10px 24px; border-radius: 10px;
        font-weight: 600; font-size: 0.9rem; border: none;
        cursor: pointer; transition: all 0.3s ease;
        display: inline-flex; align-items: center; gap: 8px;
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
        text-decoration: none;
    }
    .btn-purchase-history:hover { background: #B45309; transform: translateY(-3px); box-shadow: 0 6px 20px rgba(217, 119, 6, 0.35); color: white; }
    
    .btn-outline {
        background: transparent; color: rgba(255,255,255,0.9);
        border: 2px solid rgba(255,255,255,0.3);
        padding: 8px 18px; border-radius: 10px;
        font-weight: 600; font-size: 0.82rem;
        cursor: pointer; transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-outline:hover { border-color: white; color: white; background: rgba(255,255,255,0.1); }
    
    .purchase-banner {
        background: var(--success-light);
        border: 2px solid var(--success);
        border-radius: 12px;
        padding: 12px 18px;
        margin-bottom: 20px;
        display: flex; justify-content: space-between;
        align-items: center; flex-wrap: wrap; gap: 10px;
    }
    
    [data-theme="dark"] .purchase-banner { background: #1A3A2A; border-color: #34D399; }
    
    .purchase-banner .banner-text {
        font-size: 0.85rem; font-weight: 500; color: var(--text-primary);
    }
    .purchase-banner .banner-text i { color: var(--success); }
    
    .purchase-banner .btn-go-to-purchase {
        background: var(--success); color: white;
        padding: 6px 20px; border-radius: 8px;
        font-weight: 600; font-size: 0.8rem; border: none;
        cursor: pointer; transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .purchase-banner .btn-go-to-purchase:hover { background: var(--success-dark); transform: translateY(-2px); }
    
    .tabs-container {
        display: flex; gap: 4px;
        background: var(--bg-card); border-radius: 12px;
        padding: 4px; border: 2px solid var(--border-color);
        margin-bottom: 24px;
    }
    
    .tab-btn {
        padding: 10px 24px; border-radius: 10px;
        font-weight: 600; font-size: 0.85rem; border: none;
        cursor: pointer; transition: all 0.3s ease;
        background: transparent; color: var(--text-secondary);
        display: inline-flex; align-items: center; gap: 8px;
        flex: 1; justify-content: center;
    }
    .tab-btn:hover { background: var(--primary-light); color: var(--primary); }
    .tab-btn.active { background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
    .tab-btn .badge { background: rgba(255,255,255,0.2); color: white; padding: 1px 8px; border-radius: 10px; font-size: 0.65rem; }
    .tab-btn:not(.active) .badge { background: var(--border-color); color: var(--text-secondary); }
    
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 12px; margin-bottom: 24px;
    }
    
    .stat-card {
        border-radius: 12px; padding: 14px 16px;
        transition: all 0.3s ease; cursor: pointer;
        text-decoration: none; display: block;
        color: white; box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        min-height: 80px;
    }
    .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
    .stat-card .stat-number { font-size: 1.4rem; font-weight: 700; line-height: 1.2; }
    .stat-card .stat-label { font-size: 0.6rem; color: rgba(255,255,255,0.85); font-weight: 500; margin-top: 2px; }
    .stat-card .stat-icon { font-size: 1.2rem; opacity: 0.8; float: right; }
    .stat-card .stat-value { font-size: 0.7rem; font-weight: 600; color: rgba(255,255,255,0.7); margin-top: 2px; }
    .stat-card .stat-sub { font-size: 0.55rem; color: rgba(255,255,255,0.5); }
    .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
    .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
    .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
    
    .card {
        background: var(--bg-card); border-radius: 14px;
        padding: 18px 22px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease; margin-bottom: 24px;
    }
    .card:hover { border-color: var(--primary); box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06); }
    
    .result-count { font-size: 0.8rem; color: var(--text-secondary); }
    .result-count strong { color: var(--primary); }
    
    .filter-group { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
    
    .filter-btn {
        padding: 4px 14px; border-radius: 16px;
        font-size: 0.7rem; font-weight: 600;
        border: 2px solid var(--border-color);
        background: transparent; color: var(--text-secondary);
        cursor: pointer; transition: all 0.3s ease;
        text-decoration: none;
    }
    .filter-btn:hover { border-color: var(--primary); color: var(--primary); }
    .filter-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
    .filter-btn.clear-filter { border-color: var(--danger); color: var(--danger); }
    .filter-btn.clear-filter:hover { background: var(--danger); color: white; }
    
    .filter-form { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    
    .filter-form select {
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px; font-size: 0.8rem;
        background: var(--bg-card); color: var(--text-primary);
        outline: none; transition: all 0.3s ease;
        min-width: 180px; cursor: pointer;
    }
    .filter-form select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1); }
    
    .btn-search {
        padding: 8px 20px; border-radius: 10px;
        font-weight: 600; font-size: 0.85rem; border: none;
        background: var(--primary); color: white;
        cursor: pointer; transition: all 0.3s ease;
    }
    .btn-search:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
    
    .btn-reset {
        padding: 8px 16px; border-radius: 10px;
        font-weight: 600; font-size: 0.85rem;
        border: 2px solid var(--border-color);
        background: transparent; color: var(--text-secondary);
        cursor: pointer; transition: all 0.3s ease;
        text-decoration: none;
    }
    .btn-reset:hover { border-color: var(--danger); color: var(--danger); }
    
    /* TABLE HEADER BAR */
    .table-header-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .table-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        flex: 1;
        min-width: 0;
    }
    
    .table-header-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        flex-shrink: 0;
    }
    
    .table-search-box {
        position: relative;
        min-width: 320px;
        flex: 1;
        max-width: 500px;
    }
    
    .table-search-box i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255,255,255,0.95);
        font-size: 0.9rem;
        pointer-events: none;
        z-index: 1;
    }
    
    .table-search-box input {
        width: 100%;
        padding: 11px 16px 11px 44px;
        border: 2px solid var(--primary-dark);
        border-radius: 10px;
        font-size: 0.85rem;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        outline: none;
        font-weight: 500;
        height: 44px;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        transition: all 0.3s ease;
    }
    
    .table-search-box input::placeholder { color: rgba(255,255,255,0.85); font-weight: 400; }
    .table-search-box input:focus { box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.25), 0 6px 20px rgba(11, 94, 215, 0.35); transform: translateY(-1px); }
    
    .scroll-btn-header {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        font-size: 0.85rem;
    }
    .scroll-btn-header:hover { background: var(--primary); border-color: var(--primary); color: white; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
    .scroll-btn-header:disabled { opacity: 0.35; cursor: not-allowed; transform: none; }
    
    .search-results-info {
        font-size: 0.72rem;
        color: var(--primary);
        padding: 6px 12px;
        background: var(--primary-light);
        border-radius: 8px;
        white-space: nowrap;
        display: none;
        font-weight: 600;
        border: 1px solid var(--primary);
    }
    .search-results-info strong { color: var(--primary); font-size: 0.8rem; }
    
    .table-title-inline {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
    }
    .table-title-inline i { color: var(--primary); }
    
    /* TABLE */
    .table-container { position: relative; border-radius: 10px; overflow: hidden; }
    
    .table-wrap {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 560px;
        scroll-behavior: smooth;
        position: relative;
        border-radius: 12px;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
    }
    
    .table-wrap::-webkit-scrollbar { height: 10px; width: 10px; }
    .table-wrap::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 6px; }
    .table-wrap::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 6px; border: 2px solid var(--bg-body); }
    .table-wrap::-webkit-scrollbar-thumb:hover { background: var(--primary-dark); }
    
    .data-table {
        width: 100%;
        min-width: 1550px;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8rem;
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    
    .data-table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        padding: 14px 16px;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 700;
        white-space: nowrap;
        text-align: left;
        border-right: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .data-table thead th:last-child { border-right: none; text-align: center; }
    .data-table thead th:first-child { border-radius: 10px 0 0 0; }
    .data-table thead th:last-child { border-radius: 0 10px 0 0; }
    
    .data-table tbody tr { transition: all 0.2s ease; background: var(--bg-card); }
    .data-table tbody tr:nth-child(even) { background: rgba(11, 94, 215, 0.03); }
    [data-theme="dark"] .data-table tbody tr:nth-child(even) { background: rgba(11, 94, 215, 0.08); }
    
    .data-table tbody tr:hover {
        background: linear-gradient(90deg, rgba(209, 250, 229, 0.5) 0%, rgba(209, 250, 229, 0.15) 100%) !important;
        box-shadow: inset 4px 0 0 var(--success);
    }
    [data-theme="dark"] .data-table tbody tr:hover {
        background: linear-gradient(90deg, rgba(26, 58, 42, 0.7) 0%, rgba(26, 58, 42, 0.3) 100%) !important;
        box-shadow: inset 4px 0 0 #34D399;
    }
    
    .data-table td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
        font-size: 0.82rem;
        line-height: 1.45;
        transition: all 0.2s ease;
    }
    .data-table tbody tr:last-child td { border-bottom: none; }
    
    /* Column widths */
    .col-sno { width: 55px; min-width: 55px; text-align: center; font-weight: 700; color: var(--text-secondary); font-family: 'JetBrains Mono', monospace; }
    .col-name { min-width: 220px; max-width: 280px; white-space: normal; }
    .col-name strong { display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-bottom: 2px; line-height: 1.3; }
    .col-category { min-width: 140px; }
    .col-qty { min-width: 90px; text-align: center; font-family: 'JetBrains Mono', monospace; }
    .col-qty strong { font-size: 0.95rem; font-weight: 800; color: var(--primary); display: inline-block; }
    .col-reorder { min-width: 80px; text-align: center; font-family: 'JetBrains Mono', monospace; color: var(--text-secondary); }
    .col-stock { min-width: 140px; text-align: center; }
    .col-price { min-width: 120px; text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--success); font-size: 0.85rem; }
    .col-expiry { min-width: 120px; text-align: center; }
    .col-days { min-width: 85px; text-align: center; }
    .col-batch { min-width: 150px; text-align: center; }
    .col-status { min-width: 105px; text-align: center; }
    .col-added-by { min-width: 150px; max-width: 180px; }
    .col-actions { min-width: 100px; text-align: center; padding: 12px 8px; }
    
    /* BADGES */
    .added-by-tag {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 12px; border-radius: 20px;
        font-size: 0.7rem; font-weight: 600;
        background: var(--purple-light); color: var(--purple);
        white-space: nowrap; max-width: 100%;
        overflow: hidden; text-overflow: ellipsis;
        border: 1px solid rgba(124, 58, 237, 0.2);
    }
    [data-theme="dark"] .added-by-tag { background: #2D1B4E; color: #C4B5FD; border-color: rgba(196, 181, 253, 0.2); }
    
    .status-badge {
        padding: 5px 14px; border-radius: 20px;
        font-size: 0.68rem; font-weight: 700;
        display: inline-flex; align-items: center; gap: 4px;
        letter-spacing: 0.04em; text-transform: uppercase; white-space: nowrap;
    }
    .status-badge.active { background: var(--success-light); color: var(--success); border: 1px solid rgba(5, 150, 105, 0.3); }
    .status-badge.inactive { background: var(--danger-light); color: var(--danger); border: 1px solid rgba(220, 38, 38, 0.3); }
    
    /* ✅ STOCK BADGES - LOW NI BLUE */
    .stock-badge {
        padding: 6px 14px; border-radius: 20px;
        font-size: 0.7rem; font-weight: 700;
        display: inline-flex; align-items: center; gap: 5px;
        letter-spacing: 0.02em; white-space: nowrap;
    }
    .stock-badge.ok { background: var(--success-light); color: var(--success); border: 1px solid rgba(5, 150, 105, 0.3); }
    .stock-badge.low { background: var(--primary-light); color: var(--primary); border: 1px solid rgba(11, 94, 215, 0.3); animation: pulse 1.5s infinite; }
    .stock-badge.out { background: var(--danger-light); color: var(--danger); border: 1px solid rgba(220, 38, 38, 0.3); animation: pulse 1s infinite; }
    
    @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.65; } }
    
    /* ✅ EXPIRY BADGES - EXPIRING NI BLUE */
    .expiry-badge {
        padding: 5px 12px; border-radius: 20px;
        font-size: 0.7rem; font-weight: 700;
        display: inline-flex; align-items: center; gap: 4px;
        white-space: nowrap;
        font-family: 'JetBrains Mono', monospace;
    }
    .expiry-badge.valid { background: var(--success-light); color: var(--success); border: 1px solid rgba(5, 150, 105, 0.3); }
    .expiry-badge.expiring { background: var(--primary-light); color: var(--primary); border: 1px solid rgba(11, 94, 215, 0.3); animation: pulse 1.5s infinite; }
    .expiry-badge.expired { background: var(--danger-light); color: var(--danger); border: 1px solid rgba(220, 38, 38, 0.3); animation: pulse 1s infinite; }
    .expiry-badge.no-expiry { background: var(--border-color); color: var(--text-muted); border: 1px solid rgba(148, 163, 184, 0.3); }
    
    /* ✅ DAYS REMAINING - WARNING NI BLUE */
    .days-remaining {
        font-size: 0.72rem; font-weight: 700;
        padding: 5px 12px; border-radius: 20px;
        display: inline-flex; align-items: center; gap: 4px;
        font-family: 'JetBrains Mono', monospace;
        white-space: nowrap;
    }
    .days-remaining.good { background: var(--success-light); color: var(--success); }
    .days-remaining.warning { background: var(--primary-light); color: var(--primary); animation: pulse 1.5s infinite; }
    .days-remaining.danger { background: var(--danger-light); color: var(--danger); animation: pulse 1s infinite; }
    .days-remaining.forever { background: var(--border-color); color: var(--text-muted); }
    
    .batch-number {
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.72rem; font-weight: 700;
        padding: 5px 12px; border-radius: 8px;
        background: var(--primary-light); color: var(--primary);
        display: inline-block; letter-spacing: 0.02em;
        border: 1px solid rgba(11, 94, 215, 0.2);
    }
    [data-theme="dark"] .batch-number { background: #1E3A5F; color: #6EA8FE; border-color: rgba(110, 168, 254, 0.3); }
    
    /* ✅ BATCH COUNT BADGE - BLUE */
    .batch-count-badge {
        font-size: 0.6rem;
        background: var(--primary-light);
        color: var(--primary);
        padding: 2px 10px;
        border-radius: 10px;
        font-weight: 700;
        border: 1px solid rgba(11, 94, 215, 0.3);
        display: inline-flex;
        align-items: center;
        gap: 3px;
        box-shadow: 0 1px 3px rgba(11, 94, 215, 0.15);
    }
    [data-theme="dark"] .batch-count-badge { background: #1E3A5F; color: #6EA8FE; border-color: rgba(110, 168, 254, 0.3); }
    
    .zero-qty-warning {
        font-size: 0.6rem;
        display: block;
        color: var(--warning);
        font-weight: 700;
        margin-top: 3px;
    }
    
    /* ACTION BUTTONS */
    .action-btn {
        padding: 8px 14px; border-radius: 8px;
        font-size: 0.72rem; font-weight: 700;
        border: none; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        gap: 5px; color: white; min-width: 72px;
        height: 34px; transition: all 0.25s ease;
        letter-spacing: 0.02em;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        white-space: nowrap;
        text-decoration: none;
        font-family: inherit;
    }
    .action-btn.view { background: linear-gradient(135deg, var(--purple), #6D28D9); }
    .action-btn.view:hover { background: linear-gradient(135deg, #6D28D9, #5B21B6); transform: translateY(-2px); box-shadow: 0 6px 18px rgba(124, 58, 237, 0.5); color: white; }
    
    .no-results-row td {
        text-align: center;
        padding: 50px 20px !important;
        color: var(--text-secondary);
    }
    .no-results-row i { font-size: 2.5rem; color: var(--border-color); display: block; margin-bottom: 10px; }
    .no-results-row p { font-weight: 600; color: var(--text-primary); margin: 0; }
    
    /* MESSAGE */
    .message-box {
        padding: 12px 18px; border-radius: 10px;
        margin-bottom: 16px; display: flex;
        align-items: center; gap: 10px;
        font-weight: 500;
        animation: slideDown 0.4s ease;
    }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    
    .message-box.success { background: var(--success-light); color: #065F46; border: 2px solid #6EE7B7; }
    .message-box.error { background: var(--danger-light); color: #991B1B; border: 2px solid #FCA5A5; }
    .message-box.info { background: var(--primary-light); color: #0A4CA8; border: 2px solid #6EA8FE; }
    
    [data-theme="dark"] .message-box.success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    [data-theme="dark"] .message-box.error { background: #3A1A1A; color: #F87171; border-color: #F87171; }
    [data-theme="dark"] .message-box.info { background: #1A2A4A; color: #6EA8FE; border-color: #6EA8FE; }
    
    .empty-state { text-align: center; padding: 50px 20px; color: var(--text-secondary); }
    .empty-state i { font-size: 3rem; color: var(--border-color); display: block; margin-bottom: 12px; }
    .empty-state p { font-size: 0.95rem; font-weight: 600; margin-bottom: 6px; }
    .empty-state .sub { font-size: 0.8rem; font-weight: 400; }
    
    /* ================================================================
       ✅ MODAL - Z-INDEX KUBWA ILI IWE MBELE YA SIDEBAR
       ================================================================ */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.75);
        z-index: 999999;
        justify-content: center;
        align-items: center;
        padding: 20px;
        backdrop-filter: blur(4px);
    }
    .modal-overlay.show { display: flex; }
    
    .modal-content {
        background: var(--bg-card);
        border-radius: 14px;
        max-width: 950px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        position: relative;
        z-index: 1000000;
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
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .modal-close {
        background: none; border: none;
        font-size: 1.4rem; cursor: pointer;
        color: var(--text-secondary);
        transition: all 0.2s;
        text-decoration: none;
        line-height: 1;
    }
    .modal-close:hover { color: var(--danger); transform: rotate(90deg); }
    
    /* VIEW MODAL */
    .view-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 14px;
    }
    .view-item {
        padding: 10px 14px;
        background: var(--bg-body);
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }
    .view-item .label {
        font-size: 0.55rem;
        text-transform: uppercase;
        color: var(--text-secondary);
        font-weight: 700;
        letter-spacing: 0.05em;
    }
    .view-item .value {
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-top: 3px;
    }
    .view-item.full-width { grid-column: 1 / -1; }
    
    .view-items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.78rem;
    }
    .view-items-table thead th {
        background: var(--primary);
        color: white;
        padding: 10px 12px;
        font-size: 0.62rem;
        text-transform: uppercase;
        font-weight: 700;
        text-align: left;
        border-bottom: 2px solid var(--primary);
        letter-spacing: 0.04em;
        white-space: nowrap;
    }
    .view-items-table tbody td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        font-size: 0.78rem;
    }
    .view-items-table tbody tr:nth-child(even) { background: rgba(11, 94, 215, 0.03); }
    [data-theme="dark"] .view-items-table tbody tr:nth-child(even) { background: rgba(11, 94, 215, 0.08); }
    .view-items-table tbody tr:hover { background: rgba(209, 250, 229, 0.4); }
    
    /* FOOTER */
    .footer {
        padding: 12px 0;
        border-top: 1px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.65rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: var(--primary); font-weight: 600; }
    
    .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
    .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
    .animate-fade-in-up:nth-child(2) { animation-delay: 0.1s; }
    .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    
    @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(4, 1fr); } }
    @media (max-width: 1024px) { .main-content { margin-left: 0; padding: 16px; } .stats-grid { grid-template-columns: repeat(3, 1fr); } }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-form { flex-direction: column; align-items: stretch; }
        .filter-form select { min-width: 100%; }
        .filter-group { justify-content: center; }
        .card { padding: 12px 14px; }
        .tab-btn { font-size: 0.7rem; padding: 8px 12px; }
        .tab-btn .badge { display: none; }
        .page-header .page-title { font-size: 1.3rem; }
        .stat-card .stat-number { font-size: 1.1rem; }
        .stat-card { padding: 10px 12px; min-height: 65px; }
        .header-actions { flex-direction: column; align-items: stretch; width: 100%; }
        .header-actions a { width: 100%; justify-content: center; }
        .data-table { min-width: 1200px; }
        .table-header-bar { flex-direction: column; align-items: stretch; }
        .table-header-left { width: 100%; flex-direction: column; align-items: stretch; }
        .table-search-box { min-width: 100%; max-width: 100%; }
        .table-header-right { width: 100%; justify-content: flex-end; }
        .view-grid { grid-template-columns: 1fr; }
    }
    
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .stat-card .stat-number { font-size: 0.9rem; }
        .stat-card { padding: 8px 10px; min-height: 55px; }
        .data-table { min-width: 1000px; font-size: 0.72rem; }
    }
</style>

<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-warehouse"></i> Inventory
            </h1>
            <p class="page-subtitle">
                Manage medicines and medical equipment (Grouped by Name)
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="branch-tag" style="background:rgba(255,255,255,0.1);">
                    <i class="fas fa-coins"></i> TSh <?= formatMoneyShort($total_inventory_value) ?>
                </span>
            </p>
        </div>
        <div class="header-actions">
            <a href="select_purchase.php?type=medicine" class="btn-add-medicine">
                <i class="fas fa-plus-circle"></i> Add Medicine
            </a>
            <a href="select_purchase.php?type=equipment" class="btn-add-equipment">
                <i class="fas fa-plus-circle"></i> Add Equipment
            </a>
            <a href="purchase_history.php" class="btn-purchase-history">
                <i class="fas fa-history"></i> Purchase History
            </a>
            <a href="dashboard.php" class="btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>" id="messageBox">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'info' ? 'fa-info-circle' : 'fa-exclamation-circle') ?>"></i>
            <?= $message ?>
            <button onclick="this.parentElement.style.display='none'" style="margin-left:auto;background:none;border:none;cursor:pointer;font-size:1.1rem;color:inherit;">&times;</button>
        </div>
    <?php endif; ?>

    <!-- In Progress Banner -->
    <?php if (count($in_progress_purchases) > 0): ?>
        <div class="purchase-banner animate-fade-in-up">
            <div class="banner-text">
                <i class="fas fa-spinner fa-spin"></i>
                You have <strong><?= count($in_progress_purchases) ?></strong> purchase(s) in progress:
                <?php foreach ($in_progress_purchases as $p): ?>
                    <span style="display:inline-block;background:rgba(255,255,255,0.2);padding:2px 12px;border-radius:12px;margin:2px 4px;font-size:0.75rem;">
                        <?= htmlspecialchars($p['invoice_number']) ?>
                        (<?= ucfirst($p['purchase_type']) ?>)
                    </span>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php foreach ($in_progress_purchases as $p): ?>
                    <a href="purchases.php?id=<?= $p['id'] ?>&type=<?= $p['purchase_type'] ?>" class="btn-go-to-purchase">
                        <i class="fas fa-arrow-right"></i> Continue <?= ucfirst($p['purchase_type']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tabs-container animate-fade-in-up">
        <button class="tab-btn <?= $active_tab === 'medicines' ? 'active' : '' ?>" onclick="switchTab('medicines')">
            <i class="fas fa-pills"></i> Medicines
            <span class="badge"><?= $total_medicines ?></span>
        </button>
        <button class="tab-btn <?= $active_tab === 'equipment' ? 'active' : '' ?>" onclick="switchTab('equipment')">
            <i class="fas fa-tools"></i> Equipment
            <span class="badge"><?= $total_equipment ?></span>
        </button>
    </div>

    <!-- MEDICINES TAB -->
    <div id="tab-medicines" class="tab-content <?= $active_tab === 'medicines' ? 'active' : '' ?>">
        
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
                <div class="stat-sub">Active but 0 Qty</div>
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
                <div class="stat-label">Has Expired</div>
                <div class="stat-sub">Some batches expired</div>
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
                <div class="stat-sub">Expired/Manual off</div>
            </a>
        </div>

        <div class="card animate-fade-in-up">
            <div class="filter-group">
                <a href="inventory.php?tab=medicines" class="filter-btn <?= (empty($status_filter) || $status_filter === 'all') && empty($stock_filter) && empty($expiry_filter) ? 'active' : '' ?>">All</a>
                <a href="inventory.php?tab=medicines&status=active" class="filter-btn <?= $status_filter === 'active' ? 'active' : '' ?>">Active</a>
                <a href="inventory.php?tab=medicines&status=inactive" class="filter-btn <?= $status_filter === 'inactive' ? 'active' : '' ?>">Inactive</a>
                <a href="inventory.php?tab=medicines&stock=low" class="filter-btn <?= $stock_filter === 'low' ? 'active' : '' ?>">Low Stock</a>
                <a href="inventory.php?tab=medicines&stock=out" class="filter-btn <?= $stock_filter === 'out' ? 'active' : '' ?>">Out of Stock</a>
                <a href="inventory.php?tab=medicines&expiry=expiring" class="filter-btn <?= $expiry_filter === 'expiring' ? 'active' : '' ?>">Expiring Soon</a>
                <a href="inventory.php?tab=medicines&expiry=expired" class="filter-btn <?= $expiry_filter === 'expired' ? 'active' : '' ?>" style="border-color:#7F1D1D;color:#7F1D1D;">
                    <i class="fas fa-skull"></i> Has Expired
                </a>
                <?php if (!empty($stock_filter) || !empty($expiry_filter) || !empty($status_filter) || !empty($category_filter)): ?>
                    <a href="inventory.php?tab=medicines" class="filter-btn clear-filter">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
            
            <form method="GET" class="filter-form">
                <input type="hidden" name="tab" value="medicines">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="hidden" name="stock" value="<?= htmlspecialchars($stock_filter) ?>">
                <input type="hidden" name="expiry" value="<?= htmlspecialchars($expiry_filter) ?>">
                <select name="category" onchange="this.form.submit()">
                    <option value="">📂 All Categories</option>
                    <?php foreach ($med_categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-search"><i class="fas fa-filter"></i> Filter</button>
                <a href="inventory.php?tab=medicines" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </form>
        </div>

        <div class="card animate-fade-in-up">
            <div class="table-header-bar">
                <div class="table-header-left">
                    <div class="table-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="medSearchInput" placeholder="🔍 Auto-search medicine..." autocomplete="off">
                    </div>
                    
                    <div class="table-title-inline">
                        <i class="fas fa-list"></i>
                        <span>Medicine List</span>
                        <span class="result-count" id="medCountDisplay">(<strong><?= count($medicines) ?></strong> unique)</span>
                    </div>
                    
                    <span class="search-results-info" id="medSearchInfo">
                        <i class="fas fa-filter"></i> <strong id="medSearchCount">0</strong> match
                    </span>
                </div>
                
                <div class="table-header-right">
                    <button type="button" class="scroll-btn-header" id="medScrollBtnLeft" onclick="scrollMedTable('left')" title="Scroll Left">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="scroll-btn-header" id="medScrollBtnRight" onclick="scrollMedTable('right')" title="Scroll Right">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <?php if (count($medicines) > 0): ?>
                <div class="table-container">
                    <div class="table-wrap" id="medTableWrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="col-sno">#</th>
                                    <th class="col-name">Name</th>
                                    <th class="col-category">Category</th>
                                    <th class="col-qty">Total Qty</th>
                                    <th class="col-reorder">Reorder</th>
                                    <th class="col-stock">Stock</th>
                                    <th class="col-price">Price (TSh)</th>
                                    <th class="col-expiry">Expiry</th>
                                    <th class="col-days">Days</th>
                                    <th class="col-batch">Batches</th>
                                    <th class="col-status">Status</th>
                                    <th class="col-added-by">Added By</th>
                                    <th class="col-actions">Action</th>
                                </tr>
                            </thead>
                            <tbody id="medTableBody">
                                <?php $counter = 1; ?>
                                <?php foreach ($medicines as $item): ?>
                                    <?php
                                        $total_qty = $item['total_quantity'] ?? 0;
                                        $stock_status = 'ok'; $stock_label = 'In Stock';
                                        if ($total_qty <= 0) { $stock_status = 'out'; $stock_label = 'Out of Stock'; }
                                        elseif ($total_qty <= $item['reorder_level']) { $stock_status = 'low'; $stock_label = 'Low Stock'; }
                                        
                                        $batch_numbers = $item['batch_numbers'] ?? '';
                                        $batch_count = $batch_numbers ? count(explode('|', $batch_numbers)) : 0;
                                        $first_batch = $batch_numbers ? explode('|', $batch_numbers)[0] : '';
                                        
                                        $expiry_status = 'no-expiry'; $days = '-'; $days_class = 'forever';
                                        $expiry_date = $item['expiry_date'] ?? '';
                                        if (!empty($expiry_date) && $expiry_date !== '0000-00-00') {
                                            $days = $item['days_remaining'] ?? 0;
                                            if ($days < 0) { $expiry_status = 'expired'; $days_class = 'danger'; }
                                            elseif ($days <= 30) { $expiry_status = 'expiring'; $days_class = 'warning'; }
                                            else { $expiry_status = 'valid'; $days_class = 'good'; }
                                        }
                                        
                                        $display_status = $item['computed_status'] ?? 'active';
                                        $price_display = ($item['selling_price'] ?? 0) > 0 ? number_format($item['selling_price'], 0) : 'FREE';
                                        $added_by_display = !empty($item['added_by_full_name']) ? $item['added_by_full_name'] : ($item['added_by_name'] ?? 'System');
                                        
                                        $search_data = strtolower($item['medication_name'] . ' ' . ($item['category'] ?? '') . ' ' . $display_status . ' ' . $added_by_display . ' ' . ($first_batch ?? '') . ' ' . $stock_label);
                                    ?>
                                    <tr class="med-row" data-search="<?= htmlspecialchars($search_data) ?>">
                                        <td class="col-sno"><?= $counter++ ?></td>
                                        <td class="col-name">
                                            <strong><?= htmlspecialchars($item['medication_name']) ?></strong>
                                            <?php if ($batch_count > 1): ?>
                                                <span class="batch-count-badge">
                                                    <i class="fas fa-layer-group"></i> <?= $batch_count ?> batches
                                                </span>
                                            <?php endif; ?>
                                            <div style="font-size:0.65rem;color:var(--text-muted);margin-top:2px;">
                                                <i class="fas fa-cube"></i> <?= htmlspecialchars($item['unit'] ?? 'pcs') ?>
                                            </div>
                                        </td>
                                        <td class="col-category">
                                            <span style="background:var(--primary-light);color:var(--primary);padding:4px 12px;border-radius:12px;font-size:0.7rem;font-weight:600;">
                                                <?= htmlspecialchars($item['category'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td class="col-qty">
                                            <strong><?= $total_qty ?></strong>
                                            <?php if ($total_qty == 0 && $display_status === 'active'): ?>
                                                <span class="zero-qty-warning">
                                                    <i class="fas fa-exclamation-triangle"></i> Cannot sell
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-reorder"><?= $item['reorder_level'] ?></td>
                                        <td class="col-stock">
                                            <span class="stock-badge <?= $stock_status ?>">
                                                <i class="fas <?= $stock_status === 'ok' ? 'fa-check-circle' : ($stock_status === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                                                <?= $stock_label ?>
                                            </span>
                                        </td>
                                        <td class="col-price"><?= $price_display ?></td>
                                        <td class="col-expiry">
                                            <?php if (!empty($expiry_date) && $expiry_date !== '0000-00-00'): ?>
                                                <span class="expiry-badge <?= $expiry_status ?>"><?= date('d/m/Y', strtotime($expiry_date)) ?></span>
                                            <?php else: ?>
                                                <span class="expiry-badge no-expiry"><i class="fas fa-infinity"></i> No Expiry</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-days">
                                            <?php if (!empty($expiry_date) && $expiry_date !== '0000-00-00' && $days !== '-'): ?>
                                                <span class="days-remaining <?= $days_class ?>">
                                                    <?php if ($days < 0): ?><i class="fas fa-skull"></i> EXP
                                                    <?php elseif ($days <= 30): ?><i class="fas fa-clock"></i> <?= $days ?>d
                                                    <?php else: ?><i class="fas fa-check"></i> <?= $days ?>d<?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="days-remaining forever"><i class="fas fa-infinity"></i> ∞</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-batch">
                                            <?php if (!empty($first_batch)): ?>
                                                <span class="batch-number"><?= htmlspecialchars($first_batch) ?></span>
                                                <?php if ($batch_count > 1): ?>
                                                    <span style="font-size:0.65rem;color:var(--text-muted);margin-left:4px;font-weight:600;">+<?= $batch_count - 1 ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted);font-size:0.75rem;">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-status">
                                            <span class="status-badge <?= $display_status ?>">
                                                <i class="fas <?= $display_status === 'active' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                                                <?= ucfirst($display_status) ?>
                                            </span>
                                        </td>
                                        <td class="col-added-by">
                                            <span class="added-by-tag">
                                                <i class="fas fa-user-circle"></i> <?= htmlspecialchars($added_by_display) ?>
                                            </span>
                                        </td>
                                        <td class="col-actions">
                                            <a href="inventory.php?tab=medicines&view=<?= $item['id'] ?>&type=medicine" class="action-btn view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <tr class="no-results-row" id="medNoResults" style="display:none;">
                                    <td colspan="13">
                                        <i class="fas fa-search-minus"></i>
                                        <p>No medicines match your search</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-pills"></i>
                    <p>No medicines found</p>
                    <p class="sub">Click "Add Medicine" to create a new purchase</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- EQUIPMENT TAB -->
    <div id="tab-equipment" class="tab-content <?= $active_tab === 'equipment' ? 'active' : '' ?>">
        
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
                <div class="stat-sub">Active but 0 Qty</div>
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
                <div class="stat-label">Has Expired</div>
                <div class="stat-sub">Some batches expired</div>
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
                <div class="stat-sub">Expired/Manual off</div>
            </a>
        </div>

        <div class="card animate-fade-in-up">
            <div class="filter-group">
                <a href="inventory.php?tab=equipment" class="filter-btn <?= (empty($status_filter) || $status_filter === 'all') && empty($stock_filter) && empty($expiry_filter) ? 'active' : '' ?>">All</a>
                <a href="inventory.php?tab=equipment&status=active" class="filter-btn <?= $status_filter === 'active' ? 'active' : '' ?>">Active</a>
                <a href="inventory.php?tab=equipment&status=inactive" class="filter-btn <?= $status_filter === 'inactive' ? 'active' : '' ?>">Inactive</a>
                <a href="inventory.php?tab=equipment&stock=low" class="filter-btn <?= $stock_filter === 'low' ? 'active' : '' ?>">Low Stock</a>
                <a href="inventory.php?tab=equipment&stock=out" class="filter-btn <?= $stock_filter === 'out' ? 'active' : '' ?>">Out of Stock</a>
                <a href="inventory.php?tab=equipment&expiry=expiring" class="filter-btn <?= $expiry_filter === 'expiring' ? 'active' : '' ?>">Expiring Soon</a>
                <a href="inventory.php?tab=equipment&expiry=expired" class="filter-btn <?= $expiry_filter === 'expired' ? 'active' : '' ?>" style="border-color:#7F1D1D;color:#7F1D1D;">
                    <i class="fas fa-skull"></i> Has Expired
                </a>
                <?php if (!empty($stock_filter) || !empty($expiry_filter) || !empty($status_filter) || !empty($category_filter)): ?>
                    <a href="inventory.php?tab=equipment" class="filter-btn clear-filter">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
            
            <form method="GET" class="filter-form">
                <input type="hidden" name="tab" value="equipment">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="hidden" name="stock" value="<?= htmlspecialchars($stock_filter) ?>">
                <input type="hidden" name="expiry" value="<?= htmlspecialchars($expiry_filter) ?>">
                <select name="category" onchange="this.form.submit()">
                    <option value="">📂 All Categories</option>
                    <?php foreach ($equip_categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-search"><i class="fas fa-filter"></i> Filter</button>
                <a href="inventory.php?tab=equipment" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </form>
        </div>

        <div class="card animate-fade-in-up">
            <div class="table-header-bar">
                <div class="table-header-left">
                    <div class="table-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="equipSearchInput" placeholder="🔍 Auto-search equipment..." autocomplete="off">
                    </div>
                    
                    <div class="table-title-inline">
                        <i class="fas fa-list" style="color:var(--purple);"></i>
                        <span>Equipment List</span>
                        <span class="result-count" id="equipCountDisplay">(<strong><?= count($equipment) ?></strong> unique)</span>
                    </div>
                    
                    <span class="search-results-info" id="equipSearchInfo">
                        <i class="fas fa-filter"></i> <strong id="equipSearchCount">0</strong> match
                    </span>
                </div>
                
                <div class="table-header-right">
                    <button type="button" class="scroll-btn-header" id="equipScrollBtnLeft" onclick="scrollEquipTable('left')" title="Scroll Left">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="scroll-btn-header" id="equipScrollBtnRight" onclick="scrollEquipTable('right')" title="Scroll Right">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <?php if (count($equipment) > 0): ?>
                <div class="table-container">
                    <div class="table-wrap" id="equipTableWrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="col-sno">#</th>
                                    <th class="col-name">Name</th>
                                    <th class="col-category">Category</th>
                                    <th class="col-qty">Total Qty</th>
                                    <th class="col-reorder">Reorder</th>
                                    <th class="col-stock">Stock</th>
                                    <th class="col-price">Price (TSh)</th>
                                    <th class="col-expiry">Expiry</th>
                                    <th class="col-days">Days</th>
                                    <th class="col-batch">Batches</th>
                                    <th class="col-status">Status</th>
                                    <th class="col-added-by">Added By</th>
                                    <th class="col-actions">Action</th>
                                </tr>
                            </thead>
                            <tbody id="equipTableBody">
                                <?php $counter = 1; ?>
                                <?php foreach ($equipment as $item): ?>
                                    <?php
                                        $total_qty = $item['total_quantity'] ?? 0;
                                        $stock_status = 'ok'; $stock_label = 'In Stock';
                                        if ($total_qty <= 0) { $stock_status = 'out'; $stock_label = 'Out of Stock'; }
                                        elseif ($total_qty <= $item['reorder_level']) { $stock_status = 'low'; $stock_label = 'Low Stock'; }
                                        
                                        $batch_numbers = $item['batch_numbers'] ?? '';
                                        $batch_count = $batch_numbers ? count(explode('|', $batch_numbers)) : 0;
                                        $first_batch = $batch_numbers ? explode('|', $batch_numbers)[0] : '';
                                        
                                        $expiry_status = 'no-expiry'; $days = '-'; $days_class = 'forever';
                                        $expiry_date = $item['expiry_date'] ?? '';
                                        if (!empty($expiry_date) && $expiry_date !== '0000-00-00') {
                                            $days = $item['days_remaining'] ?? 0;
                                            if ($days < 0) { $expiry_status = 'expired'; $days_class = 'danger'; }
                                            elseif ($days <= 30) { $expiry_status = 'expiring'; $days_class = 'warning'; }
                                            else { $expiry_status = 'valid'; $days_class = 'good'; }
                                        }
                                        
                                        $display_status = $item['computed_status'] ?? 'active';
                                        $price_display = ($item['selling_price'] ?? 0) > 0 ? number_format($item['selling_price'], 0) : 'FREE';
                                        $added_by_display = !empty($item['added_by_full_name']) ? $item['added_by_full_name'] : ($item['added_by_name'] ?? 'System');
                                        
                                        $search_data = strtolower($item['equipment_name'] . ' ' . ($item['category'] ?? '') . ' ' . $display_status . ' ' . $added_by_display . ' ' . ($first_batch ?? '') . ' ' . $stock_label);
                                    ?>
                                    <tr class="equip-row" data-search="<?= htmlspecialchars($search_data) ?>">
                                        <td class="col-sno"><?= $counter++ ?></td>
                                        <td class="col-name">
                                            <strong><?= htmlspecialchars($item['equipment_name']) ?></strong>
                                            <?php if ($batch_count > 1): ?>
                                                <span class="batch-count-badge">
                                                    <i class="fas fa-layer-group"></i> <?= $batch_count ?> batches
                                                </span>
                                            <?php endif; ?>
                                            <div style="font-size:0.65rem;color:var(--text-muted);margin-top:2px;">
                                                <i class="fas fa-cube"></i> <?= htmlspecialchars($item['unit'] ?? 'pcs') ?>
                                            </div>
                                        </td>
                                        <td class="col-category">
                                            <span style="background:var(--purple-light);color:var(--purple);padding:4px 12px;border-radius:12px;font-size:0.7rem;font-weight:600;">
                                                <?= htmlspecialchars($item['category'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td class="col-qty">
                                            <strong><?= $total_qty ?></strong>
                                            <?php if ($total_qty == 0 && $display_status === 'active'): ?>
                                                <span class="zero-qty-warning">
                                                    <i class="fas fa-exclamation-triangle"></i> Cannot sell
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-reorder"><?= $item['reorder_level'] ?></td>
                                        <td class="col-stock">
                                            <span class="stock-badge <?= $stock_status ?>">
                                                <i class="fas <?= $stock_status === 'ok' ? 'fa-check-circle' : ($stock_status === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                                                <?= $stock_label ?>
                                            </span>
                                        </td>
                                        <td class="col-price"><?= $price_display ?></td>
                                        <td class="col-expiry">
                                            <?php if (!empty($expiry_date) && $expiry_date !== '0000-00-00'): ?>
                                                <span class="expiry-badge <?= $expiry_status ?>"><?= date('d/m/Y', strtotime($expiry_date)) ?></span>
                                            <?php else: ?>
                                                <span class="expiry-badge no-expiry"><i class="fas fa-infinity"></i> No Expiry</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-days">
                                            <?php if (!empty($expiry_date) && $expiry_date !== '0000-00-00' && $days !== '-'): ?>
                                                <span class="days-remaining <?= $days_class ?>">
                                                    <?php if ($days < 0): ?><i class="fas fa-skull"></i> EXP
                                                    <?php elseif ($days <= 30): ?><i class="fas fa-clock"></i> <?= $days ?>d
                                                    <?php else: ?><i class="fas fa-check"></i> <?= $days ?>d<?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="days-remaining forever"><i class="fas fa-infinity"></i> ∞</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-batch">
                                            <?php if (!empty($first_batch)): ?>
                                                <span class="batch-number"><?= htmlspecialchars($first_batch) ?></span>
                                                <?php if ($batch_count > 1): ?>
                                                    <span style="font-size:0.65rem;color:var(--text-muted);margin-left:4px;font-weight:600;">+<?= $batch_count - 1 ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted);font-size:0.75rem;">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-status">
                                            <span class="status-badge <?= $display_status ?>">
                                                <i class="fas <?= $display_status === 'active' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                                                <?= ucfirst($display_status) ?>
                                            </span>
                                        </td>
                                        <td class="col-added-by">
                                            <span class="added-by-tag">
                                                <i class="fas fa-user-circle"></i> <?= htmlspecialchars($added_by_display) ?>
                                            </span>
                                        </td>
                                        <td class="col-actions">
                                            <a href="inventory.php?tab=equipment&view=<?= $item['id'] ?>&type=equipment" class="action-btn view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <tr class="no-results-row" id="equipNoResults" style="display:none;">
                                    <td colspan="13">
                                        <i class="fas fa-search-minus"></i>
                                        <p>No equipment match your search</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tools"></i>
                    <p>No equipment found</p>
                    <p class="sub">Click "Add Equipment" to create a new purchase</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<!-- ================================================================
     ✅ VIEW MODAL - Ipo MBELE ya sidebar (z-index: 999999)
     ================================================================ -->
<?php if ($view_data && !empty($view_batches)): ?>
<div class="modal-overlay show" id="viewModal" style="display:flex;z-index:999999;">
    <div class="modal-content" style="z-index:1000000;">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-eye"></i> 
                <?= ucfirst($view_type) ?> Details - <?= htmlspecialchars($view_name) ?>
            </div>
            <a href="inventory.php?tab=<?= $active_tab ?>" class="modal-close">&times;</a>
        </div>
        
        <div class="view-grid">
            <div class="view-item full-width">
                <div class="label">Name</div>
                <div class="value"><?= htmlspecialchars($view_name) ?></div>
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
                <div class="label">Total Quantity (Active)</div>
                <div class="value">
                    <?php 
                        $total_qty = 0;
                        foreach ($view_batches as $batch) {
                            if ($batch['status'] === 'active' && (empty($batch['expiry_date']) || $batch['expiry_date'] >= date('Y-m-d'))) {
                                $total_qty += $batch['quantity'];
                            }
                        }
                    ?>
                    <strong><?= $total_qty ?></strong>
                    <?php if ($total_qty == 0): ?>
                        <span style="font-size:0.6rem;color:var(--warning);font-weight:600;margin-left:6px;">
                            <i class="fas fa-exclamation-triangle"></i> Cannot be sold
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="view-item">
                <div class="label">Reorder Level</div>
                <div class="value"><?= $view_data['reorder_level'] ?></div>
            </div>
            <div class="view-item">
                <div class="label">Selling Price</div>
                <div class="value">
                    <?= ($view_data['selling_price'] ?? 0) > 0 ? 'TSh ' . number_format($view_data['selling_price'], 0) : 'FREE' ?>
                </div>
            </div>
            <div class="view-item">
                <div class="label">Added By</div>
                <div class="value">
                    <span class="added-by-tag">
                        <i class="fas fa-user-circle"></i>
                        <?= !empty($view_data['added_by_full_name']) ? htmlspecialchars($view_data['added_by_full_name']) : ($view_data['added_by_name'] ?? 'System') ?>
                    </span>
                </div>
            </div>
            <div class="view-item full-width">
                <div class="label">Branch</div>
                <div class="value">
                    <span style="background:var(--primary-light);color:var(--primary);padding:2px 12px;border-radius:12px;font-size:0.75rem;">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div style="margin-top:16px;">
            <div style="font-size:0.85rem;font-weight:700;margin-bottom:10px;color:var(--text-primary);display:flex;align-items:center;gap:8px;">
                <i class="fas fa-layer-group" style="color:var(--primary);"></i> 
                ALL BATCHES (<?= count($view_batches) ?>)
            </div>
            <div style="overflow-x:auto;border:2px solid var(--primary);border-radius:10px;">
                <table class="view-items-table">
                    <thead>
                        <tr>
                            <th style="width:40px;text-align:center;">#</th>
                            <th style="min-width:150px;">Batch Number</th>
                            <th style="min-width:110px;">Category</th>
                            <th style="text-align:center;width:80px;">Qty</th>
                            <th style="min-width:110px;">Expiry Date</th>
                            <th style="text-align:center;width:80px;">Days</th>
                            <th style="text-align:center;width:100px;">Status</th>
                            <th style="min-width:130px;text-align:center;">Supplier</th>
                            <th style="min-width:140px;text-align:center;">Added By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $bcounter = 1; ?>
                        <?php foreach ($view_batches as $batch): 
                            $batch_expiry = $batch['expiry_date'] ?? '';
                            $batch_status = $batch['status'] ?? 'active';
                            $batch_qty = $batch['quantity'] ?? 0;
                            
                            $exp_status = 'no-expiry';
                            $days_left = '-';
                            $status_label = 'Active';
                            $status_class = 'active';
                            
                            if (!empty($batch_expiry) && $batch_expiry !== '0000-00-00') {
                                $days_left = (strtotime($batch_expiry) - time()) / 86400;
                                $days_left = round($days_left);
                                
                                if ($days_left < 0) { $exp_status = 'expired'; $status_label = 'Expired'; $status_class = 'inactive'; }
                                elseif ($days_left <= 30) { $exp_status = 'expiring'; $status_label = 'Expiring Soon'; }
                                else { $exp_status = 'valid'; $status_label = 'Valid'; }
                            } else {
                                $days_left = '∞';
                            }
                            
                            if ($batch_status === 'inactive') { 
                                $status_label = 'Inactive'; 
                                $status_class = 'inactive'; 
                            } elseif ($batch_qty == 0) {
                                $status_label = 'Active (0 Qty)';
                                $status_class = 'active';
                            }
                            
                            $batch_added_by = !empty($batch['added_by_full_name']) ? $batch['added_by_full_name'] : ($batch['added_by_name'] ?? 'System');
                        ?>
                            <tr>
                                <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $bcounter++ ?></td>
                                <td><span class="batch-number"><?= htmlspecialchars($batch['batch_number'] ?? 'N/A') ?></span></td>
                                <td>
                                    <span style="background:var(--primary-light);color:var(--primary);padding:3px 10px;border-radius:10px;font-size:0.65rem;font-weight:600;">
                                        <?= htmlspecialchars($batch['category'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;font-weight:700;font-family:monospace;">
                                    <?= $batch_qty ?>
                                    <?php if ($batch_qty == 0): ?>
                                        <span style="font-size:0.55rem;display:block;color:var(--warning);font-weight:600;">
                                            <i class="fas fa-exclamation-triangle"></i> 0 Qty
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($batch_expiry) && $batch_expiry !== '0000-00-00'): ?>
                                        <span class="expiry-badge <?= $exp_status ?>"><?= date('d/m/Y', strtotime($batch_expiry)) ?></span>
                                    <?php else: ?>
                                        <span class="expiry-badge no-expiry"><i class="fas fa-infinity"></i> No Expiry</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($days_left === '∞'): ?>
                                        <span class="days-remaining forever"><i class="fas fa-infinity"></i> ∞</span>
                                    <?php elseif ($days_left !== '-'): ?>
                                        <span class="days-remaining <?= $days_left < 0 ? 'danger' : ($days_left <= 30 ? 'warning' : 'good') ?>">
                                            <?php if ($days_left < 0): ?><i class="fas fa-skull"></i> EXP
                                            <?php elseif ($days_left <= 30): ?><i class="fas fa-clock"></i> <?= $days_left ?>d
                                            <?php else: ?><i class="fas fa-check"></i> <?= $days_left ?>d<?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="status-badge <?= $status_class ?>"><?= $status_label ?></span>
                                </td>
                                <td style="text-align:center;font-size:0.72rem;color:var(--text-secondary);">
                                    <?= htmlspecialchars($batch['supplier'] ?? 'N/A') ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="added-by-tag" style="font-size:0.65rem;">
                                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($batch_added_by) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div style="display:flex;gap:10px;margin-top:18px;padding-top:14px;border-top:2px solid var(--border-color);">
            <a href="inventory.php?tab=<?= $active_tab ?>" class="btn-reset">
                <i class="fas fa-times"></i> Close
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<footer class="footer">
    <p>
        <span class="footer-brand">Braick Dispensary</span> Management System
        <span style="color:var(--text-muted);margin:0 8px;">|</span>
        Inventory Management (Grouped by Name)
        <span style="color:var(--text-muted);margin:0 8px;">|</span>
        Total Value: <strong>TSh <?= formatMoney($total_inventory_value) ?></strong>
        <span style="color:var(--text-muted);margin:0 8px;">|</span>
        &copy; <?= date('Y') ?> All rights reserved
    </p>
</footer>

<script>
// TAB SWITCHING
function switchTab(tab) {
    var url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    url.searchParams.delete('category');
    url.searchParams.delete('status');
    url.searchParams.delete('stock');
    url.searchParams.delete('expiry');
    url.searchParams.delete('view');
    url.searchParams.delete('action');
    window.location.href = url.toString();
}

// SCROLL FUNCTIONS
var scrollAmount = 400;

function scrollMedTable(direction) {
    var wrap = document.getElementById('medTableWrap');
    if (wrap) wrap.scrollBy({ left: direction === 'left' ? -scrollAmount : scrollAmount, behavior: 'smooth' });
}

function updateMedScrollButtons() {
    var wrap = document.getElementById('medTableWrap');
    var btnLeft = document.getElementById('medScrollBtnLeft');
    var btnRight = document.getElementById('medScrollBtnRight');
    if (!wrap || !btnLeft || !btnRight) return;
    var scrollLeft = wrap.scrollLeft;
    var maxScroll = wrap.scrollWidth - wrap.clientWidth;
    btnLeft.disabled = (scrollLeft <= 5);
    btnRight.disabled = (scrollLeft >= maxScroll - 5 || maxScroll <= 0);
}

function scrollEquipTable(direction) {
    var wrap = document.getElementById('equipTableWrap');
    if (wrap) wrap.scrollBy({ left: direction === 'left' ? -scrollAmount : scrollAmount, behavior: 'smooth' });
}

function updateEquipScrollButtons() {
    var wrap = document.getElementById('equipTableWrap');
    var btnLeft = document.getElementById('equipScrollBtnLeft');
    var btnRight = document.getElementById('equipScrollBtnRight');
    if (!wrap || !btnLeft || !btnRight) return;
    var scrollLeft = wrap.scrollLeft;
    var maxScroll = wrap.scrollWidth - wrap.clientWidth;
    btnLeft.disabled = (scrollLeft <= 5);
    btnRight.disabled = (scrollLeft >= maxScroll - 5 || maxScroll <= 0);
}

// AUTO SEARCH - MEDICINES
(function() {
    var searchInput = document.getElementById('medSearchInput');
    var tableBody = document.getElementById('medTableBody');
    var noResultsRow = document.getElementById('medNoResults');
    var countDisplay = document.getElementById('medCountDisplay');
    var searchInfo = document.getElementById('medSearchInfo');
    var searchCount = document.getElementById('medSearchCount');
    
    if (!searchInput || !tableBody) return;
    var totalRows = document.querySelectorAll('.med-row').length;
    
    function filterTable() {
        var query = searchInput.value.toLowerCase().trim();
        var rows = document.querySelectorAll('.med-row');
        var visibleCount = 0;
        
        rows.forEach(function(row) {
            var searchData = row.getAttribute('data-search') || '';
            if (query === '' || searchData.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (countDisplay) {
            countDisplay.innerHTML = query === '' 
                ? '(<strong>' + totalRows + '</strong> unique)' 
                : '(<strong>' + visibleCount + '</strong> of ' + totalRows + ')';
        }
        
        if (searchInfo && searchCount) {
            if (query === '') {
                searchInfo.style.display = 'none';
            } else {
                searchInfo.style.display = 'inline-flex';
                searchCount.textContent = visibleCount;
            }
        }
        
        if (noResultsRow) {
            noResultsRow.style.display = (visibleCount === 0 && query !== '') ? '' : 'none';
        }
        
        setTimeout(updateMedScrollButtons, 100);
    }
    
    searchInput.addEventListener('input', filterTable);
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { this.value = ''; filterTable(); this.blur(); }
    });
})();

// AUTO SEARCH - EQUIPMENT
(function() {
    var searchInput = document.getElementById('equipSearchInput');
    var tableBody = document.getElementById('equipTableBody');
    var noResultsRow = document.getElementById('equipNoResults');
    var countDisplay = document.getElementById('equipCountDisplay');
    var searchInfo = document.getElementById('equipSearchInfo');
    var searchCount = document.getElementById('equipSearchCount');
    
    if (!searchInput || !tableBody) return;
    var totalRows = document.querySelectorAll('.equip-row').length;
    
    function filterTable() {
        var query = searchInput.value.toLowerCase().trim();
        var rows = document.querySelectorAll('.equip-row');
        var visibleCount = 0;
        
        rows.forEach(function(row) {
            var searchData = row.getAttribute('data-search') || '';
            if (query === '' || searchData.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (countDisplay) {
            countDisplay.innerHTML = query === '' 
                ? '(<strong>' + totalRows + '</strong> unique)' 
                : '(<strong>' + visibleCount + '</strong> of ' + totalRows + ')';
        }
        
        if (searchInfo && searchCount) {
            if (query === '') {
                searchInfo.style.display = 'none';
            } else {
                searchInfo.style.display = 'inline-flex';
                searchCount.textContent = visibleCount;
            }
        }
        
        if (noResultsRow) {
            noResultsRow.style.display = (visibleCount === 0 && query !== '') ? '' : 'none';
        }
        
        setTimeout(updateEquipScrollButtons, 100);
    }
    
    searchInput.addEventListener('input', filterTable);
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { this.value = ''; filterTable(); this.blur(); }
    });
})();

// INITIALIZE
document.addEventListener('DOMContentLoaded', function() {
    var medWrap = document.getElementById('medTableWrap');
    var equipWrap = document.getElementById('equipTableWrap');
    
    if (medWrap) {
        medWrap.addEventListener('scroll', updateMedScrollButtons);
        setTimeout(updateMedScrollButtons, 200);
    }
    if (equipWrap) {
        equipWrap.addEventListener('scroll', updateEquipScrollButtons);
        setTimeout(updateEquipScrollButtons, 200);
    }
    window.addEventListener('resize', function() {
        setTimeout(function() { updateMedScrollButtons(); updateEquipScrollButtons(); }, 200);
    });
});

// CLOSE MESSAGE
setTimeout(function() {
    var messageBox = document.querySelector('.message-box');
    if (messageBox) messageBox.style.display = 'none';
}, 5000);

// KEYBOARD SHORTCUTS
document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') return;
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        var activeTab = document.querySelector('.tab-content.active');
        if (activeTab) {
            var searchInput = activeTab.querySelector('.table-search-box input');
            if (searchInput) { searchInput.focus(); searchInput.select(); }
        }
    }
});

console.log('%c💊 Braick - Pharmacy Inventory V8.9.1', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Modal ipo MBELE ya sidebar (z-index 999999)', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Batch count badge ni BLUE', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Stock low, days warning, expiry expiring ni BLUE', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Table header search (auto-filter)', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ View modal inaonyesha batches zote + added by', 'font-size:13px; color:#34D399; font-weight:bold;');
</script>

</body>
</html>