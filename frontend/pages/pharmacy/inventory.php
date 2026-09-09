<?php
// ================================================================
// FILE: frontend/pages/pharmacy/inventory.php
// PHARMACY - COMPLETE INVENTORY WITH PURCHASE INTEGRATION
// WITH JOIN OR CREATE LOGIC
// FIXED: Equipment without expiry date now display correctly
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
// CHECK AND ADD COLUMNS IF NOT EXISTS
// ================================================================

// Check if added_by exists in medications_inventory
try {
    $stmt = $db->query("SHOW COLUMNS FROM medications_inventory LIKE 'added_by'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `medications_inventory` ADD COLUMN `added_by` INT NULL AFTER `status`");
        $db->exec("ALTER TABLE `medications_inventory` ADD COLUMN `added_by_name` VARCHAR(100) NULL AFTER `added_by`");
    }
} catch (Exception $e) {
    // Column might already exist or table doesn't exist
}

// Check if added_by exists in medical_equipment
try {
    $stmt = $db->query("SHOW COLUMNS FROM medical_equipment LIKE 'added_by'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `medical_equipment` ADD COLUMN `added_by` INT NULL AFTER `status`");
        $db->exec("ALTER TABLE `medical_equipment` ADD COLUMN `added_by_name` VARCHAR(100) NULL AFTER `added_by`");
    }
} catch (Exception $e) {
    // Column might already exist or table doesn't exist
}

// Check if joined_users exists in purchases
try {
    $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'joined_users'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `purchases` ADD COLUMN `joined_users` TEXT NULL AFTER `created_by`");
    }
} catch (Exception $e) {
    // Table might not exist
}

