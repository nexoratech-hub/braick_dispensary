<?php
// ================================================================
// FILE: frontend/pages/admin/purchases.php
// ADMIN - PURCHASE MANAGEMENT WITH PDF INVOICE
// SHOWS ALL IN_PROGRESS PURCHASES WITH JOIN BUTTONS
// ADMIN CAN COMPLETE OR CANCEL ANY PURCHASE
// FIXED: Category saving, Quantity update, Status set to active
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
$user_is_online = $_SESSION['is_online'] ?? 1;

// ================================================================
// GET SELECTED BRANCH FROM URL
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
if ($selected_branch_id === 'all') {
    $branch_id_for_query = $user_branch_id;
} else {
    $branch_id_for_query = (int)$selected_branch_id;
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
// SIDEBAR STATISTICS - GET ALL REQUIRED VARIABLES
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
} catch (Exception $e) {}

// Check if added_by exists in medical_equipment
try {
    $stmt = $db->query("SHOW COLUMNS FROM medical_equipment LIKE 'added_by'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `medical_equipment` ADD COLUMN `added_by` INT NULL AFTER `status`");
        $db->exec("ALTER TABLE `medical_equipment` ADD COLUMN `added_by_name` VARCHAR(100) NULL AFTER `added_by`");
    }
} catch (Exception $e) {}

// Check if cancelled_reason exists in purchases
try {
    $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'cancelled_reason'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `purchases` ADD COLUMN `cancelled_reason` TEXT NULL AFTER `status`");
    }
} catch (Exception $e) {}

// Check if cancelled_by exists in purchases
try {
    $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'cancelled_by'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `purchases` ADD COLUMN `cancelled_by` INT NULL AFTER `cancelled_reason`");
    }
} catch (Exception $e) {}

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
// GET PURCHASE ID AND TYPE FROM URL
// ================================================================
$purchase_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$purchase_type = isset($_GET['type']) ? $_GET['type'] : 'medicine';
$action = isset($_GET['action']) ? $_GET['action'] : '';

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

function cleanMoney($value) {
    return str_replace(',', '', $value);
}

function getMoney($value) {
    $clean = cleanMoney($value);
    return floatval($clean);
}

