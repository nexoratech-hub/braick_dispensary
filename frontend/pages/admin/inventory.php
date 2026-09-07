<?php
// ================================================================
// FILE: frontend/pages/admin/inventory.php
// ADMIN - COMPLETE MEDICINE INVENTORY
// FIXED: All Branches shows ALL branches data combined
// FIXED: Same medicine in different branches shown separately
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
// CHECK USER ACCESS (Admin only)
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
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// GET SELECTED BRANCH FROM URL
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
if ($selected_branch_id === 'all') {
    $selected_branch_id = 'all';
    $branch_id_for_query = $user_branch_id;
} else {
    $branch_id_for_query = (int)$selected_branch_id;
}

// ================================================================
// MONEY FORMAT FUNCTIONS
// ================================================================
function formatMoney($amount) {
    if ($amount === null || $amount === '') {
        return '0.00';
    }
    return number_format((float)$amount, 2, '.', ',');
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
// GET CATEGORIES
// ================================================================
$med_categories = [];
$stmt = $db->query("SELECT DISTINCT category FROM medications_inventory WHERE category IS NOT NULL AND category != '' ORDER BY category");
$med_categories = $stmt->fetchAll();

$predefined_med_categories = [
    'Antibiotics', 'Painkillers', 'Antipyretics', 'Antihistamines',
    'Antacids', 'Antivirals', 'Antifungals', 'Antimalarials',
    'Vitamins', 'Supplements', 'Respiratory', 'Cardiovascular',
    'Diabetes', 'Hypertension', 'Dermatological', 'Eye Drops',
    'Ear Drops', 'Injectables', 'IV Fluids', 'Other'
];

// ================================================================
// PROCESS POST REQUESTS
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // ADD MEDICINE
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
        $unit_cost = getMoney($_POST['unit_cost'] ?? 0);
        $selling_price = getMoney($_POST['selling_price'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');
        $expiry_date = $_POST['expiry_date'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : $branch_id_for_query;
        
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
                    $branch_id, $status
                ]);
                
                $message = "✅ Medicine added successfully! Batch: <strong>$batch_number</strong>";
                $message_type = 'success';
                $_SESSION['inventory_message'] = $message;
                $_SESSION['inventory_message_type'] = $message_type;
                header('Location: inventory.php?tab=medicines&added=1&branch=' . $selected_branch_id);
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
    // UPDATE MEDICINE BATCH
    // ================================================================
    if ($action === 'edit_medicine') {
        $id = (int)($_POST['id'] ?? 0);
        $medication_name = trim($_POST['medication_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        if (empty($category) && !empty($_POST['category_manual'])) {
            $category = trim($_POST['category_manual']);
        }
        $unit = trim($_POST['unit'] ?? 'pcs');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reorder_level = (int)($_POST['reorder_level'] ?? 10);
        $unit_cost = getMoney($_POST['unit_cost'] ?? 0);
        $selling_price = getMoney($_POST['selling_price'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');
        $expiry_date = $_POST['expiry_date'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        $errors = [];
        if (empty($medication_name)) { $errors[] = 'Medicine name is required'; }
        if ($quantity < 0) { $errors[] = 'Quantity cannot be negative'; }
        if ($selling_price < 0) { $errors[] = 'Selling price cannot be negative'; }
        if (!empty($expiry_date) && strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
            $errors[] = 'Expiry date cannot be in the past';
        }
        
        if (empty($errors) && $id > 0) {
            try {
                $stmt = $db->prepare("
                    UPDATE medications_inventory SET
                        medication_name = ?,
                        category = ?,
                        unit = ?,
                        quantity = ?,
                        reorder_level = ?,
                        unit_cost = ?,
                        selling_price = ?,
                        supplier = ?,
                        expiry_date = ?,
                        batch_number = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([
                    $medication_name, $category, $unit, $quantity, $reorder_level,
                    $unit_cost, $selling_price, $supplier, $expiry_date, $batch_number,
                    $status, $id, $branch_id_for_query
                ]);
                
                $message = "✅ Medicine batch updated successfully!";
                $message_type = 'success';
                $_SESSION['inventory_message'] = $message;
                $_SESSION['inventory_message_type'] = $message_type;
                header('Location: inventory.php?tab=medicines&updated=1&branch=' . $selected_branch_id);
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
    // DELETE MEDICINE BATCH
    // ================================================================
    if ($action === 'delete_medicine') {
        $id = (int)($_POST['id'] ?? 0);
        $confirmed = $_POST['confirmed'] ?? false;
        
        if ($confirmed && $id > 0) {
            try {
                $stmt = $db->prepare("DELETE FROM medications_inventory WHERE id = ? AND branch_id = ?");
                $stmt->execute([$id, $branch_id_for_query]);
                
                $message = "✅ Medicine batch deleted successfully!";
                $message_type = 'success';
                $_SESSION['inventory_message'] = $message;
                $_SESSION['inventory_message_type'] = $message_type;
                header('Location: inventory.php?tab=medicines&deleted=1&branch=' . $selected_branch_id);
                exit;
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "❌ Deletion not confirmed";
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
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$delete_id = isset($_GET['delete']) ? (int)$_GET['delete'] : 0;

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$stock_filter = isset($_GET['stock']) ? trim($_GET['stock']) : '';
$expiry_filter = isset($_GET['expiry']) ? trim($_GET['expiry']) : '';

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
// BUILD MEDICINE QUERY - FIXED FOR ALL BRANCHES
// Shows same medicine in different branches as separate entries
// ================================================================
$med_query = "
    SELECT 
        MIN(m.id) as id,
        m.medication_name,
        m.category,
        m.unit,
        m.branch_id,
        b.name as branch_name,
        SUM(CASE 
            WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE()) 
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
        MIN(DATEDIFF(m.expiry_date, CURDATE())) as days_remaining,
        SUM(CASE 
            WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE()) 
            THEN m.quantity 
            ELSE 0 
        END) as active_quantity
    FROM medications_inventory m
    LEFT JOIN branches b ON m.branch_id = b.id
    WHERE 1=1
";

$med_params = [];

// ================================================================
// CRITICAL FIX: Branch filter - If 'all', NO branch filter
// Shows ALL branches combined
// ================================================================
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $med_query .= " AND m.branch_id = ?";
    $med_params[] = (int)$selected_branch_id;
}
// If 'all', NO branch filter - shows ALL branches combined

// Search filter
if (!empty($search)) {
    $med_query .= " AND m.medication_name LIKE ?";
    $med_params[] = "%$search%";
}

// Category filter
if (!empty($category_filter)) {
    $med_query .= " AND m.category = ?";
    $med_params[] = $category_filter;
}

// Expiry filter
if ($expiry_filter === 'expiring') {
    $med_query .= " AND m.expiry_date IS NOT NULL 
                    AND m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}
if ($expiry_filter === 'expired') {
    $med_query .= " AND m.expiry_date IS NOT NULL AND m.expiry_date < CURDATE()";
}

// ================================================================
// GROUP BY includes branch_id so same medicine in different branches
// appears as separate entries
// ================================================================
$med_query .= " GROUP BY m.medication_name, m.category, m.unit, m.branch_id";

// HAVING clauses
$having_conditions = [];

if ($status_filter === 'active') {
    $having_conditions[] = "SUM(CASE WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE()) THEN m.quantity ELSE 0 END) > 0";
} elseif ($status_filter === 'inactive') {
    $having_conditions[] = "SUM(CASE WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE()) THEN m.quantity ELSE 0 END) <= 0";
}

if ($stock_filter === 'low') {
    $having_conditions[] = "SUM(CASE WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE()) THEN m.quantity ELSE 0 END) > 0";
    $having_conditions[] = "SUM(CASE WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE()) THEN m.quantity ELSE 0 END) <= MIN(m.reorder_level)";
}
if ($stock_filter === 'out') {
    $having_conditions[] = "SUM(CASE WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE()) THEN m.quantity ELSE 0 END) = 0";
}

if (!empty($having_conditions)) {
    $med_query .= " HAVING " . implode(" AND ", $having_conditions);
}

$med_query .= " ORDER BY m.medication_name ASC, b.name ASC";

$stmt = $db->prepare($med_query);
$stmt->execute($med_params);
$medicines = $stmt->fetchAll();

// ================================================================
// GET STATISTICS - FOR CARDS (Summary)
// Counts each branch entry separately
// ================================================================
// Build branch condition for stats
$stats_branch_condition = "";
$stats_params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stats_branch_condition = " AND branch_id = ?";
    $stats_params[] = (int)$selected_branch_id;
}
// If 'all', no branch condition - gets ALL branches combined

// Total Medicines (counts each branch entry separately)
$sql = "
    SELECT COUNT(DISTINCT CONCAT(medication_name, '-', branch_id)) as count 
    FROM medications_inventory 
    WHERE status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$total_medicines = $stmt->fetch()['count'] ?? 0;

// Total Quantity (sum of all active quantities)
$sql = "
    SELECT COALESCE(SUM(quantity), 0) as total 
    FROM medications_inventory 
    WHERE status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$total_quantity = $stmt->fetch()['total'] ?? 0;

// In Stock (counts each branch entry separately)
$sql = "
    SELECT COUNT(DISTINCT CONCAT(medication_name, '-', branch_id)) as count 
    FROM medications_inventory 
    WHERE status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    AND quantity > 0
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$med_in_stock = $stmt->fetch()['count'] ?? 0;

// Out of Stock (counts each branch entry separately)
$sql = "
    SELECT COUNT(DISTINCT CONCAT(medication_name, '-', branch_id)) as count 
    FROM medications_inventory 
    WHERE status = 'active'
    AND quantity = 0
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$med_out_of_stock = $stmt->fetch()['count'] ?? 0;

// Low Stock (counts each branch entry separately)
$sql = "
    SELECT COUNT(DISTINCT CONCAT(medication_name, '-', branch_id)) as count 
    FROM medications_inventory 
    WHERE status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    AND quantity > 0 
    AND quantity <= reorder_level
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$med_low_stock = $stmt->fetch()['count'] ?? 0;

// Expiring Soon (counts each branch entry separately)
$sql = "
    SELECT COUNT(DISTINCT CONCAT(medication_name, '-', branch_id)) as count 
    FROM medications_inventory 
    WHERE expiry_date IS NOT NULL 
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND status = 'active'
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$med_expiring = $stmt->fetch()['count'] ?? 0;

// Expired (counts each branch entry separately)
$sql = "
    SELECT COUNT(DISTINCT CONCAT(medication_name, '-', branch_id)) as count 
    FROM medications_inventory 
    WHERE expiry_date IS NOT NULL 
    AND expiry_date < CURDATE()
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$med_expired = $stmt->fetch()['count'] ?? 0;

// Inactive (counts each branch entry separately)
$sql = "
    SELECT COUNT(DISTINCT CONCAT(medication_name, '-', branch_id)) as count 
    FROM medications_inventory 
    WHERE status = 'inactive'
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$med_inactive = $stmt->fetch()['count'] ?? 0;

// Total Value
$sql = "
    SELECT COALESCE(SUM(quantity * selling_price), 0) as total_value 
    FROM medications_inventory 
    WHERE status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$med_value = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

// ================================================================
// GET VIEW DATA
// ================================================================
$view_data = null;
$view_batches = [];
$view_name = '';

if ($view_id > 0) {
    $stmt = $db->prepare("SELECT medication_name FROM medications_inventory WHERE id = ? AND branch_id = ?");
    $stmt->execute([$view_id, $branch_id_for_query]);
    $name_row = $stmt->fetch();
    if ($name_row) {
        $view_name = $name_row['medication_name'];
        $stmt = $db->prepare("
            SELECT * FROM medications_inventory 
            WHERE medication_name = ? AND branch_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$view_name, $branch_id_for_query]);
        $view_batches = $stmt->fetchAll();
        $view_data = $view_batches[0] ?? null;
    }
}

// ================================================================
// GET EDIT DATA
// ================================================================
$edit_data = null;
if ($edit_id > 0) {
    $stmt = $db->prepare("SELECT * FROM medications_inventory WHERE id = ? AND branch_id = ?");
    $stmt->execute([$edit_id, $branch_id_for_query]);
    $edit_data = $stmt->fetch();
}

// ================================================================
// GET ALL MEDICINE NAMES FOR AUTO-SEARCH
// ================================================================
$all_medicine_names = [];
try {
    $sql = "
        SELECT DISTINCT medication_name, category, selling_price 
        FROM medications_inventory 
        WHERE branch_id = ? 
        ORDER BY medication_name
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$branch_id_for_query]);
    $all_medicine_names = $stmt->fetchAll();
} catch (Exception $e) {
    $all_medicine_names = [];
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
// PROFILE & LOGO
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG';

// Display branch name
$display_branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    foreach ($branches as $b) {
        if ($b['id'] == $selected_branch_id) {
            $display_branch_name = $b['name'];
            break;
        }
    }
}

// ================================================================
// GET SIDEBAR STATISTICS
// ================================================================
$total_employees_sidebar = 0;
$total_doctors_sidebar = 0;
$total_branches_sidebar = 0;
$module_counts = ['pharmacy' => 0, 'reception' => 0, 'laboratory' => 0, 'cashier' => 0];
$total_patients_sidebar = 0;
$today_patients_sidebar = 0;
$total_services_sidebar = 0;
$today_services_sidebar = 0;
$pending_prescriptions_sidebar = 0;
$pending_lab_tests_sidebar = 0;

try {
    $stats_branch = $selected_branch_id;
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active'");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active' AND branch_id = ?");
        $stmt->execute([(int)$stats_branch]);
    }
    $total_employees_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ?");
        $stmt->execute([(int)$stats_branch]);
    }
    $total_doctors_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $modules = ['pharmacy', 'reception', 'laboratory', 'cashier'];
    foreach ($modules as $module) {
        if ($stats_branch === 'all') {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active'");
            $stmt->execute([$module]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active' AND branch_id = ?");
            $stmt->execute([$module, (int)$stats_branch]);
        }
        $module_counts[$module] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM patients");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
        $stmt->execute([(int)$stats_branch]);
    }
    $total_patients_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = CURDATE()");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([(int)$stats_branch]);
    }
    $today_patients_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM bill_items WHERE status != 'cancelled'");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill_items WHERE branch_id = ? AND status != 'cancelled'");
        $stmt->execute([(int)$stats_branch]);
    }
    $total_services_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM bill_items WHERE status != 'cancelled' AND DATE(created_at) = CURDATE()");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill_items WHERE branch_id = ? AND status != 'cancelled' AND DATE(created_at) = CURDATE()");
        $stmt->execute([(int)$stats_branch]);
    }
    $today_services_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status IN ('pending', 'confirmed')");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status IN ('pending', 'confirmed')");
        $stmt->execute([(int)$stats_branch]);
    }
    $pending_prescriptions_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    if ($stats_branch === 'all') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status IN ('pending', 'in_progress')");
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status IN ('pending', 'in_progress')");
        $stmt->execute([(int)$stats_branch]);
    }
    $pending_lab_tests_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
    $total_branches_sidebar = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
} catch (Exception $e) {
    // ignore
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Inventory - Braick Dispensary</title>
    
    <!-- ================================================================
         FAVICON
         ================================================================ -->
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= $logo_path ?>">
    
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
            --teal: #0D9488;
            --teal-light: #CCFBF1;
            
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
            --radius: 12px;
            --radius-lg: 16px;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --border-color: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
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
        
        /* ================================================================
           EMBEDDED SIDEBAR
           ================================================================ */
        .sidebar {
            position: fixed; 
            top: 0; 
            left: 0; 
            bottom: 0;
            width: 270px; 
            background: linear-gradient(180deg, #0B4EA8 0%, #0A3D7A 100%);
            color: white;
            z-index: 50; 
            overflow-y: auto;
            overflow-x: hidden;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(0);
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
            scroll-behavior: smooth;
        }
        
        [data-theme="dark"] .sidebar {
            background: linear-gradient(180deg, #0A3D7A 0%, #082F5E 100%);
            box-shadow: 4px 0 30px rgba(0,0,0,0.5);
        }
        
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        .sidebar::-webkit-scrollbar-thumb { background: #0AA84F; border-radius: 10px; }
        
        .sidebar-brand {
            padding: 18px 16px 14px;
            border-bottom: 2px solid rgba(255,255,255,0.08);
            background: rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 5;
            backdrop-filter: blur(10px);
        }
        
        .sidebar-brand .logo {
            width: 42px; 
            height: 42px; 
            border-radius: 10px;
            object-fit: cover; 
            background: white; 
            padding: 4px;
            border: 2px solid rgba(255,255,255,0.15);
            transition: transform 0.3s ease;
        }
        
        .sidebar-brand .brand-text { 
            color: white; 
            font-weight: 700; 
            font-size: 0.95rem; 
            line-height: 1.2;
            letter-spacing: 0.5px;
        }
        
        .sidebar-brand .brand-sub { 
            color: #9EC5FE; 
            font-size: 0.65rem; 
            font-weight: 500;
            letter-spacing: 0.3px;
        }
        
        .sidebar-nav { 
            padding: 10px 8px 20px; 
        }
        
        .sidebar-nav .nav-label {
            font-size: 0.5rem; 
            text-transform: uppercase;
            letter-spacing: 0.08em; 
            color: #6EA8FE;
            padding: 8px 10px 4px;
            margin: 8px 0 2px; 
            font-weight: 700;
            opacity: 0.8;
        }
        
        .sidebar-link {
            display: flex; 
            align-items: center; 
            gap: 10px;
            padding: 8px 12px; 
            border-radius: 8px;
            color: #D2E3FC; 
            text-decoration: none;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.8rem; 
            font-weight: 500;
            margin: 1px 0;
            background: transparent;
            cursor: pointer;
            border: none;
            width: 100%;
            text-align: left;
            position: relative;
        }
        
        .sidebar-link:hover {
            background: rgba(10, 168, 79, 0.4);
            color: white;
            box-shadow: 0 4px 12px rgba(10, 168, 79, 0.2);
            transform: translateX(4px);
        }
        
        .sidebar-link.active {
            background: rgba(10, 168, 79, 0.5);
            color: white;
            box-shadow: 0 4px 12px rgba(10, 168, 79, 0.3);
        }
        
        .sidebar-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 15%;
            bottom: 15%;
            width: 4px;
            background: #0AA84F;
            border-radius: 0 4px 4px 0;
            box-shadow: 0 0 12px rgba(10, 168, 79, 0.5);
        }
        
        .sidebar-link i { 
            width: 20px; 
            text-align: center; 
            font-size: 0.9rem; 
            flex-shrink: 0;
            opacity: 0.8;
        }
        
        .sidebar-link .badge {
            margin-left: auto;
            background: rgba(255,255,255,0.12);
            padding: 1px 8px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            flex-shrink: 0;
            min-width: 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .sidebar-link .badge.danger {
            background: #EF4444;
            animation: pulse-badge 2s infinite;
            border-color: #EF4444;
        }
        
        .sidebar-link .badge.success {
            background: #059669;
            border-color: #059669;
        }
        
        .sidebar-link.logout-link {
            border-top: 2px solid rgba(255,255,255,0.06);
            padding-top: 10px;
            margin-top: 4px;
            color: #FCA5A5;
        }
        
        .sidebar-link.logout-link:hover {
            background: #DC2626;
            color: white;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
            transform: translateX(4px);
        }
        
        .sidebar-status {
            padding: 10px 16px;
            border-top: 2px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(0,0,0,0.1);
            position: sticky;
            bottom: 0;
            backdrop-filter: blur(10px);
        }
        
        .sidebar-status .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .sidebar-status .status-dot.online {
            background: #34D399;
            box-shadow: 0 0 8px rgba(52, 211, 153, 0.3);
            animation: pulse-dot 1.5s infinite;
        }
        
        .sidebar-status .status-text {
            font-size: 0.65rem;
            color: #D2E3FC;
            font-weight: 500;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.8); }
        }
        
        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
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
            backdrop-filter: blur(4px);
        }
        
        #sidebarOverlay.active {
            display: block !important;
        }
        
        @media (min-width: 1025px) {
            .sidebar {
                transform: translateX(0) !important;
                z-index: 50;
            }
            #sidebarOverlay {
                display: none !important;
            }
        }
        
        @media (max-width: 1024px) {
            .sidebar {
                width: 280px;
                transform: translateX(-100%);
                z-index: 9999;
                border-radius: 0 12px 12px 0;
            }
            .sidebar.open {
                transform: translateX(0) !important;
            }
            #sidebarOverlay {
                display: none;
                z-index: 9998;
            }
            #sidebarOverlay.active {
                display: block !important;
            }
        }
        
        /* ================================================================
           EMBEDDED HEADER
           ================================================================ */
        .embedded-header {
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
        
        .embedded-header .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .embedded-header .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
        }
        
        .embedded-header .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .embedded-header .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .embedded-header .search-wrapper .search-btn {
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
        
        .embedded-header .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.02);
        }
        
        .embedded-header .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .embedded-header .datetime i {
            color: var(--success);
        }
        
        .embedded-header .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .embedded-header .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .embedded-header .icon-btn {
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
        
        .embedded-header .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .embedded-header .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .embedded-header .notif-dot.has-notif { background: var(--danger); }
        .embedded-header .notif-dot.no-notif { background: var(--text-muted); animation: none; }
        
        .embedded-header .dark-toggle-btn {
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
        
        .embedded-header .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .embedded-header .dark-toggle-btn i { font-size: 0.9rem; }
        
        .embedded-header .branch-selector {
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
        
        .embedded-header .branch-selector:focus {
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
           PAGE HEADER BOX
           ================================================================ */
        .page-header-box {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 20px 28px;
            margin-bottom: 24px;
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.2);
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .page-header-box::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 350px;
            height: 350px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header-box .page-title {
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
        
        .page-header-box .page-title .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header-box .page-title .branch-name-display {
            background: rgba(255,255,255,0.15);
            padding: 2px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            color: white;
        }
        
        .page-header-box .page-title .btn-back-green {
            background: var(--success);
            color: white;
            border: none;
            padding: 6px 20px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
            margin-left: 4px;
        }
        
        .page-header-box .page-title .btn-back-green:hover {
            background: var(--success-dark);
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.5);
        }
        
        .page-header-box .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        
        .page-header-box .page-subtitle strong { color: white; font-weight: 600; }
        
        .page-header-box .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header-box .header-badge i { opacity: 0.8; }
        .page-header-box .header-badge.medicines {
            background: rgba(52, 211, 153, 0.2);
            border-color: rgba(52, 211, 153, 0.3);
            color: #6EE7B7;
        }
        .page-header-box .header-badge.value {
            background: rgba(251, 191, 36, 0.2);
            border-color: rgba(251, 191, 36, 0.3);
            color: #FBBF24;
        }
        .page-header-box .header-badge.stock {
            background: rgba(251, 146, 60, 0.2);
            border-color: rgba(251, 146, 60, 0.3);
            color: #FDBA74;
        }
        .page-header-box .header-badge.expiry {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.3);
            color: #F87171;
        }
        
        .header-actions {
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
        
        /* ================================================================
           STATS CARDS - 4 ROWS (2 rows of 4)
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: 14px;
            padding: 18px 20px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            min-height: 100px;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .stat-card .stat-icon {
            font-size: 1.4rem;
            opacity: 0.8;
            float: right;
            margin-top: -5px;
        }
        
        .stat-card .stat-sub {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.75);
            margin-top: 2px;
        }
        
        .stat-card .stat-badge-row {
            display: flex;
            gap: 4px;
            margin-top: 4px;
            flex-wrap: wrap;
        }
        
        .stat-card .stat-badge {
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: rgba(255,255,255,0.15);
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .stat-card .stat-badge.danger {
            background: rgba(239, 68, 68, 0.3);
            color: #FCA5A5;
        }
        
        .stat-card .stat-badge.warning {
            background: rgba(245, 158, 11, 0.3);
            color: #FCD34D;
        }
        
        .stat-card .stat-badge.success {
            background: rgba(52, 211, 153, 0.2);
            color: #6EE7B7;
        }
        
        .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
        .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
        .stat-card.pink { background: linear-gradient(135deg, #DB2777, #BE185D); }
        .stat-card.indigo { background: linear-gradient(135deg, #4F46E5, #4338CA); }
        
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
        
        .result-count {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .result-count strong { color: var(--primary); }
        
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
        
        /* ================================================================
           DATA TABLE
           ================================================================ */
        .table-wrapper {
            position: relative;
        }
        
        .table-scroll-container {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 500px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-scroll-container::-webkit-scrollbar {
            height: 8px;
            width: 6px;
        }
        
        .table-scroll-container::-webkit-scrollbar-track {
            background: var(--bg-body);
            border-radius: 4px;
        }
        
        .table-scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        .scroll-arrows {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        
        .scroll-arrow-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }
        
        .scroll-arrow-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
            transform: scale(1.05);
        }
        
        .data-table {
            width: 100%;
            min-width: 1300px;
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
        .col-branch { min-width: 110px; }
        .col-qty { min-width: 60px; text-align: center; }
        .col-reorder { min-width: 70px; text-align: center; }
        .col-stock { min-width: 100px; }
        .col-price { min-width: 130px; font-family: 'Courier New', monospace; }
        .col-expiry { min-width: 100px; }
        .col-days { min-width: 70px; text-align: center; }
        .col-batch { min-width: 130px; }
        .col-status { min-width: 80px; text-align: center; }
        .col-active { min-width: 70px; text-align: center; }
        .col-actions { min-width: 120px; text-align: center; }
        
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
        
        .action-btn {
            padding: 4px 10px;
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
        
        .action-btn.edit {
            background: var(--warning);
            color: white;
        }
        
        .action-btn.edit:hover {
            background: #B45309;
            transform: scale(1.05);
        }
        
        .action-btn.delete {
            background: var(--danger);
            color: white;
        }
        
        .action-btn.delete:hover {
            background: #991B1B;
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
            max-width: 850px;
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
            text-decoration: none;
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
        
        .form-label .required { color: var(--danger); margin-left: 2px; }
        
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
            white-space: nowrap;
            height: 42px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-toggle:hover {
            background: var(--primary-dark);
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
        
        .autocomplete-list.show { display: block; }
        
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
        
        .autocomplete-item .item-detail {
            font-size: 0.65rem;
            color: var(--text-muted);
            display: block;
        }
        
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
        
        .view-item.full-width { grid-column: 1 / -1; }
        
        .batches-table-wrap {
            overflow-x: auto;
            margin-top: 10px;
        }
        
        .batches-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        
        .batches-table thead th {
            background: var(--primary);
            color: white;
            padding: 6px 10px;
            font-size: 0.6rem;
            text-transform: uppercase;
            font-weight: 700;
            text-align: left;
        }
        
        .batches-table tbody td {
            padding: 5px 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .batches-table tbody tr:nth-child(even) {
            background: var(--primary-light);
        }
        
        .batches-table tbody tr:hover td {
            background: var(--success-light);
        }
        
        .delete-warning {
            text-align: center;
            padding: 20px;
        }
        
        .delete-warning i {
            font-size: 3rem;
            color: var(--danger);
            margin-bottom: 10px;
        }
        
        .delete-warning .warning-text {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .delete-warning .sub-text {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(4, 1fr); gap: 12px; }
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .embedded-header { left: 0; }
            .embedded-header .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 14px 16px; min-height: 80px; }
            .stat-card .stat-number { font-size: 1.3rem; }
            .search-form { flex-direction: column; align-items: stretch; }
            .search-form input, .search-form select { min-width: 100%; }
            .filter-group { justify-content: center; }
            .card { padding: 12px 14px; }
            .page-header-box { flex-direction: column; align-items: stretch !important; }
            .page-header-box .page-title { font-size: 1.2rem; }
            .embedded-header .datetime { display: none; }
            .header-actions { flex-direction: column; align-items: stretch; width: 100%; }
            .header-actions .btn-add-medicine { width: 100%; justify-content: center; }
            .form-grid { grid-template-columns: 1fr; }
            .form-grid .full-width { grid-column: 1; }
            .category-input-group { flex-direction: column; }
            .batch-input-group { flex-direction: column; }
            .batch-input-group .btn-generate { width: 100%; justify-content: center; }
            .form-actions { flex-direction: column; }
            .form-actions .btn-save, .form-actions .btn-cancel { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 10px 12px; min-height: 65px; }
            .stat-card .stat-number { font-size: 1rem; }
            .stat-card .stat-icon { font-size: 1rem; }
            .data-table { min-width: 750px; font-size: 0.65rem; }
            .data-table th, .data-table td { padding: 4px 6px; }
            .modal-content { padding: 12px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SIDEBAR OVERLAY (Mobile) -->
<!-- ================================================================ -->
<div id="sidebarOverlay"></div>

<!-- ================================================================ -->
<!-- EMBEDDED SIDEBAR -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_path ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">👑 Super Admin</p>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-label">📋 Main Menu</div>
        <a href="dashboard.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-home"></i> <span class="link-text">Dashboard</span>
        </a>
        <a href="employees.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-users"></i> <span class="link-text">Employees</span>
            <span class="badge"><?= $total_employees_sidebar ?></span>
        </a>
        <a href="patients.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-user-injured"></i> <span class="link-text">Patients</span>
            <span class="badge"><?= $total_patients_sidebar ?></span>
            <?php if ($today_patients_sidebar > 0): ?>
                <span class="badge success">+<?= $today_patients_sidebar ?></span>
            <?php endif; ?>
        </a>
        
        <div class="nav-label">⚙️ Modules</div>
        <a href="doctors_list.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-user-md"></i> <span class="link-text">Doctors</span>
            <span class="badge"><?= $total_doctors_sidebar ?></span>
        </a>
        <a href="view_pharmacy.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-prescription"></i> <span class="link-text">Pharmacy</span>
            <span class="badge"><?= $module_counts['pharmacy'] ?? 0 ?></span>
            <?php if ($pending_prescriptions_sidebar > 0): ?>
                <span class="badge danger"><?= $pending_prescriptions_sidebar ?></span>
            <?php endif; ?>
        </a>
        <a href="view_reception.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-headset"></i> <span class="link-text">Reception</span>
            <span class="badge"><?= $module_counts['reception'] ?? 0 ?></span>
        </a>
        <a href="view_laboratory.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-flask"></i> <span class="link-text">Laboratory</span>
            <span class="badge"><?= $module_counts['laboratory'] ?? 0 ?></span>
            <?php if ($pending_lab_tests_sidebar > 0): ?>
                <span class="badge danger"><?= $pending_lab_tests_sidebar ?></span>
            <?php endif; ?>
        </a>
        <a href="view_cashier.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-cash-register"></i> <span class="link-text">Cashier</span>
            <span class="badge"><?= $module_counts['cashier'] ?? 0 ?></span>
        </a>
        
        <div class="nav-label">🏢 Management</div>
        <a href="branches.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-store-alt"></i> <span class="link-text">Branches</span>
            <span class="badge"><?= $total_branches_sidebar ?></span>
        </a>
        <a href="reports.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-chart-bar"></i> <span class="link-text">Reports</span>
        </a>
        
        <div class="nav-label">👤 Account</div>
        <a href="profile.php" class="sidebar-link">
            <i class="fas fa-user-circle"></i> <span class="link-text">Profile</span>
        </a>
        <a href="/dispensary_system/frontend/pages/logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span>
        </a>
    </nav>
    
    <div class="sidebar-status">
        <span class="status-dot online"></span>
        <span class="status-text">Online</span>
    </div>
</aside>

<!-- ================================================================ -->
<!-- EMBEDDED HEADER -->
<!-- ================================================================ -->
<header class="embedded-header">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="icon-btn lg:hidden">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search medicines...">
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
        
        <span class="datetime" id="currentDateTime">
            <i class="fas fa-clock" style="color:#059669;"></i>
            <span id="clockDisplay"><?= date('d M Y • h:i:s A') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn" onclick="window.location.href='notifications.php'">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</header>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-box animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-pills"></i>
                Inventory
                <span class="role-badge-display">ADMIN</span>
                <span class="branch-name-display">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($display_branch_name) ?>
                </span>
                <a href="dashboard.php" class="btn-back-green">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </h1>
            <p class="page-subtitle">
                <strong><?= $total_medicines ?></strong> entries across branches
                <span class="header-badge medicines">
                    <i class="fas fa-pills"></i> <?= $med_in_stock ?> In Stock
                </span>
                <span class="header-badge value">
                    <i class="fas fa-coins"></i> TSh <?= formatMoneyShort($med_value) ?>
                </span>
                <span class="header-badge stock">
                    <i class="fas fa-exclamation-triangle"></i> <?= $med_low_stock ?> Low Stock
                </span>
                <span class="header-badge expiry">
                    <i class="fas fa-clock"></i> <?= $med_expiring ?> Expiring Soon
                </span>
            </p>
        </div>
        <div class="header-actions">
            <button onclick="openAddModal()" class="btn-add-medicine">
                <i class="fas fa-plus-circle"></i> Add Medicine
            </button>
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
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <!-- Card 1: Total Medicines -->
        <a href="inventory.php?tab=medicines&branch=<?= $selected_branch_id ?>" class="stat-card blue">
            <span class="stat-icon"><i class="fas fa-pills"></i></span>
            <div class="stat-number"><?= $total_medicines ?></div>
            <div class="stat-label">Total Entries</div>
            <div class="stat-sub">📦 <?= number_format($total_quantity) ?> units</div>
        </a>
        
        <!-- Card 2: In Stock -->
        <a href="inventory.php?tab=medicines&status=active&branch=<?= $selected_branch_id ?>" class="stat-card green">
            <span class="stat-icon"><i class="fas fa-check-circle"></i></span>
            <div class="stat-number"><?= $med_in_stock ?></div>
            <div class="stat-label">In Stock</div>
            <div class="stat-sub">Available entries</div>
        </a>
        
        <!-- Card 3: Low Stock -->
        <a href="inventory.php?tab=medicines&stock=low&branch=<?= $selected_branch_id ?>" class="stat-card orange">
            <span class="stat-icon"><i class="fas fa-exclamation-triangle"></i></span>
            <div class="stat-number"><?= $med_low_stock ?></div>
            <div class="stat-label">Low Stock</div>
            <div class="stat-sub">Below reorder level</div>
        </a>
        
        <!-- Card 4: Out of Stock -->
        <a href="inventory.php?tab=medicines&stock=out&branch=<?= $selected_branch_id ?>" class="stat-card red">
            <span class="stat-icon"><i class="fas fa-times-circle"></i></span>
            <div class="stat-number"><?= $med_out_of_stock ?></div>
            <div class="stat-label">Out of Stock</div>
            <div class="stat-sub">Quantity = 0</div>
        </a>
        
        <!-- Card 5: Expiring Soon -->
        <a href="inventory.php?tab=medicines&expiry=expiring&branch=<?= $selected_branch_id ?>" class="stat-card teal">
            <span class="stat-icon"><i class="fas fa-clock"></i></span>
            <div class="stat-number"><?= $med_expiring ?></div>
            <div class="stat-label">Expiring Soon</div>
            <div class="stat-sub">Within 30 days</div>
        </a>
        
        <!-- Card 6: Has Expired -->
        <a href="inventory.php?tab=medicines&expiry=expired&branch=<?= $selected_branch_id ?>" class="stat-card red">
            <span class="stat-icon"><i class="fas fa-skull"></i></span>
            <div class="stat-number"><?= $med_expired ?></div>
            <div class="stat-label">Has Expired Batches</div>
            <div class="stat-sub">Some batches expired</div>
        </a>
        
        <!-- Card 7: Inactive -->
        <a href="inventory.php?tab=medicines&status=inactive&branch=<?= $selected_branch_id ?>" class="stat-card purple">
            <span class="stat-icon"><i class="fas fa-archive"></i></span>
            <div class="stat-number"><?= $med_inactive ?></div>
            <div class="stat-label">Inactive</div>
            <div class="stat-sub">No active batches</div>
        </a>
        
        <!-- Card 8: Total Value -->
        <a href="inventory.php?tab=medicines&branch=<?= $selected_branch_id ?>" class="stat-card indigo">
            <span class="stat-icon"><i class="fas fa-coins"></i></span>
            <div class="stat-number">TSh <?= formatMoneyShort($med_value) ?></div>
            <div class="stat-label">Total Value</div>
            <div class="stat-sub">Inventory worth</div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="filter-group">
            <a href="inventory.php?tab=medicines&branch=<?= $selected_branch_id ?>" class="filter-btn <?= empty($status_filter) && empty($stock_filter) && empty($expiry_filter) ? 'active' : '' ?>">All</a>
            <a href="inventory.php?tab=medicines&status=active&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $status_filter === 'active' ? 'active' : '' ?>">Active</a>
            <a href="inventory.php?tab=medicines&status=inactive&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $status_filter === 'inactive' ? 'active' : '' ?>">Inactive</a>
            <a href="inventory.php?tab=medicines&stock=low&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $stock_filter === 'low' ? 'active' : '' ?>">Low Stock</a>
            <a href="inventory.php?tab=medicines&stock=out&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $stock_filter === 'out' ? 'active' : '' ?>">Out of Stock</a>
            <a href="inventory.php?tab=medicines&expiry=expiring&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $expiry_filter === 'expiring' ? 'active' : '' ?>">Expiring Soon</a>
            <a href="inventory.php?tab=medicines&expiry=expired&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $expiry_filter === 'expired' ? 'active' : '' ?>" style="border-color:#7F1D1D;color:#7F1D1D;">
                <i class="fas fa-skull"></i> Has Expired
            </a>
            <?php if (!empty($stock_filter) || !empty($expiry_filter) || !empty($status_filter)): ?>
                <a href="inventory.php?tab=medicines&branch=<?= $selected_branch_id ?>" class="filter-btn clear-filter">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="search-form" id="filterForm">
            <input type="hidden" name="tab" value="medicines">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
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
            <a href="inventory.php?tab=medicines&branch=<?= $selected_branch_id ?>" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- MEDICINE TABLE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <h3 class="card-title">
                    <i class="fas fa-list title-blue"></i> Medicine List
                    <span class="result-count">(<strong><?= count($medicines) ?></strong> entries)</span>
                    <?php if ($total_medicines > 0): ?>
                        <span class="result-count ml-2">Total Value: <strong>TSh <?= formatMoney($med_value) ?></strong></span>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="scroll-arrows">
                <button class="scroll-arrow-btn" onclick="scrollTable('left')" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="scroll-arrow-btn" onclick="scrollTable('right')" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <?php if (count($medicines) > 0): ?>
            <div class="table-wrapper">
                <div class="table-scroll-container" id="tableScrollContainer">
                    <table class="data-table" id="medicineTable">
                        <thead>
                            <tr>
                                <th class="col-sno">#</th>
                                <th class="col-name">Name</th>
                                <th class="col-category">Category</th>
                                <th class="col-branch">Branch</th>
                                <th class="col-qty">Total Qty</th>
                                <th class="col-reorder">Reorder</th>
                                <th class="col-stock">Stock</th>
                                <th class="col-price">Price (TSh)</th>
                                <th class="col-expiry">Expiry</th>
                                <th class="col-days">Days</th>
                                <th class="col-batch">Batches</th>
                                <th class="col-status">Status</th>
                                <th class="col-active">Active</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($medicines as $item): ?>
                                <?php
                                    $active_qty = $item['active_quantity'] ?? 0;
                                    $branch_name = $item['branch_name'] ?? 'Unknown';
                                    
                                    $stock_status = 'ok';
                                    $stock_label = 'In Stock';
                                    if ($active_qty <= 0) {
                                        $stock_status = 'out';
                                        $stock_label = 'Out of Stock';
                                    } elseif ($active_qty <= $item['reorder_level']) {
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
                                    
                                    $display_status = $active_qty > 0 ? 'active' : 'inactive';
                                    $price_display = ($item['selling_price'] ?? 0) > 0 ? number_format($item['selling_price'], 0) : 'FREE';
                                    
                                    $batch_ids = $item['batch_ids'] ?? '';
                                    $first_batch_id = $batch_ids ? explode(',', $batch_ids)[0] : 0;
                                ?>
                                <tr>
                                    <td class="col-sno"><?= $counter++ ?></td>
                                    <td class="col-name">
                                        <strong><?= htmlspecialchars($item['medication_name']) ?></strong>
                                        <?php if ($batch_count > 1): ?>
                                            <span style="font-size:0.55rem;margin-left:4px;background:var(--primary-light);color:var(--primary);padding:1px 8px;border-radius:10px;display:inline-block;">
                                                <?= $batch_count ?> batches
                                            </span>
                                        <?php endif; ?>
                                        <div style="font-size:0.65rem;color:var(--text-secondary);"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></div>
                                    </td>
                                    <td class="col-category"><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                    <td class="col-branch">
                                        <span style="font-weight:600;color:var(--primary);">🏥 <?= htmlspecialchars($branch_name) ?></span>
                                    </td>
                                    <td class="col-qty"><strong><?= $active_qty ?></strong></td>
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
                                                <span style="font-size:0.6rem;color:var(--text-secondary);">+<?= $batch_count - 1 ?> more</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-status">
                                        <span class="status-badge <?= $display_status ?>">
                                            <?= ucfirst($display_status) ?>
                                        </span>
                                    </td>
                                    <td class="col-active">
                                        <span class="stock-badge <?= $active_qty > 0 ? 'ok' : 'out' ?>">
                                            <i class="fas <?= $active_qty > 0 ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                            <?= $active_qty > 0 ? 'YES' : 'NO' ?>
                                        </span>
                                    </td>
                                    <td class="col-actions">
                                        <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                            <a href="inventory.php?tab=medicines&view=<?= $item['id'] ?>&branch=<?= $selected_branch_id ?>" class="action-btn view" title="View Batches">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="inventory.php?tab=medicines&edit=<?= $first_batch_id ?>&branch=<?= $selected_branch_id ?>" class="action-btn edit" title="Edit Batch">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="inventory.php?tab=medicines&delete=<?= $first_batch_id ?>&branch=<?= $selected_branch_id ?>" class="action-btn delete" title="Delete Batch" onclick="return confirmDelete(<?= $first_batch_id ?>, '<?= addslashes($item['medication_name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-pills"></i>
                <p>No medicines found</p>
                <p style="color:var(--text-secondary);font-size:0.85rem;">Click "Add Medicine" to get started</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-400 mx-2">|</span>
            Medicine Inventory
            <span class="text-gray-400 mx-2">|</span>
            <strong><?= $total_medicines ?></strong> entries · 
            <strong><?= number_format($total_quantity) ?></strong> units · 
            TSh <strong><?= formatMoney($med_value) ?></strong>
            <span class="text-gray-400 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- ADD MEDICINE MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="addMedicineModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-plus-circle"></i> Add Medicine Batch
            </div>
            <button class="modal-close" onclick="closeModal('addMedicineModal')">&times;</button>
        </div>
        
        <form method="POST" action="" id="addMedicineForm">
            <input type="hidden" name="action" value="add_medicine">
            
            <div class="form-grid">
                <div class="full-width form-row">
                    <label class="form-label">Medicine Name <span class="required">*</span></label>
                    <div class="autocomplete-container">
                        <input type="text" name="medication_name" id="medicineNameInput" class="form-control" 
                               placeholder="e.g. Paracetamol 500mg" required autocomplete="off">
                        <div class="autocomplete-list" id="medicineAutocomplete"></div>
                    </div>
                    <div class="help-text">Type to search existing medicine. If new, it will be created as new batch.</div>
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
                    <label class="form-label">Branch <span class="required">*</span></label>
                    <select name="branch_id" class="form-control" required>
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= ($selected_branch_id != 'all' && $selected_branch_id == $b['id']) ? 'selected' : '' ?>>
                                🏥 <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
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
                    <input type="text" name="unit_cost" class="form-control money-input" placeholder="0" value="0">
                    <div class="help-text">Auto-format with commas</div>
                </div>
                
                <div class="form-row">
                    <label class="form-label">Selling Price (TSh) <span class="required">*</span></label>
                    <input type="text" name="selling_price" class="form-control money-input" placeholder="0" value="0" required>
                    <div class="help-text">Auto-format with commas | 0 = Free</div>
                </div>
                
                <div class="form-row">
                    <label class="form-label">Supplier</label>
                    <input type="text" name="supplier" class="form-control" placeholder="Supplier name">
                </div>
                
                <div class="form-row">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-control">
                    <div class="help-text">Leave empty = No expiry (Active Forever)</div>
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
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Add Batch</button>
                <button type="button" class="btn-cancel" onclick="closeModal('addMedicineModal')"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT (Full) -->
<!-- ================================================================ -->
<script>
// ================================================================
// SIDEBAR TOGGLE
// ================================================================
(function() {
    var sidebar = document.getElementById('sidebar');
    var toggleBtn = document.getElementById('sidebarToggle');
    var overlay = document.getElementById('sidebarOverlay');
    
    function toggleSidebar() {
        if (!sidebar) return;
        sidebar.classList.toggle('open');
        if (overlay) {
            if (sidebar.classList.contains('open')) {
                overlay.style.display = 'block';
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            } else {
                overlay.style.display = 'none';
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
    }
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            if (sidebar) {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }
})();

// ================================================================
// DARK MODE TOGGLE
// ================================================================
(function() {
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        if (darkIcon) darkIcon.className = 'fas fa-sun';
        if (darkText) darkText.textContent = 'Light';
    }
    
    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', function(e) {
            e.preventDefault();
            var isDarkNow = htmlElement.getAttribute('data-theme') === 'dark';
            if (isDarkNow) {
                htmlElement.removeAttribute('data-theme');
                if (darkIcon) darkIcon.className = 'fas fa-moon';
                if (darkText) darkText.textContent = 'Dark';
                localStorage.setItem('darkMode', 'false');
                document.cookie = "dark_mode=false; path=/";
            } else {
                htmlElement.setAttribute('data-theme', 'dark');
                if (darkIcon) darkIcon.className = 'fas fa-sun';
                if (darkText) darkText.textContent = 'Light';
                localStorage.setItem('darkMode', 'true');
                document.cookie = "dark_mode=true; path=/";
            }
        });
    }
})();

// ================================================================
// CLOCK
// ================================================================
function updateClock() {
    var now = new Date();
    var dateStr = now.toLocaleDateString('en-US', {
        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
    });
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var el = document.getElementById('clockDisplay');
    if (el) {
        el.textContent = dateStr + ' • ' + timeStr;
    }
}
setInterval(updateClock, 1000);
updateClock();

// ================================================================
// BRANCH SWITCHER
// ================================================================
function switchBranch(branchId) {
    var url = new URL(window.location.href);
    url.searchParams.set('branch', branchId);
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
        var branch = document.getElementById('branchSelector')?.value || 'all';
        window.location.href = 'inventory.php?tab=medicines&search=' + encodeURIComponent(query) + '&branch=' + branch;
    }
}

searchBtn?.addEventListener('click', performSearch);
searchInput?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') performSearch();
});

// Keyboard shortcut
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
});

// ================================================================
// TABLE SCROLL
// ================================================================
function scrollTable(direction) {
    var container = document.getElementById('tableScrollContainer');
    if (!container) return;
    var scrollAmount = 300;
    if (direction === 'left') {
        container.scrollLeft -= scrollAmount;
    } else {
        container.scrollLeft += scrollAmount;
    }
}

// ================================================================
// AUTO-SEARCH - Medicine Name
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
// AUTO MONEY FORMAT
// ================================================================
(function() {
    'use strict';
    
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
            
            input.addEventListener('input', function() { autoFormat(this); });
            input.addEventListener('focus', function() {
                var raw = this.value.replace(/,/g, '');
                this.value = raw;
                this.select();
            });
            input.addEventListener('blur', function() {
                if (this.value) {
                    this.value = formatWithCommas(this.value);
                } else {
                    this.value = '0';
                }
            });
        });
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMoneyInputs);
    } else {
        initMoneyInputs();
    }
    
    document.addEventListener('modalOpened', function() {
        setTimeout(initMoneyInputs, 150);
    });
    
    var observer = new MutationObserver(function() {
        setTimeout(initMoneyInputs, 100);
    });
    observer.observe(document.body, { childList: true, subtree: true });
})();