// ================================================================
// CREATE PURCHASE TABLES IF NOT EXISTS
// ================================================================
try {
    $stmt = $db->query("SHOW TABLES LIKE 'purchases'");
    if ($stmt->rowCount() == 0) {
        $db->exec("
            CREATE TABLE `purchases` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `invoice_number` VARCHAR(50) NOT NULL,
                `purchase_type` ENUM('medicine', 'equipment') NOT NULL DEFAULT 'medicine',
                `created_by` INT(11) NOT NULL,
                `created_by_name` VARCHAR(100) NOT NULL,
                `joined_users` TEXT NULL,
                `status` ENUM('IN_PROGRESS', 'COMPLETED', 'CANCELLED') DEFAULT 'IN_PROGRESS',
                `total_items` INT(11) DEFAULT 0,
                `total_quantity` INT(11) DEFAULT 0,
                `total_cost` DECIMAL(15,2) DEFAULT 0.00,
                `completed_at` DATETIME NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `invoice_number` (`invoice_number`),
                KEY `created_by` (`created_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } else {
        $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'purchase_type'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE `purchases` ADD COLUMN `purchase_type` ENUM('medicine', 'equipment') NOT NULL DEFAULT 'medicine' AFTER `invoice_number`");
        }
        $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'joined_users'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE `purchases` ADD COLUMN `joined_users` TEXT NULL AFTER `created_by`");
        }
    }
} catch (Exception $e) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `purchases` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `invoice_number` VARCHAR(50) NOT NULL,
            `purchase_type` ENUM('medicine', 'equipment') NOT NULL DEFAULT 'medicine',
            `created_by` INT(11) NOT NULL,
            `created_by_name` VARCHAR(100) NOT NULL,
            `joined_users` TEXT NULL,
            `status` ENUM('IN_PROGRESS', 'COMPLETED', 'CANCELLED') DEFAULT 'IN_PROGRESS',
            `total_items` INT(11) DEFAULT 0,
            `total_quantity` INT(11) DEFAULT 0,
            `total_cost` DECIMAL(15,2) DEFAULT 0.00,
            `completed_at` DATETIME NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `invoice_number` (`invoice_number`),
            KEY `created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// Create purchase_items table if not exists
try {
    $stmt = $db->query("SHOW TABLES LIKE 'purchase_items'");
    if ($stmt->rowCount() == 0) {
        $db->exec("
            CREATE TABLE `purchase_items` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `purchase_id` INT(11) NOT NULL,
                `item_type` ENUM('medicine', 'equipment') NOT NULL DEFAULT 'medicine',
                `item_id` INT(11) NOT NULL,
                `quantity` INT(11) NOT NULL,
                `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `added_by` INT(11) NOT NULL,
                `added_by_name` VARCHAR(100) NOT NULL,
                `added_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `purchase_id` (`purchase_id`),
                KEY `item_id` (`item_id`),
                KEY `added_by` (`added_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
} catch (Exception $e) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `purchase_items` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `purchase_id` INT(11) NOT NULL,
            `item_type` ENUM('medicine', 'equipment') NOT NULL DEFAULT 'medicine',
            `item_id` INT(11) NOT NULL,
            `quantity` INT(11) NOT NULL,
            `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `added_by` INT(11) NOT NULL,
            `added_by_name` VARCHAR(100) NOT NULL,
            `added_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `purchase_id` (`purchase_id`),
            KEY `item_id` (`item_id`),
            KEY `added_by` (`added_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ================================================================
// GET CATEGORIES
// ================================================================
$med_categories = [];
$stmt = $db->query("SELECT DISTINCT category FROM medications_inventory WHERE category IS NOT NULL AND category != '' AND category != '__other__' ORDER BY category");
$med_categories = $stmt->fetchAll();

$equip_categories = [];
$stmt = $db->query("SELECT DISTINCT category FROM medical_equipment WHERE category IS NOT NULL AND category != '' AND category != '__other__' ORDER BY category");
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
    // CREATE PURCHASE - With Join or Create Logic
    // ================================================================
    if ($action === 'create_purchase') {
        $purchase_type = $_POST['purchase_type'] ?? 'medicine';
        
        // Check if user is already in an IN_PROGRESS purchase of this type
        $stmt = $db->prepare("
            SELECT p.id, p.invoice_number, p.purchase_type, p.created_by, p.created_by_name
            FROM purchases p
            WHERE p.status = 'IN_PROGRESS' 
            AND p.purchase_type = ?
            AND p.created_by = ?
            LIMIT 1
        ");
        $stmt->execute([$purchase_type, $user_id]);
        $creator_purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($creator_purchase) {
            // User is creator of an IN_PROGRESS purchase
            $_SESSION['purchase_message'] = "You already have an IN_PROGRESS purchase: <strong>{$creator_purchase['invoice_number']}</strong>. Continue adding items.";
            $_SESSION['purchase_message_type'] = 'info';
            header('Location: purchases.php?id=' . $creator_purchase['id'] . '&type=' . $purchase_type);
            exit;
        }
        
        // Check if user has already joined an IN_PROGRESS purchase
        $stmt = $db->prepare("
            SELECT DISTINCT p.id, p.invoice_number, p.purchase_type, p.created_by, p.created_by_name
            FROM purchases p
            JOIN purchase_items pi ON p.id = pi.purchase_id
            WHERE p.status = 'IN_PROGRESS' 
            AND p.purchase_type = ?
            AND pi.added_by = ?
            LIMIT 1
        ");
        $stmt->execute([$purchase_type, $user_id]);
        $joined_purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($joined_purchase) {
            // User has already joined an IN_PROGRESS purchase
            $_SESSION['purchase_message'] = "You have already joined purchase: <strong>{$joined_purchase['invoice_number']}</strong>. Continue adding items.";
            $_SESSION['purchase_message_type'] = 'info';
            header('Location: purchases.php?id=' . $joined_purchase['id'] . '&type=' . $purchase_type);
            exit;
        }
        
        // Check if there's any IN_PROGRESS purchase of this type (for joining)
        $stmt = $db->prepare("
            SELECT id, invoice_number, created_by, created_by_name, 
                   (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = purchases.id) as items_count
            FROM purchases 
            WHERE status = 'IN_PROGRESS' AND purchase_type = ?
        ");
        $stmt->execute([$purchase_type]);
        $in_progress_exists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($in_progress_exists) > 0) {
            // There is an IN_PROGRESS purchase, user can join or create new
            $_SESSION['purchase_join_options'] = [
                'purchases' => $in_progress_exists,
                'type' => $purchase_type
            ];
            $_SESSION['purchase_message'] = "There is an IN_PROGRESS purchase. You can join it or create a new one.";
            $_SESSION['purchase_message_type'] = 'info';
            header('Location: purchases.php?action=choose');
            exit;
        }
        
        // No IN_PROGRESS purchases, create new one
        $date = date('Ymd');
        $prefix = $purchase_type === 'medicine' ? 'INV-MED' : 'INV-EQP';
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM purchases WHERE DATE(created_at) = CURDATE() AND purchase_type = ?");
        $stmt->execute([$purchase_type]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        $invoice_number = $prefix . '-' . $date . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        
        try {
            $stmt = $db->prepare("
                INSERT INTO purchases (invoice_number, purchase_type, created_by, created_by_name, status, created_at)
                VALUES (?, ?, ?, ?, 'IN_PROGRESS', NOW())
            ");
            $stmt->execute([$invoice_number, $purchase_type, $user_id, $user_full_name]);
            
            $new_purchase_id = $db->lastInsertId();
            
            $_SESSION['purchase_message'] = "✅ New " . ucfirst($purchase_type) . " purchase created! Invoice: <strong>$invoice_number</strong>";
            $_SESSION['purchase_message_type'] = 'success';
            header('Location: purchases.php?id=' . $new_purchase_id . '&type=' . $purchase_type);
            exit;
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ================================================================
// CHECK SESSION MESSAGES
// ================================================================
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
// BUILD MEDICINE QUERY WITH ADDED BY - FIXED FOR NO EXPIRY
// ================================================================
$med_query = "
    SELECT 
        MIN(m.id) as id,
        m.medication_name,
        m.category,
        m.unit,
        m.branch_id,
        m.added_by,
        m.added_by_name,
        u.full_name as added_by_full_name,
        SUM(CASE 
            WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE() OR m.expiry_date = '0000-00-00') 
            THEN m.quantity 
            ELSE 0 
        END) as total_quantity,
        MIN(m.reorder_level) as reorder_level,
        MIN(m.unit_cost) as unit_cost,
        MIN(m.selling_price) as selling_price,
        MIN(m.supplier) as supplier,
        MIN(m.expiry_date) as expiry_date,
        GROUP_CONCAT(m.id) as batch_ids,
        GROUP_CONCAT(m.batch_number SEPARATOR '|') as batch_numbers,
        GROUP_CONCAT(m.quantity SEPARATOR '|') as batch_quantities,
        GROUP_CONCAT(m.expiry_date SEPARATOR '|') as batch_expiries,
        GROUP_CONCAT(m.status SEPARATOR '|') as batch_statuses,
        MIN(DATEDIFF(
            CASE 
                WHEN m.expiry_date = '0000-00-00' THEN NULL 
                ELSE m.expiry_date 
            END, 
            CURDATE()
        )) as days_remaining,
        CASE 
            WHEN SUM(CASE 
                WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE() OR m.expiry_date = '0000-00-00') 
                THEN m.quantity 
                ELSE 0 
            END) > 0 THEN 'active'
            ELSE 'inactive'
        END as computed_status
    FROM medications_inventory m
    LEFT JOIN users u ON m.added_by = u.id
    WHERE m.branch_id = ?
";

$med_params = [$user_branch_id];

if (!empty($search)) {
    $med_query .= " AND m.medication_name LIKE ?";
    $med_params[] = "%$search%";
}
if (!empty($category_filter)) {
    $med_query .= " AND m.category = ?";
    $med_params[] = $category_filter;
}
if ($status_filter === 'active') {
    $med_query .= " HAVING computed_status = 'active'";
} elseif ($status_filter === 'inactive') {
    $med_query .= " HAVING computed_status = 'inactive'";
}
if ($stock_filter === 'low') {
    $med_query .= " HAVING total_quantity > 0 AND total_quantity <= reorder_level AND computed_status = 'active'";
} elseif ($stock_filter === 'out') {
    $med_query .= " HAVING total_quantity = 0";
}
if ($expiry_filter === 'expiring') {
    $med_query .= " AND m.expiry_date IS NOT NULL 
                    AND m.expiry_date != '0000-00-00'
                    AND m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}
if ($expiry_filter === 'expired') {
    $med_query .= " AND m.expiry_date IS NOT NULL 
                    AND m.expiry_date != '0000-00-00'
                    AND m.expiry_date < CURDATE()";
}

$med_query .= " GROUP BY m.medication_name, m.category, m.unit, m.branch_id ORDER BY m.medication_name ASC";

$stmt = $db->prepare($med_query);
$stmt->execute($med_params);
$medicines = $stmt->fetchAll();

// ================================================================
// BUILD EQUIPMENT QUERY WITH ADDED BY - FIXED FOR NO EXPIRY
// ================================================================
$equip_query = "
    SELECT 
        MIN(e.id) as id,
        e.equipment_name,
        e.category,
        e.unit,
        e.branch_id,
        e.added_by,
        e.added_by_name,
        u.full_name as added_by_full_name,
        SUM(CASE 
            WHEN e.status = 'active' AND (e.expiry_date IS NULL OR e.expiry_date >= CURDATE() OR e.expiry_date = '0000-00-00') 
            THEN e.quantity 
            ELSE 0 
        END) as total_quantity,
        MIN(e.reorder_level) as reorder_level,
        MIN(e.unit_cost) as unit_cost,
        MIN(e.selling_price) as selling_price,
        MIN(e.supplier) as supplier,
        MIN(e.expiry_date) as expiry_date,
        GROUP_CONCAT(e.id) as batch_ids,
        GROUP_CONCAT(e.batch_number SEPARATOR '|') as batch_numbers,
        GROUP_CONCAT(e.quantity SEPARATOR '|') as batch_quantities,
        GROUP_CONCAT(e.expiry_date SEPARATOR '|') as batch_expiries,
        GROUP_CONCAT(e.status SEPARATOR '|') as batch_statuses,
        MIN(DATEDIFF(
            CASE 
                WHEN e.expiry_date = '0000-00-00' THEN NULL 
                ELSE e.expiry_date 
            END, 
            CURDATE()
        )) as days_remaining,
        CASE 
            WHEN SUM(CASE 
                WHEN e.status = 'active' AND (e.expiry_date IS NULL OR e.expiry_date >= CURDATE() OR e.expiry_date = '0000-00-00') 
                THEN e.quantity 
                ELSE 0 
            END) > 0 THEN 'active'
            ELSE 'inactive'
        END as computed_status
    FROM medical_equipment e
    LEFT JOIN users u ON e.added_by = u.id
    WHERE e.branch_id = ?
";

$equip_params = [$user_branch_id];

if (!empty($search)) {
    $equip_query .= " AND e.equipment_name LIKE ?";
    $equip_params[] = "%$search%";
}
if (!empty($category_filter)) {
    $equip_query .= " AND e.category = ?";
    $equip_params[] = $category_filter;
}
if ($status_filter === 'active') {
    $equip_query .= " HAVING computed_status = 'active'";
} elseif ($status_filter === 'inactive') {
    $equip_query .= " HAVING computed_status = 'inactive'";
}
if ($stock_filter === 'low') {
    $equip_query .= " HAVING total_quantity > 0 AND total_quantity <= reorder_level AND computed_status = 'active'";
} elseif ($stock_filter === 'out') {
    $equip_query .= " HAVING total_quantity = 0";
}
if ($expiry_filter === 'expiring') {
    $equip_query .= " AND e.expiry_date IS NOT NULL 
                    AND e.expiry_date != '0000-00-00'
                    AND e.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}
if ($expiry_filter === 'expired') {
    $equip_query .= " AND e.expiry_date IS NOT NULL 
                    AND e.expiry_date != '0000-00-00'
                    AND e.expiry_date < CURDATE()";
}

$equip_query .= " GROUP BY e.equipment_name, e.category, e.unit, e.branch_id ORDER BY e.equipment_name ASC";

$stmt = $db->prepare($equip_query);
$stmt->execute($equip_params);
$equipment = $stmt->fetchAll();

// ================================================================
// GET STATISTICS - MEDICINES - FIXED
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
");
$stmt->execute([$user_branch_id]);
$total_medicines = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    AND quantity > 0
");
$stmt->execute([$user_branch_id]);
$med_in_stock = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND status = 'active'
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    AND quantity = 0
");
$stmt->execute([$user_branch_id]);
$med_out_of_stock = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    AND quantity > 0 
    AND quantity <= reorder_level
");
$stmt->execute([$user_branch_id]);
$med_low_stock = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND expiry_date IS NOT NULL 
    AND expiry_date != '0000-00-00'
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$med_expiring = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND expiry_date IS NOT NULL 
    AND expiry_date != '0000-00-00'
    AND expiry_date < CURDATE()
");
$stmt->execute([$user_branch_id]);
$med_expired = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND status = 'inactive'
");
$stmt->execute([$user_branch_id]);
$med_inactive = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COALESCE(SUM(quantity * selling_price), 0) as total_value 
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
");
$stmt->execute([$user_branch_id]);
$med_value = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

// ================================================================
// GET STATISTICS - EQUIPMENT - FIXED
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT equipment_name) as count 
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
");
$stmt->execute([$user_branch_id]);
$total_equipment = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT equipment_name) as count 
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    AND quantity > 0
");
$stmt->execute([$user_branch_id]);
$equip_in_stock = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT equipment_name) as count 
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND status = 'active'
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    AND quantity = 0
");
$stmt->execute([$user_branch_id]);
$equip_out_of_stock = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT equipment_name) as count 
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    AND quantity > 0 
    AND quantity <= reorder_level
");
$stmt->execute([$user_branch_id]);
$equip_low_stock = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT equipment_name) as count 
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND expiry_date IS NOT NULL 
    AND expiry_date != '0000-00-00'
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$equip_expiring = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT equipment_name) as count 
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND expiry_date IS NOT NULL 
    AND expiry_date != '0000-00-00'
    AND expiry_date < CURDATE()
");
$stmt->execute([$user_branch_id]);
$equip_expired = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT equipment_name) as count 
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND status = 'inactive'
");
$stmt->execute([$user_branch_id]);
$equip_inactive = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COALESCE(SUM(quantity * selling_price), 0) as total_value 
    FROM medical_equipment 
    WHERE branch_id = ? 
    AND status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
");
$stmt->execute([$user_branch_id]);
$equip_value = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

$total_inventory_value = $med_value + $equip_value;

// ================================================================
// GET IN_PROGRESS PURCHASES FOR CURRENT USER
// ================================================================
$in_progress_purchases = [];
$stmt = $db->prepare("
    SELECT id, invoice_number, purchase_type, status, total_items, total_quantity, total_cost, created_at, created_by
    FROM purchases 
    WHERE created_by = ? AND status = 'IN_PROGRESS'
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$in_progress_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET VIEW DATA WITH ADDED BY
// ================================================================
$view_data = null;
$view_batches = [];
$view_name = '';

if ($view_id > 0) {
    if ($view_type === 'medicine') {
        $stmt = $db->prepare("
            SELECT medication_name, added_by, added_by_name 
            FROM medications_inventory 
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$view_id, $user_branch_id]);
        $name_row = $stmt->fetch();
        if ($name_row) {
            $view_name = $name_row['medication_name'];
            $stmt = $db->prepare("
                SELECT m.*, u.full_name as added_by_full_name
                FROM medications_inventory m
                LEFT JOIN users u ON m.added_by = u.id
                WHERE m.medication_name = ? AND m.branch_id = ?
                ORDER BY m.id ASC
            ");
            $stmt->execute([$view_name, $user_branch_id]);
            $view_batches = $stmt->fetchAll();
            $view_data = $view_batches[0] ?? null;
        }
    } else {
        $stmt = $db->prepare("
            SELECT equipment_name, added_by, added_by_name 
            FROM medical_equipment 
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$view_id, $user_branch_id]);
        $name_row = $stmt->fetch();
        if ($name_row) {
            $view_name = $name_row['equipment_name'];
            $stmt = $db->prepare("
                SELECT e.*, u.full_name as added_by_full_name
                FROM medical_equipment e
                LEFT JOIN users u ON e.added_by = u.id
                WHERE e.equipment_name = ? AND e.branch_id = ?
                ORDER BY e.id ASC
            ");
            $stmt->execute([$view_name, $user_branch_id]);
            $view_batches = $stmt->fetchAll();
            $view_data = $view_batches[0] ?? null;
        }
    }
}

// ================================================================
// GET ALL MEDICINE NAMES FOR AUTO-SEARCH
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

$all_equipment_names = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT equipment_name, category, selling_price 
        FROM medical_equipment 
        WHERE branch_id = ? 
        ORDER BY equipment_name
    ");
    $stmt->execute([$user_branch_id]);
    $all_equipment_names = $stmt->fetchAll();
} catch (Exception $e) {
    $all_equipment_names = [];
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
            color: rgba(255,255,255,0.9);
            border: 2px solid rgba(255,255,255,0.3);
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
            border-color: white;
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .btn-purchase-history {
            background: var(--warning);
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
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
            text-decoration: none;
        }
        
        .btn-purchase-history:hover {
            background: #B45309;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(217, 119, 6, 0.35);
        }
        
        .purchase-banner {
            background: var(--success-light);
            border: 2px solid var(--success);
            border-radius: 12px;
            padding: 12px 18px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        [data-theme="dark"] .purchase-banner {
            background: #1A3A2A;
            border-color: #34D399;
        }
        
        .purchase-banner .banner-text {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .purchase-banner .banner-text i { color: var(--success); }
        
        .purchase-banner .btn-go-to-purchase {
            background: var(--success);
            color: white;
            padding: 6px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .purchase-banner .btn-go-to-purchase:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
        }
        
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
        .stat-card.pink { background: linear-gradient(135deg, #DB2777, #BE185D); }
        
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
        .card-title .title-added-by { color: var(--success); }
        
        .result-count {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .result-count strong { color: var(--primary); }
        
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
        
        .filter-btn.clear-filter {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .filter-btn.clear-filter:hover {
            background: var(--danger);
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
        
        .table-wrap {
            overflow-x: auto;
            max-height: 500px;
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
            min-width: 1200px;
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
        .col-status { min-width: 70px; text-align: center; }
        .col-added-by { min-width: 120px; }
        .col-actions { min-width: 60px; text-align: center; }
        
        .added-by-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            background: var(--purple-light);
            color: var(--purple);
        }
        
        [data-theme="dark"] .added-by-tag {
            background: #2D1B4E;
            color: #C4B5FD;
        }
        
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
        
        .expiry-badge.no-expiry {
            background: #E2E8F0;
            color: var(--text-muted);
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
        
        .days-remaining.forever {
            background: #E2E8F0;
            color: var(--text-muted);
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
        
        .message-box.info {
            background: var(--primary-light);
            color: #0A4CA8;
            border: 2px solid #6EA8FE;
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
        
        [data-theme="dark"] .message-box.info {
            background: #1A2A4A;
            color: #6EA8FE;
            border-color: #6EA8FE;
        }
        
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
        
        .empty-state .sub {
            font-size: 0.8rem;
            margin-top: 4px;
        }
        
        .footer {
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
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
            .tab-btn { font-size: 0.7rem; padding: 8px 12px; }
            .tab-btn .badge { display: none; }
            .page-header .page-title { font-size: 1.3rem; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .stat-card { padding: 10px 12px; min-height: 65px; }
            .header-actions { flex-direction: column; align-items: stretch; width: 100%; }
            .header-actions .btn-add-medicine,
            .header-actions .btn-add-equipment,
            .header-actions .btn-outline,
            .header-actions .btn-purchase-history { width: 100%; justify-content: center; }
            .data-table { min-width: 800px; }
            .col-added-by { min-width: 100px; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card .stat-number { font-size: 0.9rem; }
            .stat-card { padding: 8px 10px; min-height: 55px; }
            .stat-card .stat-icon { font-size: 1rem; }
            .data-table { min-width: 650px; font-size: 0.65rem; }
            .data-table th, .data-table td { padding: 4px 6px; }
            .col-price { min-width: 90px; font-size: 0.7rem; }
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
            <!-- Add Medicine Button -->
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="create_purchase">
                <input type="hidden" name="purchase_type" value="medicine">
                <button type="submit" class="btn-add-medicine">
                    <i class="fas fa-plus-circle"></i> Add Medicine
                </button>
            </form>
            
            <!-- Add Equipment Button -->
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="create_purchase">
                <input type="hidden" name="purchase_type" value="equipment">
                <button type="submit" class="btn-add-equipment">
                    <i class="fas fa-plus-circle"></i> Add Equipment
                </button>
            </form>
            
            <!-- Purchase History Button -->
            <a href="purchase_history.php" class="btn-purchase-history">
                <i class="fas fa-history"></i> Purchase History
            </a>
            
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
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'info' ? 'fa-info-circle' : 'fa-exclamation-circle') ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- IN PROGRESS PURCHASE BANNER -->
    <!-- ================================================================ -->
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
                <div class="stat-label">Has Expired Batches</div>
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
                <div class="stat-sub">No active batches</div>
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
                <a href="inventory.php?tab=medicines&expiry=expired" class="filter-btn <?= $expiry_filter === 'expired' ? 'active' : '' ?>" style="border-color:#7F1D1D;color:#7F1D1D;">
                    <i class="fas fa-skull"></i> Has Expired
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

        <!-- Medicine Table WITH ADDED BY COLUMN -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list title-blue"></i> Medicine List
                    <span class="result-count">(<strong><?= count($medicines) ?></strong> unique medicines)</span>
                    <?php if ($total_medicines > 0): ?>
                        <span class="result-count ml-2">Total Value: <strong>TSh <?= formatMoney($med_value) ?></strong></span>
                    <?php endif; ?>
                </h3>
                <div style="font-size:0.7rem;color:var(--text-muted);">
                    <i class="fas fa-info-circle"></i> Click "Add Medicine" to create a new purchase
                </div>
            </div>
            
            <?php if (count($medicines) > 0): ?>
                <div class="table-wrap">
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
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($medicines as $item): ?>
                                <?php
                                    $total_qty = $item['total_quantity'] ?? 0;
                                    $stock_status = 'ok';
                                    $stock_label = 'In Stock';
                                    if ($total_qty <= 0) {
                                        $stock_status = 'out';
                                        $stock_label = 'Out of Stock';
                                    } elseif ($total_qty <= $item['reorder_level']) {
                                        $stock_status = 'low';
                                        $stock_label = 'Low Stock';
                                    }
                                    
                                    $batch_numbers = $item['batch_numbers'] ?? '';
                                    $batch_count = $batch_numbers ? count(explode('|', $batch_numbers)) : 0;
                                    $first_batch = $batch_numbers ? explode('|', $batch_numbers)[0] : '';
                                    
                                    $expiry_status = 'no-expiry';
                                    $days = '-';
                                    $days_class = 'forever';
                                    $expiry_date = $item['expiry_date'] ?? '';
                                    if (!empty($expiry_date) && $expiry_date !== '0000-00-00') {
                                        $days = $item['days_remaining'] ?? 0;
                                        if ($days < 0) {
                                            $expiry_status = 'expired';
                                            $days_class = 'danger';
                                        } elseif ($days <= 30) {
                                            $expiry_status = 'expiring';
                                            $days_class = 'warning';
                                        } else {
                                            $expiry_status = 'valid';
                                            $days_class = 'good';
                                        }
                                    }
                                    
                                    $display_status = $item['computed_status'] ?? 'active';
                                    $price_display = ($item['selling_price'] ?? 0) > 0 ? number_format($item['selling_price'], 0) : 'FREE';
                                    
                                    $added_by_display = !empty($item['added_by_full_name']) ? $item['added_by_full_name'] : ($item['added_by_name'] ?? 'System');
                                ?>
                                <tr>
                                    <td class="col-sno"><?= $counter++ ?></td>
                                    <td class="col-name">
                                        <strong><?= htmlspecialchars($item['medication_name']) ?></strong>
                                        <?php if ($batch_count > 1): ?>
                                            <span style="font-size:0.55rem;margin-left:4px;background:var(--primary-light);color:var(--primary);padding:1px 8px;border-radius:10px;">
                                                <?= $batch_count ?> batches
                                            </span>
                                        <?php endif; ?>
                                        <div style="font-size:0.6rem;color:var(--text-muted);"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></div>
                                    </td>
                                    <td class="col-category"><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                    <td class="col-qty"><strong><?= $total_qty ?></strong></td>
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
                                            <span class="expiry-badge <?= $expiry_status ?>">
                                                <?= date('d/m/Y', strtotime($expiry_date)) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="expiry-badge no-expiry">
                                                <i class="fas fa-infinity"></i> No Expiry
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-days">
                                        <?php if (!empty($expiry_date) && $expiry_date !== '0000-00-00' && $days !== '-'): ?>
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
                                            <span class="days-remaining forever">
                                                <i class="fas fa-infinity"></i> ∞
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-batch">
                                        <?php if (!empty($first_batch)): ?>
                                            <span class="batch-number"><?= htmlspecialchars($first_batch) ?></span>
                                            <?php if ($batch_count > 1): ?>
                                                <span style="font-size:0.6rem;color:var(--text-muted);">+<?= $batch_count - 1 ?> more</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-status">
                                        <span class="status-badge <?= $display_status ?>">
                                            <?= ucfirst($display_status) ?>
                                        </span>
                                    </td>
                                    <td class="col-added-by">
                                        <span class="added-by-tag">
                                            <i class="fas fa-user-circle"></i>
                                            <?= htmlspecialchars($added_by_display) ?>
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
                    <p class="sub">Click "Add Medicine" to create a new purchase</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TAB CONTENT: EQUIPMENT -->
    <!-- ================================================================ -->
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
                <div class="stat-label">Has Expired Batches</div>
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
                <div class="stat-sub">No active batches</div>
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
                <a href="inventory.php?tab=equipment&expiry=expired" class="filter-btn <?= $expiry_filter === 'expired' ? 'active' : '' ?>" style="border-color:#7F1D1D;color:#7F1D1D;">
                    <i class="fas fa-skull"></i> Has Expired
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

        <!-- Equipment Table WITH ADDED BY COLUMN -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list title-purple"></i> Equipment List
                    <span class="result-count">(<strong><?= count($equipment) ?></strong> unique equipment)</span>
                    <?php if ($total_equipment > 0): ?>
                        <span class="result-count ml-2">Total Value: <strong>TSh <?= formatMoney($equip_value) ?></strong></span>
                    <?php endif; ?>
                </h3>
                <div style="font-size:0.7rem;color:var(--text-muted);">
                    <i class="fas fa-info-circle"></i> Click "Add Equipment" to create a new purchase
                </div>
            </div>
            
            <?php if (count($equipment) > 0): ?>
                <div class="table-wrap">
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
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($equipment as $item): ?>
                                <?php
                                    $total_qty = $item['total_quantity'] ?? 0;
                                    $stock_status = 'ok';
                                    $stock_label = 'In Stock';
                                    if ($total_qty <= 0) {
                                        $stock_status = 'out';
                                        $stock_label = 'Out of Stock';
                                    } elseif ($total_qty <= $item['reorder_level']) {
                                        $stock_status = 'low';
                                        $stock_label = 'Low Stock';
                                    }
                                    
                                    $batch_numbers = $item['batch_numbers'] ?? '';
                                    $batch_count = $batch_numbers ? count(explode('|', $batch_numbers)) : 0;
                                    $first_batch = $batch_numbers ? explode('|', $batch_numbers)[0] : '';
                                    
                                    $expiry_status = 'no-expiry';
                                    $days = '-';
                                    $days_class = 'forever';
                                    $expiry_date = $item['expiry_date'] ?? '';
                                    if (!empty($expiry_date) && $expiry_date !== '0000-00-00') {
                                        $days = $item['days_remaining'] ?? 0;
                                        if ($days < 0) {
                                            $expiry_status = 'expired';
                                            $days_class = 'danger';
                                        } elseif ($days <= 30) {
                                            $expiry_status = 'expiring';
                                            $days_class = 'warning';
                                        } else {
                                            $expiry_status = 'valid';
                                            $days_class = 'good';
                                        }
                                    }
                                    
                                    $display_status = $item['computed_status'] ?? 'active';
                                    $price_display = ($item['selling_price'] ?? 0) > 0 ? number_format($item['selling_price'], 0) : 'FREE';
                                    
                                    $added_by_display = !empty($item['added_by_full_name']) ? $item['added_by_full_name'] : ($item['added_by_name'] ?? 'System');
                                ?>
                                <tr>
                                    <td class="col-sno"><?= $counter++ ?></td>
                                    <td class="col-name">
                                        <strong><?= htmlspecialchars($item['equipment_name']) ?></strong>
                                        <?php if ($batch_count > 1): ?>
                                            <span style="font-size:0.55rem;margin-left:4px;background:var(--primary-light);color:var(--primary);padding:1px 8px;border-radius:10px;">
                                                <?= $batch_count ?> batches
                                            </span>
                                        <?php endif; ?>
                                        <div style="font-size:0.6rem;color:var(--text-muted);"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></div>
                                    </td>
                                    <td class="col-category"><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                    <td class="col-qty"><strong><?= $total_qty ?></strong></td>
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
                                            <span class="expiry-badge <?= $expiry_status ?>">
                                                <?= date('d/m/Y', strtotime($expiry_date)) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="expiry-badge no-expiry">
                                                <i class="fas fa-infinity"></i> No Expiry
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-days">
                                        <?php if (!empty($expiry_date) && $expiry_date !== '0000-00-00' && $days !== '-'): ?>
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
                                            <span class="days-remaining forever">
                                                <i class="fas fa-infinity"></i> ∞
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-batch">
                                        <?php if (!empty($first_batch)): ?>
                                            <span class="batch-number"><?= htmlspecialchars($first_batch) ?></span>
                                            <?php if ($batch_count > 1): ?>
                                                <span style="font-size:0.6rem;color:var(--text-muted);">+<?= $batch_count - 1 ?> more</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-status">
                                        <span class="status-badge <?= $display_status ?>">
                                            <?= ucfirst($display_status) ?>
                                        </span>
                                    </td>
                                    <td class="col-added-by">
                                        <span class="added-by-tag">
                                            <i class="fas fa-user-circle"></i>
                                            <?= htmlspecialchars($added_by_display) ?>
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
                    <p class="sub">Click "Add Equipment" to create a new purchase</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VIEW MODAL -->
    <!-- ================================================================ -->
    <?php if ($view_data && !empty($view_batches)): ?>
    <div class="modal-overlay show" id="viewModal" style="display:flex;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;justify-content:center;align-items:center;animation:fadeIn 0.3s ease;">
        <div class="modal-content" style="background:var(--bg-card);border-radius:16px;padding:24px 28px;max-width:850px;width:95%;max-height:90vh;overflow-y:auto;border:2px solid var(--border-color);box-shadow:var(--shadow-lg);animation:slideUp 0.3s ease;">
            <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;padding-bottom:12px;border-bottom:2px solid var(--border-color);margin-bottom:16px;">
                <div class="modal-title" style="font-size:1.1rem;font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:10px;">
                    <i class="fas fa-eye"></i> 
                    <?= ucfirst($view_type) ?> Details - <?= htmlspecialchars($view_name) ?>
                </div>
                <a href="inventory.php?tab=<?= $active_tab ?>" class="modal-close" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--text-secondary);transition:all 0.3s ease;text-decoration:none;">&times;</a>
            </div>
            
            <div class="view-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
                <div class="view-item full-width" style="grid-column:1/-1;padding:8px 12px;background:var(--bg-body);border-radius:6px;border:1px solid var(--border-color);">
                    <div class="label" style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;letter-spacing:0.05em;">Name</div>
                    <div class="value" style="font-size:0.85rem;font-weight:600;color:var(--text-primary);margin-top:2px;"><?= htmlspecialchars($view_name) ?></div>
                </div>
                <div class="view-item" style="padding:8px 12px;background:var(--bg-body);border-radius:6px;border:1px solid var(--border-color);">
                    <div class="label" style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;letter-spacing:0.05em;">Category</div>
                    <div class="value" style="font-size:0.85rem;font-weight:600;color:var(--text-primary);margin-top:2px;"><?= htmlspecialchars($view_data['category'] ?? 'N/A') ?></div>
                </div>
                <div class="view-item" style="padding:8px 12px;background:var(--bg-body);border-radius:6px;border:1px solid var(--border-color);">
                    <div class="label" style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;letter-spacing:0.05em;">Unit</div>
                    <div class="value" style="font-size:0.85rem;font-weight:600;color:var(--text-primary);margin-top:2px;"><?= htmlspecialchars($view_data['unit'] ?? 'pcs') ?></div>
                </div>
                <div class="view-item" style="padding:8px 12px;background:var(--bg-body);border-radius:6px;border:1px solid var(--border-color);">
                    <div class="label" style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;letter-spacing:0.05em;">Total Quantity</div>
                    <div class="value" style="font-size:0.85rem;font-weight:600;color:var(--text-primary);margin-top:2px;">
                        <?php 
                            $total_qty = 0;
                            foreach ($view_batches as $batch) {
                                if ($batch['status'] === 'active' && (empty($batch['expiry_date']) || $batch['expiry_date'] >= date('Y-m-d'))) {
                                    $total_qty += $batch['quantity'];
                                }
                            }
                        ?>
                        <strong><?= $total_qty ?></strong>
                        <?php if ($total_qty <= 0): ?>
                            <span class="stock-badge out" style="padding:2px 8px;border-radius:8px;font-size:0.65rem;font-weight:600;display:inline-flex;align-items:center;gap:3px;background:var(--danger-light);color:var(--danger);animation:pulse 1s infinite;">Out of Stock</span>
                        <?php elseif ($total_qty <= $view_data['reorder_level']): ?>
                            <span class="stock-badge low" style="padding:2px 8px;border-radius:8px;font-size:0.65rem;font-weight:600;display:inline-flex;align-items:center;gap:3px;background:var(--warning-light);color:var(--warning);animation:pulse 1.5s infinite;">Low Stock</span>
                        <?php else: ?>
                            <span class="stock-badge ok" style="padding:2px 8px;border-radius:8px;font-size:0.65rem;font-weight:600;display:inline-flex;align-items:center;gap:3px;background:var(--success-light);color:var(--success);">In Stock</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="view-item" style="padding:8px 12px;background:var(--bg-body);border-radius:6px;border:1px solid var(--border-color);">
                    <div class="label" style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;letter-spacing:0.05em;">Reorder Level</div>
                    <div class="value" style="font-size:0.85rem;font-weight:600;color:var(--text-primary);margin-top:2px;"><?= $view_data['reorder_level'] ?></div>
                </div>
                <div class="view-item" style="padding:8px 12px;background:var(--bg-body);border-radius:6px;border:1px solid var(--border-color);">
                    <div class="label" style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;letter-spacing:0.05em;">Selling Price</div>
                    <div class="value" style="font-size:0.85rem;font-weight:600;color:var(--text-primary);margin-top:2px;">
                        <?= ($view_data['selling_price'] ?? 0) > 0 ? 'TSh ' . number_format($view_data['selling_price'], 0) : 'FREE' ?>
                    </div>
                </div>
                <div class="view-item" style="padding:8px 12px;background:var(--bg-body);border-radius:6px;border:1px solid var(--border-color);">
                    <div class="label" style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;letter-spacing:0.05em;">Added By</div>
                    <div class="value" style="font-size:0.85rem;font-weight:600;color:var(--text-primary);margin-top:2px;">
                        <span class="added-by-tag" style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:12px;font-size:0.65rem;font-weight:600;background:var(--purple-light);color:var(--purple);">
                            <i class="fas fa-user-circle"></i>
                            <?= !empty($view_data['added_by_full_name']) ? htmlspecialchars($view_data['added_by_full_name']) : ($view_data['added_by_name'] ?? 'System') ?>
                        </span>
                    </div>
                </div>
                <div class="view-item" style="padding:8px 12px;background:var(--bg-body);border-radius:6px;border:1px solid var(--border-color);">
                    <div class="label" style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;letter-spacing:0.05em;">Status</div>
                    <div class="value" style="font-size:0.85rem;font-weight:600;color:var(--text-primary);margin-top:2px;">
                        <span class="status-badge <?= $total_qty > 0 ? 'active' : 'inactive' ?>" style="padding:2px 8px;border-radius:10px;font-size:0.6rem;font-weight:600;display:inline-flex;align-items:center;gap:3px;">
                            <?= $total_qty > 0 ? 'ACTIVE' : 'INACTIVE' ?>
                        </span>
                    </div>
                </div>
                <div class="view-item full-width" style="grid-column:1/-1;padding:8px 12px;background:var(--bg-body);border-radius:6px;border:1px solid var(--border-color);">
                    <div class="label" style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;letter-spacing:0.05em;">Branch</div>
                    <div class="value" style="font-size:0.85rem;font-weight:600;color:var(--text-primary);margin-top:2px;">
                        <span style="background:var(--primary-light);color:var(--primary);padding:2px 12px;border-radius:12px;font-size:0.75rem;">
                            <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div style="margin-top:16px;">
                <div style="font-size:0.8rem;font-weight:600;margin-bottom:8px;color:var(--text-primary);">
                    <i class="fas fa-layer-group"></i> Batches (<?= count($view_batches) ?>)
                </div>
                <div style="overflow-x:auto;margin-top:10px;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.75rem;">
                        <thead>
                            <tr>
                                <th style="background:var(--primary);color:white;padding:6px 10px;font-size:0.6rem;text-transform:uppercase;font-weight:700;text-align:left;width:25%;">Batch</th>
                                <th style="background:var(--primary);color:white;padding:6px 10px;font-size:0.6rem;text-transform:uppercase;font-weight:700;text-align:center;width:12%;">Quantity</th>
                                <th style="background:var(--primary);color:white;padding:6px 10px;font-size:0.6rem;text-transform:uppercase;font-weight:700;text-align:left;width:20%;">Expiry</th>
                                <th style="background:var(--primary);color:white;padding:6px 10px;font-size:0.6rem;text-transform:uppercase;font-weight:700;text-align:center;width:12%;">Days</th>
                                <th style="background:var(--primary);color:white;padding:6px 10px;font-size:0.6rem;text-transform:uppercase;font-weight:700;text-align:center;width:13%;">Status</th>
                                <th style="background:var(--primary);color:white;padding:6px 10px;font-size:0.6rem;text-transform:uppercase;font-weight:700;text-align:center;width:18%;">Added By</th>
                            </tr>
                        </thead>
                        <tbody>
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
                                    
                                    if ($days_left < 0) {
                                        $exp_status = 'expired';
                                        $status_label = 'Expired';
                                        $status_class = 'inactive';
                                    } elseif ($days_left <= 30) {
                                        $exp_status = 'expiring';
                                        $status_label = 'Expiring Soon';
                                        $status_class = 'active';
                                    } else {
                                        $exp_status = 'valid';
                                        $status_label = 'Valid';
                                        $status_class = 'active';
                                    }
                                } else {
                                    $exp_status = 'no-expiry';
                                    $status_label = 'Active';
                                    $status_class = 'active';
                                    $days_left = '∞';
                                }
                                
                                if ($batch_status === 'inactive') {
                                    $status_label = 'Inactive';
                                    $status_class = 'inactive';
                                }
                                
                                $batch_added_by = !empty($batch['added_by_full_name']) ? $batch['added_by_full_name'] : ($batch['added_by_name'] ?? 'System');
                            ?>
                                <tr style="border-bottom:1px solid var(--border-color);">
                                    <td style="padding:5px 10px;"><span style="font-family:monospace;font-size:0.65rem;font-weight:600;padding:1px 6px;border-radius:4px;background:var(--primary-light);color:var(--primary);"><?= htmlspecialchars($batch['batch_number'] ?? 'N/A') ?></span></td>
                                    <td style="padding:5px 10px;text-align:center;font-weight:600;"><?= $batch_qty ?></td>
                                    <td style="padding:5px 10px;">
                                        <?php if (!empty($batch_expiry) && $batch_expiry !== '0000-00-00'): ?>
                                            <span class="expiry-badge <?= $exp_status ?>" style="padding:2px 8px;border-radius:8px;font-size:0.6rem;font-weight:600;display:inline-flex;align-items:center;gap:3px;">
                                                <?= date('d/m/Y', strtotime($batch_expiry)) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="expiry-badge no-expiry" style="padding:2px 8px;border-radius:8px;font-size:0.6rem;font-weight:600;display:inline-flex;align-items:center;gap:3px;background:#E2E8F0;color:var(--text-muted);">
                                                <i class="fas fa-infinity"></i> No Expiry
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:5px 10px;text-align:center;">
                                        <?php if ($days_left === '∞'): ?>
                                            <span class="days-remaining forever" style="font-size:0.65rem;font-weight:600;padding:1px 6px;border-radius:8px;display:inline-flex;align-items:center;gap:3px;background:#E2E8F0;color:var(--text-muted);">
                                                <i class="fas fa-infinity"></i> ∞
                                            </span>
                                        <?php elseif ($days_left !== '-'): ?>
                                            <span class="days-remaining <?= $days_left < 0 ? 'danger' : ($days_left <= 30 ? 'warning' : 'good') ?>" style="font-size:0.65rem;font-weight:600;padding:1px 6px;border-radius:8px;display:inline-flex;align-items:center;gap:3px;">
                                                <?php if ($days_left < 0): ?>
                                                    <i class="fas fa-skull"></i> EXP
                                                <?php elseif ($days_left <= 30): ?>
                                                    <i class="fas fa-clock"></i> <?= $days_left ?>d
                                                <?php else: ?>
                                                    <i class="fas fa-check"></i> <?= $days_left ?>d
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="days-remaining forever" style="font-size:0.65rem;font-weight:600;padding:1px 6px;border-radius:8px;display:inline-flex;align-items:center;gap:3px;background:#E2E8F0;color:var(--text-muted);">
                                                <i class="fas fa-infinity"></i> ∞
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:5px 10px;text-align:center;">
                                        <span class="status-badge <?= $status_class ?>" style="padding:2px 8px;border-radius:10px;font-size:0.6rem;font-weight:600;display:inline-flex;align-items:center;gap:3px;">
                                            <?= $status_label ?>
                                        </span>
                                    </td>
                                    <td style="padding:5px 10px;text-align:center;">
                                        <span class="added-by-tag" style="display:inline-flex;align-items:center;gap:4px;padding:1px 8px;border-radius:10px;font-size:0.6rem;font-weight:600;background:var(--purple-light);color:var(--purple);">
                                            <i class="fas fa-user-circle"></i>
                                            <?= htmlspecialchars($batch_added_by) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div style="display:flex;gap:10px;margin-top:18px;padding-top:14px;border-top:2px solid var(--border-color);flex-wrap:wrap;">
                <a href="inventory.php?tab=<?= $active_tab ?>" class="btn-cancel" style="background:transparent;color:var(--text-secondary);border:2px solid var(--border-color);padding:10px 24px;border-radius:10px;font-weight:600;font-size:0.9rem;cursor:pointer;transition:all 0.3s ease;text-decoration:none;">
                    <i class="fas fa-times"></i> Close
                </a>
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
            <span class="text-gray-400 mx-2">|</span>
            Inventory Management (Grouped by Name)
            <span class="text-gray-400 mx-2">|</span>
            Total Value: <strong>TSh <?= formatMoney($total_inventory_value) ?></strong>
            <span class="text-gray-400 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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
// CLOSE MESSAGE
// ================================================================
setTimeout(function() {
    var messageBox = document.querySelector('.message-box');
    if (messageBox) {
        messageBox.style.display = 'none';
    }
}, 5000);

// ================================================================
// KEYBOARD SHORTCUTS
// ================================================================
document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') return;
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        var searchInput = document.querySelector('.search-form input[type="text"]');
        if (searchInput) { searchInput.focus(); searchInput.select(); }
    }
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(function(modal) {
            modal.style.display = 'none';
        });
    }
});

// ================================================================
// CONSOLE LOG
// ================================================================
console.log('%c💊 Braick - Pharmacy Inventory (FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ FIXED: Equipment without expiry date now display correctly', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Medicines: <?= $total_medicines ?> | Equipment: <?= $total_equipment ?>', 'font-size:13px; color:#059669;');
console.log('%c💰 Total Value: TSh <?= formatMoney($total_inventory_value) ?>', 'font-size:13px; color:#D97706;');
console.log('%c👤 Added By column visible in both Medicine and Equipment tables', 'font-size:13px; color:#7C3AED;');
console.log('%c✅ Add Medicine → Creates Medicine Purchase', 'font-size:13px; color:#34D399;');
console.log('%c✅ Add Equipment → Creates Equipment Purchase', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>