// ================================================================
// PROCESS POST REQUESTS
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_post = $_POST['action'] ?? '';
    
    // ================================================================
    // JOIN PURCHASE
    // ================================================================
    if ($action_post === 'join_purchase') {
        $purchase_id_join = (int)($_POST['purchase_id'] ?? 0);
        $join_type = $_POST['purchase_type'] ?? 'medicine';
        
        if ($purchase_id_join > 0) {
            $stmt = $db->prepare("SELECT id, status, invoice_number, purchase_type FROM purchases WHERE id = ? AND status = 'IN_PROGRESS'");
            $stmt->execute([$purchase_id_join]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($purchase) {
                header('Location: purchases.php?id=' . $purchase_id_join . '&type=' . $purchase['purchase_type'] . '&branch=' . $selected_branch_id);
                exit;
            } else {
                $message = "❌ This purchase is not available or already completed.";
                $message_type = 'error';
            }
        } else {
            $message = "❌ Invalid purchase ID.";
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // CREATE NEW PURCHASE
    // ================================================================
    if ($action_post === 'create_purchase') {
        $purchase_type_new = $_POST['purchase_type'] ?? 'medicine';
        
        $date = date('Ymd');
        $prefix = $purchase_type_new === 'medicine' ? 'INV-MED' : 'INV-EQP';
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM purchases WHERE DATE(created_at) = CURDATE() AND purchase_type = ?");
        $stmt->execute([$purchase_type_new]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        $invoice_number = $prefix . '-' . $date . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        
        try {
            $stmt = $db->prepare("
                INSERT INTO purchases (invoice_number, purchase_type, created_by, created_by_name, status, created_at)
                VALUES (?, ?, ?, ?, 'IN_PROGRESS', NOW())
            ");
            $stmt->execute([$invoice_number, $purchase_type_new, $user_id, $user_full_name]);
            
            $new_purchase_id = $db->lastInsertId();
            
            $_SESSION['purchase_message'] = "✅ New " . ucfirst($purchase_type_new) . " purchase created! Invoice: <strong>$invoice_number</strong>";
            $_SESSION['purchase_message_type'] = 'success';
            header('Location: purchases.php?id=' . $new_purchase_id . '&type=' . $purchase_type_new . '&branch=' . $selected_branch_id);
            exit;
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // ADD MEDICINE TO PURCHASE - FIXED: Category saving
    // ================================================================
    if ($action_post === 'add_medicine') {
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);
        $medicine_id = (int)($_POST['medicine_id'] ?? 0);
        $medication_name = trim($_POST['medication_name'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $buying_price = (float)str_replace(',', '', $_POST['buying_price'] ?? 0);
        $selling_price = (float)str_replace(',', '', $_POST['selling_price'] ?? 0);
        $expiry_date = $_POST['expiry_date'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $category = trim($_POST['category'] ?? '');
        
        // ================================================================
        // FIX: If category is '__other__', use manual category instead
        // ================================================================
        if ($category === '__other__' && !empty($_POST['category_manual'])) {
            $category = trim($_POST['category_manual']);
        }
        // If category is still empty, use 'Other' as fallback
        if (empty($category)) {
            $category = 'Other';
        }
        
        $unit = trim($_POST['unit'] ?? 'pcs');
        $reorder_level = (int)($_POST['reorder_level'] ?? 10);
        $supplier = trim($_POST['supplier'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        // Generate batch number if empty
        if (empty($batch_number)) {
            $batch_number = 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        }
        
        $errors = [];
        if ($purchase_id_post <= 0) { $errors[] = 'Invalid purchase'; }
        if (empty($medication_name)) { $errors[] = 'Medicine name is required'; }
        if ($quantity <= 0) { $errors[] = 'Quantity must be greater than 0'; }
        if ($buying_price < 0) { $errors[] = 'Buying price cannot be negative'; }
        if ($selling_price < 0) { $errors[] = 'Selling price cannot be negative'; }
        if (!empty($expiry_date) && strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
            $errors[] = 'Expiry date cannot be in the past';
        }
        
        $stmt = $db->prepare("SELECT status, created_by, purchase_type FROM purchases WHERE id = ?");
        $stmt->execute([$purchase_id_post]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$purchase) {
            $errors[] = 'Purchase not found';
        } elseif ($purchase['status'] !== 'IN_PROGRESS') {
            $errors[] = 'This purchase is already completed. Cannot add more items.';
        }
        
        if (empty($errors)) {
            try {
                // Check if medicine exists in inventory
                $stmt = $db->prepare("
                    SELECT id, medication_name FROM medications_inventory 
                    WHERE medication_name = ? AND branch_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$medication_name, $user_branch_id]);
                $existing_medicine = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$existing_medicine) {
                    $stmt = $db->prepare("
                        INSERT INTO medications_inventory (
                            medication_name, category, unit, quantity, reorder_level,
                            unit_cost, selling_price, supplier, expiry_date, batch_number,
                            branch_id, status, added_by, added_by_name, created_at
                        ) VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $medication_name, $category, $unit, $reorder_level,
                        $buying_price, $selling_price, $supplier, $expiry_date, $batch_number,
                        $user_branch_id, $status, $user_id, $user_full_name
                    ]);
                    
                    $medicine_id = $db->lastInsertId();
                    $is_new = true;
                } else {
                    $medicine_id = $existing_medicine['id'];
                    $is_new = false;
                }
                
                $total_selling_value = $quantity * $selling_price;
                $total_buying_cost = $quantity * $buying_price;
                
                $stmt = $db->prepare("
                    INSERT INTO purchase_items 
                    (purchase_id, item_type, item_id, quantity, 
                     buying_price, selling_price, 
                     total_buying_cost, total_selling_value,
                     added_by, added_by_name, added_at)
                    VALUES (?, 'medicine', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $purchase_id_post, 
                    $medicine_id, 
                    $quantity, 
                    $buying_price, 
                    $selling_price,
                    $total_buying_cost,
                    $total_selling_value,
                    $user_id, 
                    $user_full_name
                ]);
                
                // Update purchase totals
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(*) as total_items,
                        SUM(quantity) as total_quantity,
                        SUM(total_buying_cost) as total_buying_cost,
                        SUM(total_selling_value) as total_selling_value
                    FROM purchase_items 
                    WHERE purchase_id = ?
                ");
                $stmt->execute([$purchase_id_post]);
                $totals = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    UPDATE purchases 
                    SET total_items = ?, 
                        total_quantity = ?, 
                        total_buying_cost = ?, 
                        total_selling_value = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $totals['total_items'] ?? 0,
                    $totals['total_quantity'] ?? 0,
                    $totals['total_buying_cost'] ?? 0,
                    $totals['total_selling_value'] ?? 0,
                    $purchase_id_post
                ]);
                
                if ($is_new) {
                    $message = "✅ New medicine <strong>" . htmlspecialchars($medication_name) . "</strong> added to purchase (will be saved to inventory on completion)";
                } else {
                    $message = "✅ Added <strong>" . htmlspecialchars($medication_name) . "</strong> x $quantity (New Batch - will be saved to inventory on completion)";
                }
                $message_type = 'success';
                
                $_SESSION['purchase_message'] = $message;
                $_SESSION['purchase_message_type'] = $message_type;
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=' . $purchase_type . '&branch=' . $selected_branch_id);
                exit;
                
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
        
        if (!empty($errors)) {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // ADD EQUIPMENT TO PURCHASE - FIXED: Category saving
    // ================================================================
    if ($action_post === 'add_equipment') {
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);
        $equipment_id = (int)($_POST['equipment_id'] ?? 0);
        $equipment_name = trim($_POST['equipment_name'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $buying_price = (float)str_replace(',', '', $_POST['buying_price'] ?? 0);
        $selling_price = (float)str_replace(',', '', $_POST['selling_price'] ?? 0);
        $expiry_date = $_POST['expiry_date'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $category = trim($_POST['category'] ?? '');
        
        // ================================================================
        // FIX: If category is '__other__', use manual category instead
        // ================================================================
        if ($category === '__other__' && !empty($_POST['category_manual'])) {
            $category = trim($_POST['category_manual']);
        }
        // If category is still empty, use 'Other' as fallback
        if (empty($category)) {
            $category = 'Other';
        }
        
        $unit = trim($_POST['unit'] ?? 'pcs');
        $reorder_level = (int)($_POST['reorder_level'] ?? 5);
        $supplier = trim($_POST['supplier'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        // Generate batch number if empty
        if (empty($batch_number)) {
            $batch_number = 'EQP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        }
        
        $errors = [];
        if ($purchase_id_post <= 0) { $errors[] = 'Invalid purchase'; }
        if (empty($equipment_name)) { $errors[] = 'Equipment name is required'; }
        if ($quantity <= 0) { $errors[] = 'Quantity must be greater than 0'; }
        if ($buying_price < 0) { $errors[] = 'Buying price cannot be negative'; }
        if ($selling_price < 0) { $errors[] = 'Selling price cannot be negative'; }
        if (!empty($expiry_date) && strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
            $errors[] = 'Expiry date cannot be in the past';
        }
        
        $stmt = $db->prepare("SELECT status, created_by, purchase_type FROM purchases WHERE id = ?");
        $stmt->execute([$purchase_id_post]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$purchase) {
            $errors[] = 'Purchase not found';
        } elseif ($purchase['status'] !== 'IN_PROGRESS') {
            $errors[] = 'This purchase is already completed. Cannot add more items.';
        }
        
        if (empty($errors)) {
            try {
                // Check if equipment exists in inventory
                $stmt = $db->prepare("
                    SELECT id, equipment_name FROM medical_equipment 
                    WHERE equipment_name = ? AND branch_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$equipment_name, $user_branch_id]);
                $existing_equipment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$existing_equipment) {
                    $stmt = $db->prepare("
                        INSERT INTO medical_equipment (
                            equipment_name, category, unit, quantity, reorder_level,
                            unit_cost, selling_price, supplier, expiry_date, batch_number,
                            branch_id, status, added_by, added_by_name, created_at
                        ) VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $equipment_name, $category, $unit, $reorder_level,
                        $buying_price, $selling_price, $supplier, $expiry_date, $batch_number,
                        $user_branch_id, $status, $user_id, $user_full_name
                    ]);
                    
                    $equipment_id = $db->lastInsertId();
                    $is_new = true;
                } else {
                    $equipment_id = $existing_equipment['id'];
                    $is_new = false;
                }
                
                $total_selling_value = $quantity * $selling_price;
                $total_buying_cost = $quantity * $buying_price;
                
                $stmt = $db->prepare("
                    INSERT INTO purchase_items 
                    (purchase_id, item_type, item_id, quantity, 
                     buying_price, selling_price, 
                     total_buying_cost, total_selling_value,
                     added_by, added_by_name, added_at)
                    VALUES (?, 'equipment', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $purchase_id_post, 
                    $equipment_id, 
                    $quantity, 
                    $buying_price, 
                    $selling_price,
                    $total_buying_cost,
                    $total_selling_value,
                    $user_id, 
                    $user_full_name
                ]);
                
                // Update purchase totals
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(*) as total_items,
                        SUM(quantity) as total_quantity,
                        SUM(total_buying_cost) as total_buying_cost,
                        SUM(total_selling_value) as total_selling_value
                    FROM purchase_items 
                    WHERE purchase_id = ?
                ");
                $stmt->execute([$purchase_id_post]);
                $totals = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    UPDATE purchases 
                    SET total_items = ?, 
                        total_quantity = ?, 
                        total_buying_cost = ?, 
                        total_selling_value = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $totals['total_items'] ?? 0,
                    $totals['total_quantity'] ?? 0,
                    $totals['total_buying_cost'] ?? 0,
                    $totals['total_selling_value'] ?? 0,
                    $purchase_id_post
                ]);
                
                if ($is_new) {
                    $message = "✅ New equipment <strong>" . htmlspecialchars($equipment_name) . "</strong> added to purchase (will be saved to inventory on completion)";
                } else {
                    $message = "✅ Added <strong>" . htmlspecialchars($equipment_name) . "</strong> x $quantity (New Batch - will be saved to inventory on completion)";
                }
                $message_type = 'success';
                
                $_SESSION['purchase_message'] = $message;
                $_SESSION['purchase_message_type'] = $message_type;
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=' . $purchase_type . '&branch=' . $selected_branch_id);
                exit;
                
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
        
        if (!empty($errors)) {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // COMPLETE PURCHASE - FIXED: Quantity ADD, Status ACTIVE
    // ================================================================
    if ($action_post === 'complete_purchase') {
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);
        
        $stmt = $db->prepare("
            SELECT id, status, created_by, invoice_number, purchase_type, total_items, total_quantity, total_buying_cost, total_selling_value
            FROM purchases WHERE id = ?
        ");
        $stmt->execute([$purchase_id_post]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $errors = [];
        if (!$purchase) {
            $errors[] = 'Purchase not found';
        } elseif ($purchase['status'] !== 'IN_PROGRESS') {
            $errors[] = 'This purchase is already completed';
        } elseif ($purchase['total_items'] <= 0) {
            $errors[] = 'Cannot complete empty purchase. Add at least one item.';
        }
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Get all items with details
                $stmt = $db->prepare("
                    SELECT 
                        pi.*,
                        CASE 
                            WHEN pi.item_type = 'medicine' THEN mi.medication_name 
                            WHEN pi.item_type = 'equipment' THEN eq.equipment_name 
                        END as item_name,
                        CASE 
                            WHEN pi.item_type = 'medicine' THEN mi.branch_id 
                            WHEN pi.item_type = 'equipment' THEN eq.branch_id 
                        END as branch_id,
                        CASE 
                            WHEN pi.item_type = 'medicine' THEN mi.category 
                            WHEN pi.item_type = 'equipment' THEN eq.category 
                        END as category,
                        CASE 
                            WHEN pi.item_type = 'medicine' THEN mi.unit 
                            WHEN pi.item_type = 'equipment' THEN eq.unit 
                        END as unit,
                        pi.buying_price,
                        pi.selling_price,
                        pi.total_buying_cost,
                        pi.total_selling_value
                    FROM purchase_items pi
                    LEFT JOIN medications_inventory mi ON pi.item_id = mi.id AND pi.item_type = 'medicine'
                    LEFT JOIN medical_equipment eq ON pi.item_id = eq.id AND pi.item_type = 'equipment'
                    WHERE pi.purchase_id = ?
                ");
                $stmt->execute([$purchase_id_post]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $updated_count = 0;
                
                foreach ($items as $item) {
                    if ($item['item_type'] === 'medicine') {
                        // Check if medicine exists in inventory
                        $stmt = $db->prepare("
                            SELECT id, quantity, status FROM medications_inventory 
                            WHERE id = ?
                            LIMIT 1
                        ");
                        $stmt->execute([$item['item_id']]);
                        $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($inventory) {
                            // ================================================================
                            // FIX: Calculate new quantity by ADDING to existing
                            // ================================================================
                            $new_qty = $inventory['quantity'] + $item['quantity'];
                            
                            // ================================================================
                            // FIX: Set status to 'active' when completing purchase
                            // ================================================================
                            $stmt = $db->prepare("
                                UPDATE medications_inventory 
                                SET quantity = ?, 
                                    unit_cost = ?,
                                    selling_price = ?,
                                    status = 'active',
                                    updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $new_qty, 
                                $item['buying_price'],
                                $item['selling_price'],
                                $item['item_id']
                            ]);
                            $updated_count++;
                        }
                    } else {
                        // Equipment
                        $stmt = $db->prepare("
                            SELECT id, quantity, status FROM medical_equipment 
                            WHERE id = ?
                            LIMIT 1
                        ");
                        $stmt->execute([$item['item_id']]);
                        $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($inventory) {
                            // ================================================================
                            // FIX: Calculate new quantity by ADDING to existing
                            // ================================================================
                            $new_qty = $inventory['quantity'] + $item['quantity'];
                            
                            // ================================================================
                            // FIX: Set status to 'active' when completing purchase
                            // ================================================================
                            $stmt = $db->prepare("
                                UPDATE medical_equipment 
                                SET quantity = ?, 
                                    unit_cost = ?,
                                    selling_price = ?,
                                    status = 'active',
                                    updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $new_qty, 
                                $item['buying_price'],
                                $item['selling_price'],
                                $item['item_id']
                            ]);
                            $updated_count++;
                        }
                    }
                }
                
                // Update purchase status
                $stmt = $db->prepare("
                    UPDATE purchases 
                    SET status = 'COMPLETED', completed_at = NOW(), updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$purchase_id_post]);
                
                $db->commit();
                
                $message = "✅ Purchase <strong>{$purchase['invoice_number']}</strong> completed successfully! ($updated_count items updated in inventory)";
                $message_type = 'success';
                $_SESSION['purchase_message'] = $message;
                $_SESSION['purchase_message_type'] = $message_type;
                header('Location: purchases.php?action=view&id=' . $purchase_id_post . '&branch=' . $selected_branch_id);
                exit;
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // CANCEL PURCHASE - ADMIN CAN CANCEL ANY PURCHASE
    // ================================================================
    if ($action_post === 'cancel_purchase') {
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);
        $cancel_reason = trim($_POST['cancel_reason'] ?? '');
        
        $stmt = $db->prepare("
            SELECT id, status, invoice_number
            FROM purchases WHERE id = ?
        ");
        $stmt->execute([$purchase_id_post]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $errors = [];
        if (!$purchase) {
            $errors[] = 'Purchase not found';
        } elseif ($purchase['status'] === 'COMPLETED') {
            $errors[] = 'This purchase is already completed. Cannot cancel.';
        } elseif ($purchase['status'] === 'CANCELLED') {
            $errors[] = 'This purchase is already cancelled.';
        }
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Update purchase status to CANCELLED
                $stmt = $db->prepare("
                    UPDATE purchases 
                    SET status = 'CANCELLED', 
                        cancelled_reason = ?,
                        cancelled_by = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $cancel_reason ?: 'Cancelled by Admin',
                    $user_id,
                    $purchase_id_post
                ]);
                
                $db->commit();
                
                $message = "✅ Purchase <strong>{$purchase['invoice_number']}</strong> cancelled successfully!";
                $message_type = 'success';
                $_SESSION['purchase_message'] = $message;
                $_SESSION['purchase_message_type'] = $message_type;
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=' . $purchase_type . '&branch=' . $selected_branch_id);
                exit;
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // DELETE PURCHASE ITEM
    // ================================================================
    if ($action_post === 'delete_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);
        
        $stmt = $db->prepare("SELECT status FROM purchases WHERE id = ?");
        $stmt->execute([$purchase_id_post]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($purchase && $purchase['status'] === 'IN_PROGRESS') {
            try {
                $stmt = $db->prepare("DELETE FROM purchase_items WHERE id = ? AND purchase_id = ?");
                $stmt->execute([$item_id, $purchase_id_post]);
                
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(*) as total_items,
                        SUM(quantity) as total_quantity,
                        SUM(total_buying_cost) as total_buying_cost,
                        SUM(total_selling_value) as total_selling_value
                    FROM purchase_items 
                    WHERE purchase_id = ?
                ");
                $stmt->execute([$purchase_id_post]);
                $totals = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    UPDATE purchases 
                    SET total_items = ?, 
                        total_quantity = ?, 
                        total_buying_cost = ?, 
                        total_selling_value = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $totals['total_items'] ?? 0,
                    $totals['total_quantity'] ?? 0,
                    $totals['total_buying_cost'] ?? 0,
                    $totals['total_selling_value'] ?? 0,
                    $purchase_id_post
                ]);
                
                $message = "✅ Item removed successfully!";
                $message_type = 'success';
                $_SESSION['purchase_message'] = $message;
                $_SESSION['purchase_message_type'] = $message_type;
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=' . $purchase_type . '&branch=' . $selected_branch_id);
                exit;
                
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "❌ Cannot delete item from completed or cancelled purchase";
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

// ================================================================
// GET PURCHASE DATA FOR VIEWING
// ================================================================
$current_purchase = null;
$purchase_items = [];
$is_creator = false;
$can_edit = false;
$all_medicines = [];
$all_equipment = [];

if ($purchase_id > 0) {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as creator_name,
               a.full_name as cancelled_by_name
        FROM purchases p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN users a ON p.cancelled_by = a.id
        WHERE p.id = ?
    ");
    $stmt->execute([$purchase_id]);
    $current_purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_purchase) {
        $is_creator = ($current_purchase['created_by'] == $user_id);
        // Admin can edit any IN_PROGRESS purchase
        $can_edit = ($current_purchase['status'] === 'IN_PROGRESS');
        
        $stmt = $db->prepare("
            SELECT 
                pi.*,
                CASE 
                    WHEN pi.item_type = 'medicine' THEN mi.medication_name 
                    WHEN pi.item_type = 'equipment' THEN eq.equipment_name 
                END as item_name,
                CASE 
                    WHEN pi.item_type = 'medicine' THEN mi.category 
                    WHEN pi.item_type = 'equipment' THEN eq.category 
                END as category,
                CASE 
                    WHEN pi.item_type = 'medicine' THEN mi.unit 
                    WHEN pi.item_type = 'equipment' THEN eq.unit 
                END as unit,
                CASE 
                    WHEN pi.item_type = 'medicine' THEN mi.batch_number 
                    WHEN pi.item_type = 'equipment' THEN eq.batch_number 
                END as batch_number,
                u.full_name as added_by_full_name
            FROM purchase_items pi
            LEFT JOIN medications_inventory mi ON pi.item_id = mi.id AND pi.item_type = 'medicine'
            LEFT JOIN medical_equipment eq ON pi.item_id = eq.id AND pi.item_type = 'equipment'
            LEFT JOIN users u ON pi.added_by = u.id
            WHERE pi.purchase_id = ?
            ORDER BY pi.added_at DESC
        ");
        $stmt->execute([$purchase_id]);
        $purchase_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all medicines for auto-search
        $stmt = $db->prepare("
            SELECT id, medication_name, category, unit, selling_price, reorder_level, unit_cost, supplier
            FROM medications_inventory 
            WHERE branch_id = ? AND status = 'active'
            ORDER BY medication_name
        ");
        $stmt->execute([$user_branch_id]);
        $all_medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all equipment for auto-search
        $stmt = $db->prepare("
            SELECT id, equipment_name, category, unit, selling_price, reorder_level, unit_cost, supplier
            FROM medical_equipment 
            WHERE branch_id = ? AND status = 'active'
            ORDER BY equipment_name
        ");
        $stmt->execute([$user_branch_id]);
        $all_equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ================================================================
// HANDLE ACTION: LIST ALL IN_PROGRESS PURCHASES FOR JOINING
// ================================================================

// Get all IN_PROGRESS medicine purchases
$all_med_in_progress = [];
$stmt = $db->prepare("
    SELECT p.*, u.full_name as creator_name 
    FROM purchases p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.status = 'IN_PROGRESS' AND p.purchase_type = 'medicine'
    ORDER BY p.created_at DESC
");
$stmt->execute();
$all_med_in_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all IN_PROGRESS equipment purchases
$all_equip_in_progress = [];
$stmt = $db->prepare("
    SELECT p.*, u.full_name as creator_name 
    FROM purchases p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.status = 'IN_PROGRESS' AND p.purchase_type = 'equipment'
    ORDER BY p.created_at DESC
");
$stmt->execute();
$all_equip_in_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);

$has_med_in_progress = count($all_med_in_progress) > 0;
$has_equip_in_progress = count($all_equip_in_progress) > 0;

// ================================================================
// HANDLE ACTION: SHOW LIST OR CREATE
// ================================================================
$show_med_list = ($action === 'list_medicine' || ($action === 'create' && $purchase_type === 'medicine') || ($action === '' && !$purchase_id));
$show_equip_list = ($action === 'list_equipment' || ($action === 'create' && $purchase_type === 'equipment'));

// If action is 'create', check if there are IN_PROGRESS purchases first
if ($action === 'create') {
    if ($purchase_type === 'medicine' && $has_med_in_progress) {
        $show_med_list = true;
        $action = 'list_medicine';
    } elseif ($purchase_type === 'equipment' && $has_equip_in_progress) {
        $show_equip_list = true;
        $action = 'list_equipment';
    }
}

// ================================================================
// GET COMPLETED PURCHASES
// ================================================================
$completed_purchases = [];
$stmt = $db->prepare("
    SELECT p.*, u.full_name as creator_name 
    FROM purchases p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.status = 'COMPLETED'
    ORDER BY p.completed_at DESC
    LIMIT 50
");
$stmt->execute();
$completed_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Determine page title based on action
$page_title = 'Admin Purchases';
if ($action === 'list_medicine' || ($action === 'create' && $purchase_type === 'medicine')) {
    $page_title = 'Medicine Purchases - Join or Create';
} elseif ($action === 'list_equipment' || ($action === 'create' && $purchase_type === 'equipment')) {
    $page_title = 'Equipment Purchases - Join or Create';
} elseif ($purchase_id > 0 && $current_purchase) {
    $page_title = 'Purchase: ' . $current_purchase['invoice_number'];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= $logo_path ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================ */
        /* ROOT VARIABLES */
        /* ================================================================ */
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
            --teal: #0D9488;
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
        
        /* ================================================================ */
        /* SIDEBAR */
        /* ================================================================ */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: 270px; 
            background: linear-gradient(180deg, #0B4EA8 0%, #0A3D7A 100%);
            color: white; z-index: 50; overflow-y: auto; overflow-x: hidden;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(0);
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
            scroll-behavior: smooth;
        }
        
        [data-theme="dark"] .sidebar {
            background: linear-gradient(180deg, #0A3D7A 0%, #082F5E 100%);
        }
        
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        .sidebar::-webkit-scrollbar-thumb { background: #0AA84F; border-radius: 10px; }
        
        .sidebar-brand {
            padding: 18px 16px 14px;
            border-bottom: 2px solid rgba(255,255,255,0.08);
            background: rgba(0,0,0,0.1);
            position: sticky; top: 0; z-index: 5;
            backdrop-filter: blur(10px);
        }
        
        .sidebar-brand .logo {
            width: 42px; height: 42px; border-radius: 10px;
            object-fit: cover; background: white; padding: 4px;
            border: 2px solid rgba(255,255,255,0.15);
            transition: transform 0.3s ease;
        }
        
        .sidebar-brand .logo:hover { transform: rotate(-5deg) scale(1.05); }
        
        .sidebar-brand .brand-text { color: white; font-weight: 700; font-size: 0.95rem; line-height: 1.2; letter-spacing: 0.5px; }
        .sidebar-brand .brand-sub { color: #9EC5FE; font-size: 0.65rem; font-weight: 500; letter-spacing: 0.3px; }
        
        .sidebar-branch-selector {
            padding: 10px 14px;
            border-bottom: 2px solid rgba(255,255,255,0.06);
            background: rgba(0,0,0,0.05);
        }
        
        .sidebar-branch-selector select {
            width: 100%;
            padding: 7px 10px;
            border-radius: 8px;
            border: none;
            background: rgba(255,255,255,0.12);
            color: white;
            font-size: 0.75rem;
            cursor: pointer;
            outline: none;
            transition: all 0.3s ease;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='white' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }
        
        .sidebar-branch-selector select:hover {
            background-color: rgba(255,255,255,0.2);
        }
        
        .sidebar-branch-selector select:focus {
            box-shadow: 0 0 0 2px rgba(10, 168, 79, 0.5);
        }
        
        .sidebar-branch-selector select option {
            background: #0B4EA8;
            color: white;
            padding: 8px;
        }
        
        .sidebar-nav { padding: 10px 8px 20px; }
        
        .sidebar-nav .nav-label {
            font-size: 0.5rem; text-transform: uppercase;
            letter-spacing: 0.08em; color: #6EA8FE;
            padding: 8px 10px 4px; margin: 8px 0 2px;
            font-weight: 700; opacity: 0.8;
        }
        
        .sidebar-nav .nav-label .label-icon { margin-right: 4px; }
        
        .sidebar-link {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 12px; border-radius: 8px;
            color: #D2E3FC; text-decoration: none;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.8rem; font-weight: 500;
            margin: 1px 0; background: transparent;
            cursor: pointer; border: none; width: 100%;
            text-align: left; position: relative;
        }
        
        .sidebar-link:hover {
            background: rgba(10, 168, 79, 0.4);
            color: white; box-shadow: 0 4px 12px rgba(10, 168, 79, 0.2);
            transform: translateX(4px);
        }
        
        .sidebar-link.active {
            background: rgba(10, 168, 79, 0.5);
            color: white; box-shadow: 0 4px 12px rgba(10, 168, 79, 0.3);
        }
        
        .sidebar-link.active::before {
            content: ''; position: absolute; left: 0; top: 15%; bottom: 15%;
            width: 4px; background: #0AA84F;
            border-radius: 0 4px 4px 0;
            box-shadow: 0 0 12px rgba(10, 168, 79, 0.5);
        }
        
        .sidebar-link i { width: 20px; text-align: center; font-size: 0.9rem; flex-shrink: 0; opacity: 0.8; }
        .sidebar-link:hover i, .sidebar-link.active i { opacity: 1; }
        .sidebar-link .link-text { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .sidebar-link .badge {
            margin-left: auto; background: rgba(255,255,255,0.12);
            padding: 1px 8px; border-radius: 20px;
            font-size: 0.6rem; font-weight: 600; color: white;
            transition: all 0.3s ease; flex-shrink: 0; min-width: 20px;
            text-align: center; border: 1px solid rgba(255,255,255,0.05);
        }
        
        .sidebar-link .badge.danger { background: #EF4444; animation: pulse-badge 2s infinite; border-color: #EF4444; }
        .sidebar-link .badge.success { background: #059669; border-color: #059669; }
        
        .sidebar-link.logout-link {
            border-top: 2px solid rgba(255,255,255,0.06);
            padding-top: 10px; margin-top: 4px; color: #FCA5A5;
        }
        
        .sidebar-link.logout-link:hover { background: #DC2626; color: white; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4); transform: translateX(4px); }
        
        .sidebar-status {
            padding: 10px 16px; border-top: 2px solid rgba(255,255,255,0.06);
            display: flex; align-items: center; gap: 10px;
            background: rgba(0,0,0,0.1); position: sticky; bottom: 0;
            backdrop-filter: blur(10px);
        }
        
        .sidebar-status .status-dot {
            width: 8px; height: 8px; border-radius: 50%; display: inline-block;
            transition: all 0.3s ease;
        }
        
        .sidebar-status .status-dot.online {
            background: #34D399; box-shadow: 0 0 8px rgba(52, 211, 153, 0.3);
            animation: pulse-dot 1.5s infinite;
        }
        
        .sidebar-status .status-dot.offline {
            background: #94A3B8;
        }
        
        .sidebar-status .status-text { font-size: 0.65rem; color: #D2E3FC; font-weight: 500; }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.8); }
        }
        
        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }
        
        #sidebarOverlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 45;
            display: none; backdrop-filter: blur(4px);
        }
        
        #sidebarOverlay.active { display: block !important; }
        
        @media (min-width: 1025px) {
            .sidebar { transform: translateX(0) !important; z-index: 50; }
            #sidebarOverlay { display: none !important; }
        }
        
        @media (max-width: 1024px) {
            .sidebar { width: 280px; transform: translateX(-100%); z-index: 9999; border-radius: 0 12px 12px 0; }
            .sidebar.open { transform: translateX(0) !important; }
            #sidebarOverlay { display: none; z-index: 9998; }
            #sidebarOverlay.active { display: block !important; }
        }
        
        /* ================================================================ */
        /* HEADER & MAIN CONTENT */
        /* ================================================================ */
        .embedded-header {
            position: fixed; top: 0; left: 270px; right: 0;
            height: 68px; background: var(--bg-nav); z-index: 40;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease; backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .embedded-header .search-wrapper {
            display: flex; align-items: center; background: var(--bg-body);
            border-radius: var(--radius); border: 2px solid var(--border-color);
            transition: all 0.3s; flex: 1; max-width: 500px;
        }
        
        .embedded-header .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
        }
        
        .embedded-header .search-wrapper input {
            border: none; background: transparent; padding: 8px 14px;
            width: 100%; font-size: 0.85rem; outline: none;
            color: var(--text-primary);
        }
        
        .embedded-header .search-wrapper input::placeholder { color: var(--text-secondary); }
        
        .embedded-header .search-wrapper .search-btn {
            background: var(--primary); color: white; border: none;
            padding: 8px 16px; border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer; font-size: 0.85rem; transition: all 0.3s;
            white-space: nowrap;
        }
        
        .embedded-header .search-wrapper .search-btn:hover {
            background: var(--primary-dark); transform: scale(1.02);
        }
        
        .embedded-header .datetime { font-size: 0.78rem; color: var(--text-secondary); font-weight: 500; display: flex; align-items: center; gap: 6px; }
        .embedded-header .datetime i { color: var(--success); }
        
        .embedded-header .avatar {
            width: 40px; height: 40px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--border-color);
            cursor: pointer; transition: all 0.3s;
        }
        
        .embedded-header .avatar:hover { border-color: var(--primary); transform: scale(1.05); }
        
        .embedded-header .icon-btn {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary); transition: all 0.3s;
            background: transparent; border: none; cursor: pointer;
            position: relative;
        }
        
        .embedded-header .icon-btn:hover { background: var(--bg-body); color: var(--primary); }
        
        .embedded-header .notif-dot {
            position: absolute; top: 6px; right: 6px;
            width: 8px; height: 8px; border-radius: 50%;
            border: 2px solid var(--bg-nav); animation: pulse-dot 2s infinite;
        }
        
        .embedded-header .notif-dot.has-notif { background: var(--danger); }
        .embedded-header .notif-dot.no-notif { background: var(--text-muted); animation: none; }
        
        .embedded-header .dark-toggle-btn {
            background: var(--bg-body); border: 2px solid var(--border-color);
            border-radius: var(--radius); padding: 6px 12px;
            cursor: pointer; font-size: 0.82rem; color: var(--text-primary);
            transition: all 0.3s; display: flex; align-items: center; gap: 6px;
        }
        
        .embedded-header .dark-toggle-btn:hover { border-color: var(--primary); background: var(--bg-card); }
        .embedded-header .dark-toggle-btn i { font-size: 0.9rem; }
        
        .embedded-header .branch-selector {
            background: var(--bg-body); border: 2px solid var(--border-color);
            border-radius: var(--radius); padding: 6px 12px;
            font-size: 0.78rem; color: var(--text-primary);
            outline: none; cursor: pointer; transition: all 0.3s;
        }
        
        .embedded-header .branch-selector:focus { border-color: var(--primary); }
        
        .main-content {
            margin-left: 270px; margin-top: 68px;
            padding: 28px 32px; min-height: calc(100vh - 68px);
        }
        
        /* ================================================================ */
        /* PAGE HEADER BOX */
        /* ================================================================ */
        .page-header-box {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 18px 24px;
            margin-bottom: 20px;
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.2);
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
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
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header-box .page-title .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header-box .page-title .branch-name-display {
            background: rgba(255,255,255,0.15);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            color: white;
        }
        
        .page-header-box .page-title .btn-back-green {
            background: var(--success); color: white; border: none;
            padding: 5px 16px; border-radius: 20px; font-size: 0.7rem;
            font-weight: 600; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
            margin-left: 4px;
        }
        
        .page-header-box .page-title .btn-back-green:hover {
            background: var(--success-dark); transform: translateY(-2px) scale(1.03);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.5);
        }
        
        .page-header-box .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 2px;
        }
        
        .page-header-box .page-subtitle strong { color: white; font-weight: 600; }
        
        .page-header-box .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header-box .header-badge i { opacity: 0.8; }
        .page-header-box .header-badge.medicines {
            background: rgba(52, 211, 153, 0.2);
            border-color: rgba(52, 211, 153, 0.3);
            color: #6EE7B7;
        }
        .page-header-box .header-badge.equipment {
            background: rgba(124, 58, 237, 0.2);
            border-color: rgba(124, 58, 237, 0.3);
            color: #C4B5FD;
        }
        
        .header-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .btn-back {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 6px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.75rem;
            border: 1px solid rgba(255,255,255,0.15);
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .btn-add-purchase {
            background: var(--success);
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }
        
        .btn-add-purchase:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35);
        }
        
        .btn-join {
            background: var(--primary);
            color: white;
            padding: 4px 14px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-join:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-complete {
            background: var(--success);
            color: white;
            padding: 8px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-complete:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-complete:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-cancel-purchase {
            background: var(--danger);
            color: white;
            padding: 8px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-cancel-purchase:hover {
            background: #991B1B;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        .btn-cancel-purchase:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-delete-item {
            background: var(--danger);
            color: white;
            border: none;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.6rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-delete-item:hover {
            background: #991B1B;
            transform: scale(1.05);
        }
        
        .btn-print {
            background: var(--danger);
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-print:hover {
            background: #991B1B;
            transform: translateY(-2px);
        }
        
        .btn-save {
            background: var(--success);
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
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
        
        .message-box {
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            font-size: 0.9rem;
            animation: slideDown 0.5s ease;
            border-left: 5px solid transparent;
        }
        
        .message-box.success {
            background: #D1FAE5;
            color: #065F46;
            border-color: #059669;
            border-left: 5px solid #059669;
        }
        
        .message-box.success i { color: #059669; font-size: 1.2rem; }
        
        .message-box.error {
            background: #FEE2E2;
            color: #991B1B;
            border-color: #DC2626;
            border-left: 5px solid #DC2626;
        }
        
        .message-box.error i { color: #DC2626; font-size: 1.2rem; }
        
        .message-box.info {
            background: var(--primary-light);
            color: #0A4CA8;
            border-color: #0B5ED7;
            border-left: 5px solid #0B5ED7;
        }
        
        .message-box.info i { color: #0B5ED7; font-size: 1.2rem; }
        
        .message-box .message-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            color: inherit;
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }
        
        .message-box .message-close:hover { opacity: 1; }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-blue { color: var(--primary); }
        .card-title .title-green { color: var(--success); }
        .card-title .title-purple { color: var(--purple); }
        
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        
        .purchase-card {
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 14px 16px;
            transition: all 0.3s ease;
        }
        
        .purchase-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .purchase-card .invoice-number {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .purchase-card .status-badge {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .purchase-card .status-badge.in-progress {
            background: var(--warning-light);
            color: var(--warning);
        }
        
        .purchase-card .status-badge.completed {
            background: var(--success-light);
            color: var(--success);
        }
        
        .purchase-card .status-badge.cancelled {
            background: var(--danger-light);
            color: var(--danger);
        }
        
        .purchase-card .meta-text {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .purchase-details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }
        
        .purchase-details-header .invoice-number {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .purchase-details-header .purchase-status {
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .purchase-details-header .purchase-status.in-progress {
            background: var(--warning-light);
            color: var(--warning);
        }
        
        .purchase-details-header .purchase-status.completed {
            background: var(--success-light);
            color: var(--success);
        }
        
        .purchase-details-header .purchase-status.cancelled {
            background: var(--danger-light);
            color: var(--danger);
        }
        
        .purchase-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }
        
        .purchase-info-item {
            padding: 8px 12px;
            background: var(--bg-body);
            border-radius: 6px;
        }
        
        .purchase-info-item .label {
            font-size: 0.55rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        
        .purchase-info-item .value {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 2px;
        }
        
        .purchase-info-item .profit-positive {
            color: var(--success);
        }
        
        .purchase-info-item .profit-negative {
            color: var(--danger);
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
            height: 42px;
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
            box-shadow: var(--shadow-lg);
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
        
        .autocomplete-item.active {
            background: var(--primary);
            color: white;
        }
        
        .autocomplete-item .item-detail {
            font-size: 0.65rem;
            color: var(--text-muted);
            display: block;
        }
        
        .table-wrapper { overflow-x: auto; }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.78rem;
        }
        
        .data-table thead th {
            background: var(--primary);
            color: white;
            padding: 6px 10px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            white-space: nowrap;
            text-align: left;
        }
        
        .data-table thead th:first-child { border-radius: 6px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 6px 0 0; }
        
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
            padding: 6px 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .col-sno { width: 30px; text-align: center; }
        .col-item { min-width: 160px; }
        .col-category { min-width: 90px; }
        .col-quantity { min-width: 60px; text-align: center; }
        .col-buying-price { min-width: 90px; }
        .col-buying-total { min-width: 100px; }
        .col-selling-price { min-width: 90px; }
        .col-selling-total { min-width: 100px; }
        .col-added-by { min-width: 100px; }
        .col-actions { min-width: 70px; text-align: center; }
        
        .added-by-tag {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 600;
            background: var(--purple-light);
            color: var(--purple);
        }
        
        [data-theme="dark"] .added-by-tag {
            background: #2D1B4E;
            color: #C4B5FD;
        }
        
        .grand-total {
            font-size: 1.2rem;
            font-weight: 700;
            text-align: right;
            padding: 10px 0;
            border-top: 2px solid var(--border-color);
        }
        
        .grand-total .label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .grand-total .amount {
            font-size: 1.4rem;
            color: var(--success);
        }
        
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 2rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 8px;
        }
        
        .footer {
            padding: 10px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 16px;
            text-align: center;
            font-size: 0.6rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ================================================================ */
        /* CANCEL MODAL STYLES */
        /* ================================================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: 12px;
            max-width: 550px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px 28px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 2px solid var(--border-color);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 16px;
        }
        
        .modal-header .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--danger);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        
        .modal-close:hover {
            color: var(--danger);
            transform: rotate(90deg);
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            padding-top: 14px;
            border-top: 2px solid var(--border-color);
            margin-top: 16px;
            flex-wrap: wrap;
        }
        
        .modal-actions .btn-confirm-cancel {
            background: var(--danger);
            color: white;
            padding: 10px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
        }
        
        .modal-actions .btn-confirm-cancel:hover {
            background: #991B1B;
            transform: translateY(-2px);
        }
        
        .modal-actions .btn-close-modal {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .modal-actions .btn-close-modal:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .cancel-reason-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            resize: vertical;
            min-height: 80px;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        
        .cancel-reason-textarea:focus {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
            outline: none;
        }
        
        .cancel-warning-icon {
            text-align: center;
            font-size: 3rem;
            color: var(--danger);
            margin-bottom: 10px;
        }
        
        /* ================================================================ */
        /* PDF MODAL STYLES */
        /* ================================================================ */
        .modal-overlay-pdf {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .modal-overlay-pdf.show {
            display: flex;
        }
        
        .modal-content-pdf {
            background: white;
            border-radius: 12px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header-pdf {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 2px solid #E2E8F0;
            margin-bottom: 16px;
        }
        
        .modal-header-pdf .modal-title-pdf {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0B5ED7;
        }
        
        .modal-close-pdf {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748B;
            transition: all 0.3s ease;
        }
        
        .modal-close-pdf:hover {
            color: #DC2626;
            transform: rotate(90deg);
        }
        
        .modal-actions-pdf {
            display: flex;
            gap: 10px;
            padding-top: 12px;
            border-top: 2px solid #E2E8F0;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        
        .btn-print-invoice {
            background: #0B5ED7;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-print-invoice:hover {
            background: #0A4CA8;
            transform: translateY(-2px);
        }
        
        .btn-close-modal-pdf {
            background: transparent;
            color: #64748B;
            border: 2px solid #E2E8F0;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-close-modal-pdf:hover {
            border-color: #DC2626;
            color: #DC2626;
        }
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 14px; }
            .embedded-header { left: 0; }
            .embedded-header .search-wrapper { max-width: 300px; }
            .grid-3 { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 768px) {
            .grid-3 { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .form-grid .full-width { grid-column: 1; }
            .page-header-box .page-title { font-size: 1.1rem; }
            .page-header-box { flex-direction: column; align-items: stretch !important; }
            .purchase-details-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; }
            .header-actions .btn-add-purchase { width: 100%; justify-content: center; }
            .embedded-header .datetime { display: none; }
            .category-input-group { flex-direction: column; }
            .batch-input-group { flex-direction: column; }
            .batch-input-group .btn-generate { width: 100%; justify-content: center; }
            .form-actions { flex-direction: column; }
            .form-actions .btn-save,
            .form-actions .btn-cancel { width: 100%; justify-content: center; }
            .purchase-info-grid { grid-template-columns: 1fr 1fr; }
            .modal-content { padding: 16px; }
        }
        
        @media (max-width: 480px) {
            .data-table { font-size: 0.65rem; }
            .data-table th, .data-table td { padding: 3px 5px; }
            .col-item { min-width: 100px; }
            .col-buying-price, .col-selling-price { min-width: 70px; }
            .col-buying-total, .col-selling-total { min-width: 80px; }
            .purchase-info-grid { grid-template-columns: 1fr; }
        }
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
    
    <div class="sidebar-branch-selector">
        <select id="sidebarBranchSelector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-label"><span class="label-icon">📋</span> Main Menu</div>
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
        
        <div class="nav-label"><span class="label-icon">⚙️</span> Modules</div>
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
        
        <div class="nav-label"><span class="label-icon">💼</span> Services</div>
        <a href="services.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-concierge-bell"></i> <span class="link-text">Services</span>
            <span class="badge"><?= $total_services_sidebar ?></span>
            <?php if ($today_services_sidebar > 0): ?>
                <span class="badge success">+<?= $today_services_sidebar ?></span>
            <?php endif; ?>
        </a>
        
        <div class="nav-label"><span class="label-icon">🏢</span> Management</div>
        <a href="branches.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-store-alt"></i> <span class="link-text">Branches</span>
            <span class="badge"><?= $total_branches_sidebar ?></span>
        </a>
        <a href="departments.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-building"></i> <span class="link-text">Departments</span>
        </a>
        <a href="reports.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-chart-bar"></i> <span class="link-text">Reports</span>
        </a>
        
        <div class="nav-label"><span class="label-icon">🔧</span> System</div>
        <a href="settings.php?branch=<?= $selected_branch_id ?>" class="sidebar-link">
            <i class="fas fa-cog"></i> <span class="link-text">Settings</span>
        </a>
        
        <div class="nav-label"><span class="label-icon">👤</span> Account</div>
        <a href="profile.php" class="sidebar-link">
            <i class="fas fa-user-circle"></i> <span class="link-text">Profile</span>
        </a>
        <a href="/dispensary_system/frontend/pages/logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span>
        </a>
    </nav>
    
    <div class="sidebar-status">
        <span class="status-dot <?= $user_is_online ? 'online' : 'offline' ?>"></span>
        <span class="status-text"><?= $user_is_online ? 'Online' : 'Offline' ?></span>
    </div>
</aside>

<!-- ================================================================ -->
<!-- HEADER -->
<!-- ================================================================ -->
<header class="embedded-header">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="icon-btn lg:hidden">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search purchases...">
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
    <div class="page-header-box">
        <div>
            <h1 class="page-title">
                <?php if ($action === 'list_medicine' || ($action === 'create' && $purchase_type === 'medicine')): ?>
                    <i class="fas fa-pills"></i> Medicine Purchases
                <?php elseif ($action === 'list_equipment' || ($action === 'create' && $purchase_type === 'equipment')): ?>
                    <i class="fas fa-tools"></i> Equipment Purchases
                <?php elseif ($purchase_id > 0 && $current_purchase): ?>
                    <i class="fas fa-shopping-cart"></i> Purchase: <?= htmlspecialchars($current_purchase['invoice_number']) ?>
                <?php else: ?>
                    <i class="fas fa-shopping-cart"></i> Admin Purchases
                <?php endif; ?>
                <span class="role-badge-display">ADMIN</span>
                <span class="branch-name-display">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($display_branch_name) ?>
                </span>
                <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn-back-green">
                    <i class="fas fa-arrow-left"></i> Back to Inventory
                </a>
            </h1>
            <p class="page-subtitle">
                <?php if ($purchase_id > 0 && $current_purchase): ?>
                    <strong><?= $current_purchase['total_items'] ?></strong> items · 
                    <strong><?= number_format($current_purchase['total_quantity']) ?></strong> units
                <?php else: ?>
                    <strong><?= count($all_med_in_progress) + count($all_equip_in_progress) ?></strong> IN_PROGRESS purchases
                <?php endif; ?>
                <?php if ($action === 'list_medicine' || $purchase_type === 'medicine'): ?>
                    <span class="header-badge medicines">
                        <i class="fas fa-pills"></i> <?= count($all_med_in_progress) ?> Medicine
                    </span>
                <?php endif; ?>
                <?php if ($action === 'list_equipment' || $purchase_type === 'equipment'): ?>
                    <span class="header-badge equipment">
                        <i class="fas fa-tools"></i> <?= count($all_equip_in_progress) ?> Equipment
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="header-actions">
            <?php if ($action === 'list_medicine' || ($action === 'create' && $purchase_type === 'medicine')): ?>
                <?php if (count($all_med_in_progress) == 0): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="create_purchase">
                        <input type="hidden" name="purchase_type" value="medicine">
                        <button type="submit" class="btn-add-purchase" style="padding:8px 20px;font-size:0.8rem;">
                            <i class="fas fa-plus-circle"></i> Create New Purchase
                        </button>
                    </form>
                <?php endif; ?>
            <?php elseif ($action === 'list_equipment' || ($action === 'create' && $purchase_type === 'equipment')): ?>
                <?php if (count($all_equip_in_progress) == 0): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="create_purchase">
                        <input type="hidden" name="purchase_type" value="equipment">
                        <button type="submit" class="btn-add-purchase" style="padding:8px 20px;font-size:0.8rem;background:var(--purple);">
                            <i class="fas fa-plus-circle"></i> Create New Purchase
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>" id="messageBox">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'info' ? 'fa-info-circle' : 'fa-exclamation-circle') ?>"></i>
            <span><?= $message ?></span>
            <button class="message-close" onclick="closeMessage()">&times;</button>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- SHOW LIST OF IN_PROGRESS PURCHASES FOR JOINING -->
    <!-- ================================================================ -->
    <?php if (($action === 'list_medicine' || ($action === 'create' && $purchase_type === 'medicine')) && !$purchase_id): ?>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-spinner title-blue fa-spin"></i> 
                    Medicine Purchases In Progress
                    <span class="result-count">(<strong><?= count($all_med_in_progress) ?></strong> available)</span>
                </h3>
            </div>
            
            <?php if (count($all_med_in_progress) > 0): ?>
                <div class="grid-3">
                    <?php foreach ($all_med_in_progress as $purchase): ?>
                        <div class="purchase-card" style="border-color:var(--warning);">
                            <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:4px;">
                                <div>
                                    <div class="invoice-number" style="color:var(--warning);">
                                        <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($purchase['invoice_number']) ?>
                                        <span style="font-size:0.55rem;font-weight:400;color:var(--text-secondary);">
                                            (Medicine)
                                        </span>
                                    </div>
                                    <div class="meta-text">
                                        <i class="fas fa-user"></i> Created by: <?= htmlspecialchars($purchase['creator_name'] ?? 'Unknown') ?>
                                        <?php if ($purchase['created_by'] == $user_id): ?>
                                            <span style="color:var(--success);font-weight:600;"> (You)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="meta-text">
                                        <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?>
                                    </div>
                                </div>
                                <span class="status-badge in-progress">
                                    <i class="fas fa-spinner fa-spin"></i> IN PROGRESS
                                </span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;flex-wrap:wrap;gap:4px;">
                                <div class="meta-text">
                                    <i class="fas fa-boxes"></i> <?= number_format($purchase['total_items']) ?> items
                                    <span style="margin-left:6px;">
                                        <i class="fas fa-cubes"></i> <?= number_format($purchase['total_quantity']) ?> units
                                    </span>
                                    <span style="margin-left:6px;font-weight:600;color:var(--success);">
                                        TSh <?= number_format($purchase['total_selling_value'] ?? 0) ?>
                                    </span>
                                </div>
                                <div style="display:flex;gap:4px;">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="join_purchase">
                                        <input type="hidden" name="purchase_id" value="<?= $purchase['id'] ?>">
                                        <input type="hidden" name="purchase_type" value="<?= $purchase['purchase_type'] ?>">
                                        <button type="submit" class="btn-join">
                                            <i class="fas fa-sign-in-alt"></i> Join
                                        </button>
                                    </form>
                                    <?php if ($purchase['created_by'] == $user_id): ?>
                                        <a href="purchases.php?id=<?= $purchase['id'] ?>&type=<?= $purchase['purchase_type'] ?>&branch=<?= $selected_branch_id ?>" class="btn-join" style="background:var(--success);">
                                            <i class="fas fa-arrow-right"></i> Continue
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No medicine purchases in progress</p>
                    <p style="font-size:0.8rem;margin-top:4px;">
                        Click "Create New Purchase" to start one.
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- SHOW LIST OF EQUIPMENT IN_PROGRESS PURCHASES -->
    <!-- ================================================================ -->
    <?php if (($action === 'list_equipment' || ($action === 'create' && $purchase_type === 'equipment')) && !$purchase_id): ?>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-spinner title-purple fa-spin"></i> 
                    Equipment Purchases In Progress
                    <span class="result-count">(<strong><?= count($all_equip_in_progress) ?></strong> available)</span>
                </h3>
            </div>
            
            <?php if (count($all_equip_in_progress) > 0): ?>
                <div class="grid-3">
                    <?php foreach ($all_equip_in_progress as $purchase): ?>
                        <div class="purchase-card" style="border-color:var(--warning);">
                            <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:4px;">
                                <div>
                                    <div class="invoice-number" style="color:var(--warning);">
                                        <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($purchase['invoice_number']) ?>
                                        <span style="font-size:0.55rem;font-weight:400;color:var(--text-secondary);">
                                            (Equipment)
                                        </span>
                                    </div>
                                    <div class="meta-text">
                                        <i class="fas fa-user"></i> Created by: <?= htmlspecialchars($purchase['creator_name'] ?? 'Unknown') ?>
                                        <?php if ($purchase['created_by'] == $user_id): ?>
                                            <span style="color:var(--success);font-weight:600;"> (You)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="meta-text">
                                        <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?>
                                    </div>
                                </div>
                                <span class="status-badge in-progress">
                                    <i class="fas fa-spinner fa-spin"></i> IN PROGRESS
                                </span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;flex-wrap:wrap;gap:4px;">
                                <div class="meta-text">
                                    <i class="fas fa-boxes"></i> <?= number_format($purchase['total_items']) ?> items
                                    <span style="margin-left:6px;">
                                        <i class="fas fa-cubes"></i> <?= number_format($purchase['total_quantity']) ?> units
                                    </span>
                                    <span style="margin-left:6px;font-weight:600;color:var(--success);">
                                        TSh <?= number_format($purchase['total_selling_value'] ?? 0) ?>
                                    </span>
                                </div>
                                <div style="display:flex;gap:4px;">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="join_purchase">
                                        <input type="hidden" name="purchase_id" value="<?= $purchase['id'] ?>">
                                        <input type="hidden" name="purchase_type" value="<?= $purchase['purchase_type'] ?>">
                                        <button type="submit" class="btn-join">
                                            <i class="fas fa-sign-in-alt"></i> Join
                                        </button>
                                    </form>
                                    <?php if ($purchase['created_by'] == $user_id): ?>
                                        <a href="purchases.php?id=<?= $purchase['id'] ?>&type=<?= $purchase['purchase_type'] ?>&branch=<?= $selected_branch_id ?>" class="btn-join" style="background:var(--success);">
                                            <i class="fas fa-arrow-right"></i> Continue
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No equipment purchases in progress</p>
                    <p style="font-size:0.8rem;margin-top:4px;">
                        Click "Create New Purchase" to start one.
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- IF VIEWING A SPECIFIC PURCHASE -->
    <!-- ================================================================ -->
    <?php if ($purchase_id && $current_purchase): ?>
        
        <!-- PURCHASE DETAILS -->
        <div class="card">
            <div class="purchase-details-header">
                <div>
                    <div class="invoice-number">
                        <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($current_purchase['invoice_number']) ?>
                        <span style="font-size:0.65rem;font-weight:400;color:var(--text-secondary);margin-left:8px;">
                            <?= ucfirst($current_purchase['purchase_type']) ?>
                        </span>
                    </div>
                    <div style="font-size:0.7rem;color:var(--text-secondary);margin-top:2px;">
                        Created: <?= date('d/m/Y H:i', strtotime($current_purchase['created_at'])) ?>
                        <?php if ($current_purchase['completed_at']): ?>
                            | Completed: <?= date('d/m/Y H:i', strtotime($current_purchase['completed_at'])) ?>
                        <?php endif; ?>
                        <?php if ($current_purchase['status'] === 'CANCELLED' && $current_purchase['cancelled_reason']): ?>
                            | <span style="color:var(--danger);">Cancelled: <?= htmlspecialchars($current_purchase['cancelled_reason']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span class="purchase-status <?= strtolower($current_purchase['status']) ?>">
                        <i class="fas <?= $current_purchase['status'] === 'IN_PROGRESS' ? 'fa-spinner fa-spin' : ($current_purchase['status'] === 'COMPLETED' ? 'fa-check-circle' : 'fa-times-circle') ?>"></i>
                        <?= $current_purchase['status'] ?>
                    </span>
                    <?php if ($is_creator && $current_purchase['status'] === 'IN_PROGRESS'): ?>
                        <span style="font-size:0.6rem;color:var(--success);background:var(--success-light);padding:2px 10px;border-radius:12px;">
                            <i class="fas fa-crown"></i> Creator
                        </span>
                    <?php endif; ?>
                    <?php if ($current_purchase['status'] === 'CANCELLED'): ?>
                        <span style="font-size:0.6rem;color:var(--danger);background:var(--danger-light);padding:2px 10px;border-radius:12px;">
                            <i class="fas fa-user"></i> Cancelled by: <?= htmlspecialchars($current_purchase['cancelled_by_name'] ?? 'Admin') ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($current_purchase['status'] === 'COMPLETED'): ?>
                        <button onclick="openPDFView(<?= $purchase_id ?>)" class="btn-print">
                            <i class="fas fa-file-pdf"></i> PDF Invoice
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Purchase Info Grid -->
            <?php 
                $profit = ($current_purchase['total_selling_value'] ?? 0) - ($current_purchase['total_buying_cost'] ?? 0);
                $profit_class = $profit >= 0 ? 'profit-positive' : 'profit-negative';
            ?>
            <div class="purchase-info-grid">
                <div class="purchase-info-item">
                    <div class="label">Created By</div>
                    <div class="value">
                        <i class="fas fa-user" style="color:var(--primary);"></i>
                        <?= htmlspecialchars($current_purchase['creator_name'] ?? 'Unknown') ?>
                    </div>
                </div>
                <div class="purchase-info-item">
                    <div class="label">Total Items</div>
                    <div class="value">
                        <i class="fas fa-boxes" style="color:var(--warning);"></i>
                        <?= number_format($current_purchase['total_items']) ?> items
                    </div>
                </div>
                <div class="purchase-info-item">
                    <div class="label">Total Quantity</div>
                    <div class="value">
                        <i class="fas fa-cubes" style="color:var(--teal);"></i>
                        <?= number_format($current_purchase['total_quantity']) ?> units
                    </div>
                </div>
                <div class="purchase-info-item" style="background:var(--danger-light);border:2px solid var(--danger);">
                    <div class="label" style="color:var(--danger);">💰 Total Buying Cost</div>
                    <div class="value" style="color:var(--danger);font-size:1rem;">
                        <i class="fas fa-shopping-cart"></i>
                        TSh <?= number_format($current_purchase['total_buying_cost'] ?? 0) ?>
                    </div>
                </div>
                <div class="purchase-info-item" style="background:var(--success-light);border:2px solid var(--success);">
                    <div class="label" style="color:var(--success);">💰 Total Selling Value</div>
                    <div class="value" style="color:var(--success);font-size:1rem;">
                        <i class="fas fa-coins"></i>
                        TSh <?= number_format($current_purchase['total_selling_value'] ?? 0) ?>
                    </div>
                </div>
                <div class="purchase-info-item" style="background:var(<?= $profit >= 0 ? '--success-light' : '--danger-light' ?>);border:2px solid var(<?= $profit >= 0 ? '--success' : '--danger' ?>);">
                    <div class="label" style="color:var(<?= $profit >= 0 ? '--success' : '--danger' ?>);">📈 Expected Profit</div>
                    <div class="value <?= $profit_class ?>" style="font-size:1rem;">
                        <i class="fas fa-chart-line"></i>
                        TSh <?= number_format($profit) ?>
                        <?php if ($current_purchase['total_buying_cost'] > 0): ?>
                            <span style="font-size:0.6rem;">
                                (<?= round(($profit / $current_purchase['total_buying_cost']) * 100, 1) ?>% margin)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- ADD MEDICINE FORM -->
            <?php if ($current_purchase['purchase_type'] === 'medicine' && $current_purchase['status'] === 'IN_PROGRESS'): ?>
                <div class="card" style="margin-top:12px;border-color:var(--success);">
                    <div class="card-header">
                        <h4 class="card-title" style="font-size:0.85rem;">
                            <i class="fas fa-plus-circle title-green"></i> 
                            Add Medicine Batch to Purchase
                            <span style="font-size:0.65rem;font-weight:400;color:var(--text-secondary);">
                                (Auto-search or type new medicine)
                            </span>
                        </h4>
                    </div>
                    
                    <form method="POST" id="addMedicineForm">
                        <input type="hidden" name="action" value="add_medicine">
                        <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
                        
                        <div class="form-grid">
                            <!-- Medicine Name -->
                            <div class="full-width form-row">
                                <label class="form-label">Medicine Name <span class="required">*</span></label>
                                <div class="autocomplete-container">
                                    <input type="text" name="medication_name" id="purchaseMedicineName" class="form-control" 
                                           placeholder="Type medicine name to search or type new medicine..." required autocomplete="off">
                                    <input type="hidden" name="medicine_id" id="purchaseMedicineId" value="">
                                    <div class="autocomplete-list" id="purchaseMedicineAutocomplete"></div>
                                </div>
                                <div class="help-text">Type to search existing medicine. You can also type a new medicine name.</div>
                            </div>
                            
                            <!-- Category -->
                            <div class="form-row">
                                <label class="form-label">Category <span class="required">*</span></label>
                                <div class="category-input-group">
                                    <select name="category" id="purchaseCategorySelect" class="form-control" required>
                                        <option value="">Select</option>
                                        <?php foreach ($predefined_med_categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                        <?php endforeach; ?>
                                        <option value="__other__">+ Other</option>
                                    </select>
                                    <input type="text" name="category_manual" id="purchaseCategoryManual" class="form-control" placeholder="Custom category..." style="display:none;">
                                    <button type="button" class="btn-toggle" onclick="toggleCategory('med')">
                                        <i class="fas fa-edit"></i> Manual
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Unit -->
                            <div class="form-row">
                                <label class="form-label">Unit</label>
                                <select name="unit" id="purchaseUnit" class="form-control">
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
                            
                            <!-- Quantity -->
                            <div class="form-row">
                                <label class="form-label">Quantity <span class="required">*</span></label>
                                <input type="number" name="quantity" id="purchaseQuantity" class="form-control" placeholder="0" min="1" required>
                                <div class="help-text">Number of units being purchased</div>
                            </div>
                            
                            <!-- Reorder Level -->
                            <div class="form-row">
                                <label class="form-label">Reorder Level</label>
                                <input type="number" name="reorder_level" id="purchaseReorderLevel" class="form-control" placeholder="10" min="0" value="10">
                                <div class="help-text">Alert when stock reaches this level</div>
                            </div>
                            
                            <!-- Buying Price -->
                            <div class="form-row">
                                <label class="form-label">Buying Price (TSh) <span class="required">*</span></label>
                                <input type="text" name="buying_price" id="purchaseBuyingPrice" class="form-control money-input" placeholder="0" value="0" required>
                                <div class="help-text" style="color:var(--danger);">Price you paid per unit</div>
                            </div>
                            
                            <!-- Selling Price -->
                            <div class="form-row">
                                <label class="form-label">Selling Price (TSh) <span class="required">*</span></label>
                                <input type="text" name="selling_price" id="purchaseUnitPrice" class="form-control money-input" placeholder="0" value="0" required>
                                <div class="help-text" style="color:var(--success);">Price you will sell per unit | 0 = Free</div>
                            </div>
                            
                            <!-- Supplier -->
                            <div class="form-row">
                                <label class="form-label">Supplier</label>
                                <input type="text" name="supplier" id="purchaseSupplier" class="form-control" placeholder="Supplier name">
                            </div>
                            
                            <!-- Expiry Date -->
                            <div class="form-row">
                                <label class="form-label">Expiry Date <span class="required">*</span></label>
                                <input type="date" name="expiry_date" id="purchaseExpiryDate" class="form-control" required>
                                <div class="help-text">Expiry date is required for medicines</div>
                            </div>
                            
                            <!-- Batch Number -->
                            <div class="full-width form-row">
                                <label class="form-label">Batch Number</label>
                                <div class="batch-input-group">
                                    <input type="text" name="batch_number" id="purchaseBatchInput" class="form-control" 
                                           placeholder="BATCH-YYYYMMDD-XXXX" 
                                           value="<?= 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)) ?>">
                                    <button type="button" class="btn-generate" onclick="generateBatch('med')">
                                        <i class="fas fa-sync-alt"></i> Generate
                                    </button>
                                </div>
                                <div class="help-text">Auto-generated. Click "Generate" for a new batch number.</div>
                            </div>
                            
                            <!-- Status -->
                            <div class="form-row">
                                <label class="form-label">Status</label>
                                <select name="status" id="purchaseStatus" class="form-control">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-save">
                                <i class="fas fa-plus-circle"></i> Add Batch to Purchase
                            </button>
                            <button type="reset" class="btn-cancel">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </form>
                    
                    <div style="font-size:0.6rem;color:var(--text-muted);margin-top:6px;">
                        <i class="fas fa-info-circle"></i> 
                        Items are saved to purchase only. Inventory will be updated when you <strong>Complete Purchase</strong>.
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- ADD EQUIPMENT FORM -->
            <?php if ($current_purchase['purchase_type'] === 'equipment' && $current_purchase['status'] === 'IN_PROGRESS'): ?>
                <div class="card" style="margin-top:12px;border-color:var(--purple);">
                    <div class="card-header">
                        <h4 class="card-title" style="font-size:0.85rem;">
                            <i class="fas fa-plus-circle" style="color:var(--purple);"></i> 
                            Add Equipment Batch to Purchase
                            <span style="font-size:0.65rem;font-weight:400;color:var(--text-secondary);">
                                (Auto-search or type new equipment)
                            </span>
                        </h4>
                    </div>
                    
                    <form method="POST" id="addEquipmentForm">
                        <input type="hidden" name="action" value="add_equipment">
                        <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
                        
                        <div class="form-grid">
                            <!-- Equipment Name -->
                            <div class="full-width form-row">
                                <label class="form-label">Equipment Name <span class="required">*</span></label>
                                <div class="autocomplete-container">
                                    <input type="text" name="equipment_name" id="purchaseEquipmentName" class="form-control" 
                                           placeholder="Type equipment name to search or type new equipment..." required autocomplete="off">
                                    <input type="hidden" name="equipment_id" id="purchaseEquipmentId" value="">
                                    <div class="autocomplete-list" id="purchaseEquipmentAutocomplete"></div>
                                </div>
                                <div class="help-text">Type to search existing equipment. You can also type a new equipment name.</div>
                            </div>
                            
                            <!-- Category -->
                            <div class="form-row">
                                <label class="form-label">Category <span class="required">*</span></label>
                                <div class="category-input-group">
                                    <select name="category" id="purchaseEquipCategorySelect" class="form-control" required>
                                        <option value="">Select</option>
                                        <?php foreach ($predefined_equip_categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                        <?php endforeach; ?>
                                        <option value="__other__">+ Other</option>
                                    </select>
                                    <input type="text" name="category_manual" id="purchaseEquipCategoryManual" class="form-control" placeholder="Custom category..." style="display:none;">
                                    <button type="button" class="btn-toggle" onclick="toggleCategory('equip')">
                                        <i class="fas fa-edit"></i> Manual
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Unit -->
                            <div class="form-row">
                                <label class="form-label">Unit</label>
                                <select name="unit" id="purchaseEquipUnit" class="form-control">
                                    <option value="pcs">Pieces (pcs)</option>
                                    <option value="set">Set</option>
                                    <option value="box">Box</option>
                                    <option value="pack">Pack</option>
                                    <option value="each">Each</option>
                                </select>
                            </div>
                            
                            <!-- Quantity -->
                            <div class="form-row">
                                <label class="form-label">Quantity <span class="required">*</span></label>
                                <input type="number" name="quantity" id="purchaseEquipQuantity" class="form-control" placeholder="0" min="1" required>
                                <div class="help-text">Number of units being purchased</div>
                            </div>
                            
                            <!-- Reorder Level -->
                            <div class="form-row">
                                <label class="form-label">Reorder Level</label>
                                <input type="number" name="reorder_level" id="purchaseEquipReorderLevel" class="form-control" placeholder="5" min="0" value="5">
                                <div class="help-text">Alert when stock reaches this level</div>
                            </div>
                            
                            <!-- Buying Price -->
                            <div class="form-row">
                                <label class="form-label">Buying Price (TSh) <span class="required">*</span></label>
                                <input type="text" name="buying_price" id="purchaseEquipBuyingPrice" class="form-control money-input" placeholder="0" value="0" required>
                                <div class="help-text" style="color:var(--danger);">Price you paid per unit</div>
                            </div>
                            
                            <!-- Selling Price -->
                            <div class="form-row">
                                <label class="form-label">Selling Price (TSh) <span class="required">*</span></label>
                                <input type="text" name="selling_price" id="purchaseEquipSellingPrice" class="form-control money-input" placeholder="0" value="0" required>
                                <div class="help-text" style="color:var(--success);">Price you will sell per unit | 0 = Free</div>
                            </div>
                            
                            <!-- Supplier -->
                            <div class="form-row">
                                <label class="form-label">Supplier</label>
                                <input type="text" name="supplier" id="purchaseEquipSupplier" class="form-control" placeholder="Supplier name">
                            </div>
                            
                            <!-- Expiry Date - OPTIONAL -->
                            <div class="form-row">
                                <label class="form-label">Expiry Date <span style="font-size:0.55rem;color:var(--text-muted);">(Optional)</span></label>
                                <input type="date" name="expiry_date" id="purchaseEquipExpiryDate" class="form-control">
                                <div class="help-text">Leave empty = No expiry (Active Forever)</div>
                            </div>
                            
                            <!-- Batch Number -->
                            <div class="full-width form-row">
                                <label class="form-label">Batch Number</label>
                                <div class="batch-input-group">
                                    <input type="text" name="batch_number" id="purchaseEquipBatchInput" class="form-control" 
                                           placeholder="EQP-YYYYMMDD-XXXX" 
                                           value="<?= 'EQP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)) ?>">
                                    <button type="button" class="btn-generate" onclick="generateBatch('equip')">
                                        <i class="fas fa-sync-alt"></i> Generate
                                    </button>
                                </div>
                                <div class="help-text">Auto-generated. Click "Generate" for a new batch number.</div>
                            </div>
                            
                            <!-- Status -->
                            <div class="form-row">
                                <label class="form-label">Status</label>
                                <select name="status" id="purchaseEquipStatus" class="form-control">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-save" style="background:var(--purple);">
                                <i class="fas fa-plus-circle"></i> Add Batch to Purchase
                            </button>
                            <button type="reset" class="btn-cancel">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </form>
                    
                    <div style="font-size:0.6rem;color:var(--text-muted);margin-top:6px;">
                        <i class="fas fa-info-circle"></i> 
                        Items are saved to purchase only. Inventory will be updated when you <strong>Complete Purchase</strong>.
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- ITEMS TABLE -->
            <div style="margin-top:16px;">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="fas fa-list title-blue"></i> Items 
                        <span class="result-count">(<strong><?= count($purchase_items) ?></strong> items)</span>
                    </h4>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <?php if ($current_purchase['status'] === 'IN_PROGRESS'): ?>
                            <!-- Complete Purchase - Admin can complete any purchase -->
                            <form method="POST" style="display:inline;" 
                                  onsubmit="return confirm('Are you sure you want to complete this purchase as Admin?\nThis will update inventory and cannot be undone.');">
                                <input type="hidden" name="action" value="complete_purchase">
                                <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
                                <button type="submit" class="btn-complete" <?= count($purchase_items) == 0 ? 'disabled' : '' ?>>
                                    <i class="fas fa-check-circle"></i> Complete Purchase
                                </button>
                            </form>
                            
                            <!-- Cancel Purchase - Admin can cancel any purchase -->
                            <button type="button" class="btn-cancel-purchase" onclick="openCancelModal(<?= $purchase_id ?>, '<?= htmlspecialchars($current_purchase['invoice_number']) ?>')">
                                <i class="fas fa-times-circle"></i> Cancel Purchase
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (count($purchase_items) > 0): ?>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="col-sno">#</th>
                                    <th class="col-item"><?= ucfirst($current_purchase['purchase_type']) ?></th>
                                    <th class="col-category">Category</th>
                                    <th class="col-quantity">Qty</th>
                                    <th class="col-buying-price">Buy Price</th>
                                    <th class="col-buying-total">Buy Total</th>
                                    <th class="col-selling-price">Sell Price</th>
                                    <th class="col-selling-total">Sell Total</th>
                                    <th class="col-added-by">Added By</th>
                                    <?php if ($current_purchase['status'] === 'IN_PROGRESS'): ?>
                                        <th class="col-actions">Action</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $counter = 1; ?>
                                <?php foreach ($purchase_items as $item): ?>
                                    <tr>
                                        <td class="col-sno"><?= $counter++ ?></td>
                                        <td class="col-item">
                                            <strong><?= htmlspecialchars($item['item_name'] ?? 'Unknown') ?></strong>
                                            <div style="font-size:0.6rem;color:var(--text-secondary);">
                                                <?= htmlspecialchars($item['unit'] ?? 'pcs') ?> | 
                                                Batch: <?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?>
                                            </div>
                                        </td>
                                        <td class="col-category"><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                        <td class="col-quantity"><?= number_format($item['quantity']) ?></td>
                                        <td class="col-buying-price" style="color:var(--danger);font-weight:600;">
                                            TSh <?= number_format($item['buying_price'] ?? 0) ?>
                                        </td>
                                        <td class="col-buying-total" style="color:var(--danger);font-weight:600;">
                                            TSh <?= number_format($item['total_buying_cost'] ?? 0) ?>
                                        </td>
                                        <td class="col-selling-price" style="color:var(--success);font-weight:600;">
                                            TSh <?= number_format($item['selling_price'] ?? 0) ?>
                                        </td>
                                        <td class="col-selling-total" style="color:var(--success);font-weight:600;">
                                            TSh <?= number_format($item['total_selling_value'] ?? 0) ?>
                                        </td>
                                        <td class="col-added-by">
                                            <span class="added-by-tag">
                                                <i class="fas fa-user-circle"></i>
                                                <?= htmlspecialchars($item['added_by_full_name'] ?? 'Unknown') ?>
                                            </span>
                                        </td>
                                        <?php if ($current_purchase['status'] === 'IN_PROGRESS'): ?>
                                            <td class="col-actions">
                                                <form method="POST" style="display:inline;" 
                                                      onsubmit="return confirm('Remove this item from purchase?');">
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
                                                    <button type="submit" class="btn-delete-item" title="Remove item">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="9">
                                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;padding:10px 0;border-top:2px solid var(--border-color);">
                                            <div style="background:var(--danger-light);padding:8px 12px;border-radius:8px;border:2px solid var(--danger);">
                                                <div style="font-size:0.6rem;color:var(--danger);font-weight:600;">💰 Total Buying Cost</div>
                                                <div style="font-size:1.1rem;font-weight:700;color:var(--danger);">
                                                    TSh <?= number_format($current_purchase['total_buying_cost'] ?? 0) ?>
                                                </div>
                                            </div>
                                            <div style="background:var(--success-light);padding:8px 12px;border-radius:8px;border:2px solid var(--success);">
                                                <div style="font-size:0.6rem;color:var(--success);font-weight:600;">💰 Total Selling Value</div>
                                                <div style="font-size:1.1rem;font-weight:700;color:var(--success);">
                                                    TSh <?= number_format($current_purchase['total_selling_value'] ?? 0) ?>
                                                </div>
                                            </div>
                                            <?php 
                                                $total_profit = ($current_purchase['total_selling_value'] ?? 0) - ($current_purchase['total_buying_cost'] ?? 0);
                                            ?>
                                            <div style="background:var(<?= $total_profit >= 0 ? '--success-light' : '--danger-light' ?>);padding:8px 12px;border-radius:8px;border:2px solid var(<?= $total_profit >= 0 ? '--success' : '--danger' ?>);">
                                                <div style="font-size:0.6rem;color:var(<?= $total_profit >= 0 ? '--success' : '--danger' ?>);font-weight:600;">📈 Expected Profit</div>
                                                <div style="font-size:1.1rem;font-weight:700;color:var(<?= $total_profit >= 0 ? '--success' : '--danger' ?>);">
                                                    TSh <?= number_format($total_profit) ?>
                                                    <?php if ($current_purchase['total_buying_cost'] > 0): ?>
                                                        <span style="font-size:0.6rem;">
                                                            (<?= round(($total_profit / $current_purchase['total_buying_cost']) * 100, 1) ?>% margin)
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <?php if ($current_purchase['status'] === 'IN_PROGRESS'): ?>
                                        <td colspan="1"></td>
                                    <?php endif; ?>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <p>No items added yet</p>
                        <p style="font-size:0.8rem;margin-top:4px;">
                            <?php if ($current_purchase['status'] === 'IN_PROGRESS'): ?>
                                Use the form above to add <?= $current_purchase['purchase_type'] === 'medicine' ? 'medicines' : 'equipment' ?> to this purchase.
                            <?php else: ?>
                                This purchase has been <?= strtolower($current_purchase['status']) ?>.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- CANCEL PURCHASE MODAL -->
    <!-- ================================================================ -->
    <div class="modal-overlay" id="cancelModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Cancel Purchase
                </div>
                <button class="modal-close" onclick="closeCancelModal()">&times;</button>
            </div>
            
            <div class="cancel-warning-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            
            <p style="text-align:center;color:var(--text-primary);font-weight:500;margin-bottom:8px;">
                Are you sure you want to cancel this purchase?
            </p>
            <p style="text-align:center;color:var(--text-secondary);font-size:0.85rem;margin-bottom:16px;">
                <strong id="cancelInvoiceNumber"></strong>
            </p>
            
            <form method="POST" id="cancelForm">
                <input type="hidden" name="action" value="cancel_purchase">
                <input type="hidden" name="purchase_id" id="cancelPurchaseId" value="">
                
                <div style="margin-bottom:8px;">
                    <label class="form-label">Reason for Cancellation <span class="required">*</span></label>
                    <textarea name="cancel_reason" id="cancelReason" class="cancel-reason-textarea" 
                              placeholder="Explain why this purchase is being cancelled..." required></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn-confirm-cancel">
                        <i class="fas fa-times-circle"></i> Yes, Cancel Purchase
                    </button>
                    <button type="button" class="btn-close-modal" onclick="closeCancelModal()">
                        <i class="fas fa-times"></i> No, Go Back
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PDF VIEW MODAL -->
    <!-- ================================================================ -->
    <div class="modal-overlay-pdf" id="pdfModal">
        <div class="modal-content-pdf">
            <div class="modal-header-pdf">
                <div class="modal-title-pdf">
                    <i class="fas fa-file-pdf" style="color:#DC2626;"></i> Purchase Invoice
                </div>
                <button class="modal-close-pdf" onclick="closePDF()">&times;</button>
            </div>
            
            <div id="pdfInvoiceContent" style="background:white;padding:10px;max-height:70vh;overflow-y:auto;">
                <div style="text-align:center;padding:30px;color:#64748B;">
                    <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i>
                    <p>Loading invoice...</p>
                </div>
            </div>
            
            <div class="modal-actions-pdf">
                <button class="btn-print-invoice" onclick="printPDFInvoice()">
                    <i class="fas fa-print"></i> Print Invoice
                </button>
                <button class="btn-close-modal-pdf" onclick="closePDF()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-400 mx-2">|</span>
            Admin Purchases
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
// CLOSE MESSAGE
// ================================================================
function closeMessage() {
    var messageBox = document.getElementById('messageBox');
    if (messageBox) {
        messageBox.style.display = 'none';
    }
}

setTimeout(function() {
    var messageBox = document.getElementById('messageBox');
    if (messageBox) {
        messageBox.style.display = 'none';
    }
}, 5000);

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
        window.location.href = 'purchases.php?search=' + encodeURIComponent(query) + '&branch=' + branch;
    }
}

searchBtn?.addEventListener('click', performSearch);
searchInput?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') performSearch();
});

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
    var inputId = type === 'med' ? 'purchaseBatchInput' : 'purchaseEquipBatchInput';
    var input = document.getElementById(inputId);
    if (input) input.value = batch;
}

// ================================================================
// CATEGORY TOGGLE
// ================================================================
function toggleCategory(type) {
    var select, manual;
    if (type === 'med') {
        select = document.getElementById('purchaseCategorySelect');
        manual = document.getElementById('purchaseCategoryManual');
    } else if (type === 'equip') {
        select = document.getElementById('purchaseEquipCategorySelect');
        manual = document.getElementById('purchaseEquipCategoryManual');
    } else {
        return;
    }
    if (!select || !manual) return;
    if (manual.style.display === 'none') {
        manual.style.display = 'block';
        select.style.display = 'none';
        manual.focus();
        manual.required = true;
        select.required = false;
    } else {
        manual.style.display = 'none';
        select.style.display = 'block';
        select.value = '';
        manual.required = false;
        select.required = true;
    }
}

// ================================================================
// CANCEL MODAL FUNCTIONS
// ================================================================
function openCancelModal(purchaseId, invoiceNumber) {
    var modal = document.getElementById('cancelModal');
    if (!modal) return;
    
    document.getElementById('cancelPurchaseId').value = purchaseId;
    document.getElementById('cancelInvoiceNumber').textContent = 'Invoice: ' + invoiceNumber;
    document.getElementById('cancelReason').value = '';
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeCancelModal() {
    var modal = document.getElementById('cancelModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
}

// Close cancel modal on outside click
document.getElementById('cancelModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeCancelModal();
    }
});

// Close cancel modal on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCancelModal();
        closePDF();
    }
});

// ================================================================
// OPEN PDF VIEW
// ================================================================
function openPDFView(purchaseId) {
    var modal = document.getElementById('pdfModal');
    var content = document.getElementById('pdfInvoiceContent');
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    content.innerHTML = `
        <div style="text-align:center;padding:30px;color:#64748B;">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i>
            <p>Loading invoice...</p>
        </div>
    `;
    
    fetch('get_invoice.php?id=' + purchaseId)
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(function(html) {
            content.innerHTML = html;
        })
        .catch(function(error) {
            content.innerHTML = `
                <div style="text-align:center;padding:30px;color:#DC2626;">
                    <i class="fas fa-exclamation-circle" style="font-size:2rem;"></i>
                    <p>Error loading invoice: ${error.message}</p>
                    <button class="btn-close-modal-pdf" onclick="closePDF()" style="margin-top:10px;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
        });
}

// ================================================================
// CLOSE PDF MODAL
// ================================================================
function closePDF() {
    var modal = document.getElementById('pdfModal');
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
}

// ================================================================
// PRINT PDF INVOICE
// ================================================================
function printPDFInvoice() {
    var content = document.getElementById('pdfInvoiceContent');
    if (!content) return;
    
    var printContents = content.innerHTML;
    var originalTitle = document.title;
    document.title = 'Invoice - Braick Dispensary';
    
    var win = window.open('', '_blank', 'width=900,height=700');
    if (!win) {
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = '<style>body{background:white;padding:20px;}</style>' + printContents;
        window.print();
        document.body.innerHTML = originalContents;
        document.title = originalTitle;
        return;
    }
    
    win.document.write('<!DOCTYPE html><html><head><title>Invoice - Braick Dispensary</title>');
    win.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">');
    win.document.write('<style>');
    win.document.write(`
        body { font-family: Arial, sans-serif; background: white; padding: 20px; margin: 0; }
        .invoice-container { max-width: 800px; margin: 0 auto; }
        .invoice-header { text-align: center; border-bottom: 3px solid #0B5ED7; padding-bottom: 15px; margin-bottom: 20px; }
        .invoice-header .logo { max-height: 70px; width: auto; display: block; margin: 0 auto 10px; }
        .invoice-header h1 { font-size: 24px; color: #0B5ED7; margin: 0; }
        .invoice-header p { font-size: 12px; color: #64748B; margin: 2px 0; }
        .invoice-title { text-align: center; margin-bottom: 20px; }
        .invoice-title h2 { font-size: 20px; color: #0B5ED7; margin: 0; }
        .invoice-title p { font-size: 12px; color: #64748B; margin: 2px 0; }
        .invoice-info { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 12px; background: #F8FAFC; border-radius: 8px; border: 1px solid #E2E8F0; margin-bottom: 20px; }
        .invoice-info .label { font-size: 11px; color: #64748B; margin: 0; font-weight: 600; }
        .invoice-info .value { font-size: 14px; font-weight: 600; margin: 2px 0; }
        .invoice-info .text-right { text-align: right; }
        .invoice-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px; }
        .invoice-table thead th { background: #0B5ED7; color: white; padding: 8px 10px; text-align: left; border: 1px solid #0B5ED7; }
        .invoice-table thead th.text-center { text-align: center; }
        .invoice-table thead th.text-right { text-align: right; }
        .invoice-table tbody td { padding: 6px 10px; border: 1px solid #E2E8F0; }
        .invoice-table tbody td.text-center { text-align: center; }
        .invoice-table tbody td.text-right { text-align: right; }
        .invoice-table tbody td .batch-info { font-size: 11px; color: #64748B; }
        .invoice-table tbody tr:nth-child(even) { background: #F8FAFC; }
        .invoice-table tfoot td { padding: 8px 10px; border-top: 2px solid #0B5ED7; font-weight: 700; }
        .invoice-summary { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; padding: 12px; background: #F8FAFC; border-radius: 8px; border: 1px solid #E2E8F0; margin-bottom: 20px; }
        .invoice-summary .label { font-size: 11px; color: #64748B; margin: 0; font-weight: 600; }
        .invoice-summary .value { font-size: 18px; font-weight: 700; margin: 2px 0; }
        .invoice-summary .text-right { text-align: right; }
        .invoice-added-by { font-size: 11px; color: #64748B; border-top: 1px solid #E2E8F0; padding-top: 10px; margin-top: 10px; }
        .invoice-footer { text-align: center; border-top: 2px solid #0B5ED7; padding-top: 12px; margin-top: 15px; }
        .invoice-footer p { font-size: 11px; color: #64748B; margin: 0; }
        .invoice-footer .thank-you { font-size: 10px; color: #94A3B8; margin: 2px 0; }
        @media print { body { padding: 10px; } }
        @media (max-width: 600px) { .invoice-info { grid-template-columns: 1fr; } .invoice-summary { grid-template-columns: 1fr; } }
    `);
    win.document.write('</style>');
    win.document.write('</head><body>');
    win.document.write('<div class="invoice-container">');
    win.document.write(printContents);
    win.document.write('</div>');
    win.document.write('</body></html>');
    win.document.close();
    
    setTimeout(function() {
        win.focus();
        win.print();
        win.close();
    }, 500);
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
// AUTO-SEARCH - Medicine
// ================================================================
(function() {
    var medicineData = <?= json_encode($all_medicines) ?>;
    var input = document.getElementById('purchaseMedicineName');
    var autocomplete = document.getElementById('purchaseMedicineAutocomplete');
    var medicineIdInput = document.getElementById('purchaseMedicineId');
    
    var categorySelect = document.getElementById('purchaseCategorySelect');
    var categoryManual = document.getElementById('purchaseCategoryManual');
    var unitSelect = document.getElementById('purchaseUnit');
    var buyingPriceInput = document.getElementById('purchaseBuyingPrice');
    var sellingPriceInput = document.getElementById('purchaseUnitPrice');
    var reorderInput = document.getElementById('purchaseReorderLevel');
    var supplierInput = document.getElementById('purchaseSupplier');
    var quantityInput = document.getElementById('purchaseQuantity');
    var expiryInput = document.getElementById('purchaseExpiryDate');
    
    if (!input || !autocomplete) return;
    
    function autoFillMedicine(medicineId) {
        var selected = medicineData.find(function(m) { return m.id == medicineId; });
        if (selected) {
            if (medicineIdInput) medicineIdInput.value = selected.id;
            
            if (categorySelect && selected.category) {
                var catFound = false;
                for (var i = 0; i < categorySelect.options.length; i++) {
                    if (categorySelect.options[i].value === selected.category) {
                        categorySelect.value = selected.category;
                        catFound = true;
                        break;
                    }
                }
                if (!catFound && selected.category) {
                    categoryManual.style.display = 'block';
                    categorySelect.style.display = 'none';
                    categoryManual.value = selected.category;
                    categoryManual.required = true;
                    categorySelect.required = false;
                } else {
                    categoryManual.style.display = 'none';
                    categorySelect.style.display = 'block';
                    categorySelect.value = selected.category || '';
                    categoryManual.required = false;
                    categorySelect.required = true;
                }
            }
            
            if (unitSelect && selected.unit) {
                for (var j = 0; j < unitSelect.options.length; j++) {
                    if (unitSelect.options[j].value === selected.unit) {
                        unitSelect.value = selected.unit;
                        break;
                    }
                }
            }
            
            if (buyingPriceInput) {
                buyingPriceInput.value = Number(selected.unit_cost || 0).toLocaleString();
            }
            
            if (sellingPriceInput) {
                sellingPriceInput.value = Number(selected.selling_price || 0).toLocaleString();
            }
            
            if (reorderInput && selected.reorder_level > 0) {
                reorderInput.value = selected.reorder_level;
            }
            
            if (supplierInput && selected.supplier) {
                supplierInput.value = selected.supplier;
            }
            
            if (quantityInput && selected.reorder_level > 0) {
                quantityInput.value = selected.reorder_level;
            }
        }
    }
    
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
                <div class="autocomplete-item" data-id="${item.id}" data-name="${escapeHtml(item.medication_name)}">
                    <strong>${escapeHtml(item.medication_name)}</strong>
                    <span class="item-detail">
                        Category: ${escapeHtml(item.category || 'N/A')} | 
                        Buy: TSh ${Number(item.unit_cost || 0).toLocaleString()} | 
                        Sell: TSh ${Number(item.selling_price || 0).toLocaleString()}
                    </span>
                </div>
            `;
        });
        
        autocomplete.innerHTML = html;
        autocomplete.classList.add('show');
        
        autocomplete.querySelectorAll('.autocomplete-item').forEach(function(item) {
            item.addEventListener('click', function() {
                var id = parseInt(this.dataset.id);
                var name = this.dataset.name;
                input.value = name;
                if (medicineIdInput) medicineIdInput.value = id;
                autocomplete.classList.remove('show');
                autoFillMedicine(id);
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
                var id = parseInt(selectedItem.dataset.id);
                var name = selectedItem.dataset.name;
                input.value = name;
                if (medicineIdInput) medicineIdInput.value = id;
                autocomplete.classList.remove('show');
                selectedIndex = -1;
                autoFillMedicine(id);
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

// ================================================================
// AUTO-SEARCH - Equipment
// ================================================================
(function() {
    var equipmentData = <?= json_encode($all_equipment) ?>;
    var input = document.getElementById('purchaseEquipmentName');
    var autocomplete = document.getElementById('purchaseEquipmentAutocomplete');
    var equipmentIdInput = document.getElementById('purchaseEquipmentId');
    
    var categorySelect = document.getElementById('purchaseEquipCategorySelect');
    var categoryManual = document.getElementById('purchaseEquipCategoryManual');
    var unitSelect = document.getElementById('purchaseEquipUnit');
    var buyingPriceInput = document.getElementById('purchaseEquipBuyingPrice');
    var sellingPriceInput = document.getElementById('purchaseEquipSellingPrice');
    var reorderInput = document.getElementById('purchaseEquipReorderLevel');
    var supplierInput = document.getElementById('purchaseEquipSupplier');
    var quantityInput = document.getElementById('purchaseEquipQuantity');
    
    if (!input || !autocomplete) return;
    
    function autoFillEquipment(equipmentId) {
        var selected = equipmentData.find(function(m) { return m.id == equipmentId; });
        if (selected) {
            if (equipmentIdInput) equipmentIdInput.value = selected.id;
            
            if (categorySelect && selected.category) {
                var catFound = false;
                for (var i = 0; i < categorySelect.options.length; i++) {
                    if (categorySelect.options[i].value === selected.category) {
                        categorySelect.value = selected.category;
                        catFound = true;
                        break;
                    }
                }
                if (!catFound && selected.category) {
                    categoryManual.style.display = 'block';
                    categorySelect.style.display = 'none';
                    categoryManual.value = selected.category;
                    categoryManual.required = true;
                    categorySelect.required = false;
                } else {
                    categoryManual.style.display = 'none';
                    categorySelect.style.display = 'block';
                    categorySelect.value = selected.category || '';
                    categoryManual.required = false;
                    categorySelect.required = true;
                }
            }
            
            if (unitSelect && selected.unit) {
                for (var j = 0; j < unitSelect.options.length; j++) {
                    if (unitSelect.options[j].value === selected.unit) {
                        unitSelect.value = selected.unit;
                        break;
                    }
                }
            }
            
            if (buyingPriceInput) {
                buyingPriceInput.value = Number(selected.unit_cost || 0).toLocaleString();
            }
            
            if (sellingPriceInput) {
                sellingPriceInput.value = Number(selected.selling_price || 0).toLocaleString();
            }
            
            if (reorderInput && selected.reorder_level > 0) {
                reorderInput.value = selected.reorder_level;
            }
            
            if (supplierInput && selected.supplier) {
                supplierInput.value = selected.supplier;
            }
            
            if (quantityInput && selected.reorder_level > 0) {
                quantityInput.value = selected.reorder_level;
            }
        }
    }
    
    input.addEventListener('input', function() {
        var query = this.value.toLowerCase().trim();
        
        if (query.length < 1) {
            autocomplete.classList.remove('show');
            return;
        }
        
        var matches = equipmentData.filter(function(item) {
            return item.equipment_name.toLowerCase().includes(query);
        });
        
        if (matches.length === 0) {
            autocomplete.classList.remove('show');
            return;
        }
        
        var html = '';
        matches.forEach(function(item) {
            html += `
                <div class="autocomplete-item" data-id="${item.id}" data-name="${escapeHtml(item.equipment_name)}">
                    <strong>${escapeHtml(item.equipment_name)}</strong>
                    <span class="item-detail">
                        Category: ${escapeHtml(item.category || 'N/A')} | 
                        Buy: TSh ${Number(item.unit_cost || 0).toLocaleString()} | 
                        Sell: TSh ${Number(item.selling_price || 0).toLocaleString()}
                    </span>
                </div>
            `;
        });
        
        autocomplete.innerHTML = html;
        autocomplete.classList.add('show');
        
        autocomplete.querySelectorAll('.autocomplete-item').forEach(function(item) {
            item.addEventListener('click', function() {
                var id = parseInt(this.dataset.id);
                var name = this.dataset.name;
                input.value = name;
                if (equipmentIdInput) equipmentIdInput.value = id;
                autocomplete.classList.remove('show');
                autoFillEquipment(id);
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
                var id = parseInt(selectedItem.dataset.id);
                var name = selectedItem.dataset.name;
                input.value = name;
                if (equipmentIdInput) equipmentIdInput.value = id;
                autocomplete.classList.remove('show');
                selectedIndex = -1;
                autoFillEquipment(id);
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
// CONSOLE LOG
// ================================================================
console.log('%c💊 Braick - Admin Purchases', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Medicine Form: Auto-search, expiry required', 'font-size:13px; color:#34D399;');
console.log('%c✅ Equipment Form: Auto-search, expiry optional', 'font-size:13px; color:#7C3AED;');
console.log('%c✅ Items saved to purchase_items only', 'font-size:13px; color:#34D399;');
console.log('%c✅ Inventory updated ONLY on Complete Purchase', 'font-size:13px; color:#34D399;');
console.log('%c✅ PDF Invoice: Click PDF Invoice button to view', 'font-size:13px; color:#DC2626;');
console.log('%c✅ ADMIN CAN COMPLETE ANY PURCHASE (not just creator)', 'font-size:13px; color:#EF4444; font-weight:bold;');
console.log('%c✅ ADMIN CAN CANCEL ANY PURCHASE with reason', 'font-size:13px; color:#EF4444; font-weight:bold;');
console.log('%c✅ FIXED: Category saves correctly (not __other__)', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ FIXED: Quantity ADDS to existing inventory', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ FIXED: Status set to ACTIVE on complete', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Medicine IN_PROGRESS count: <?= count($all_med_in_progress) ?>', 'font-size:13px; color:#D97706;');
console.log('%c✅ Equipment IN_PROGRESS count: <?= count($all_equip_in_progress) ?>', 'font-size:13px; color:#D97706;');
</script>

</body>
</html>