// ================================================================
// MODAL FUNCTIONS
// ================================================================
function openAddModal() {
    var modal = document.getElementById('addMedicineModal');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
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
    var select, manual;
    if (type === 'med') {
        select = document.getElementById('medCategorySelect');
        manual = document.getElementById('medCategoryManual');
    } else if (type === 'editMed') {
        select = document.getElementById('editMedCategorySelect');
        manual = document.getElementById('editMedCategoryManual');
    } else {
        return;
    }
    if (!select || !manual) return;
    if (manual.style.display === 'none') {
        manual.style.display = 'block';
        select.style.display = 'none';
        manual.focus();
    } else {
        manual.style.display = 'none';
        select.style.display = 'block';
        select.value = '';
    }
}

// ================================================================
// GENERATE BATCH
// ================================================================
function generateBatch(type) {
    var now = new Date();
    var dateStr = now.getFullYear() + 
                  String(now.getMonth() + 1).padStart(2, '0') + 
                  String(now.getDate()).padStart(2, '0');
    var random = Math.random().toString(36).substring(2, 8).toUpperCase();
    var batch = 'BATCH-' + dateStr + '-' + random;
    var inputId = type === 'med' ? 'medBatchInput' : 'equipBatchInput';
    var input = document.getElementById(inputId);
    if (input) input.value = batch;
}

// ================================================================
// CONFIRM DELETE
// ================================================================
function confirmDelete(id, name) {
    return confirm('Are you sure you want to delete this batch of "' + name + '"?\nThis action cannot be undone!');
}

// ================================================================
// CONSOLE
// ================================================================
console.log('%c💊 Admin - Medicine Inventory', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Branch Filter: <?= $selected_branch_id ?>', 'font-size:13px; color:#059669;');
console.log('%c✅ If "all" - shows ALL branches data combined (each branch separate)', 'font-size:13px; color:#34D399;');
console.log('%c✅ Total Entries: <?= $total_medicines ?>', 'font-size:13px; color:#0891B2;');
console.log('%c✅ Total Quantity: <?= number_format($total_quantity) ?> units', 'font-size:13px; color:#0891B2;');
console.log('%c💰 Total Value: TSh <?= formatMoney($med_value) ?>', 'font-size:13px; color:#D97706;');
console.log('%c🖼️ Logo: <?= $logo_path ?>', 'font-size:12px; color:#6EA8FE;');
console.log('%c✅ Same medicine in different branches shown separately', 'font-size:13px; color:#34D399; font-weight:bold;');
</script>

</body>
</html>