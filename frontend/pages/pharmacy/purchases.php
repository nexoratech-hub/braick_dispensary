<?php
// ================================================================
// FILE: frontend/pages/pharmacy/purchases.php
// PHARMACY - PURCHASE MANAGEMENT WITH PDF INVOICE
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
    header('Location: ../dashboard.php');
    exit;
}

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacist';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

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
} catch (Exception $e) {}

// Check if added_by exists in medical_equipment
try {
    $stmt = $db->query("SHOW COLUMNS FROM medical_equipment LIKE 'added_by'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `medical_equipment` ADD COLUMN `added_by` INT NULL AFTER `status`");
        $db->exec("ALTER TABLE `medical_equipment` ADD COLUMN `added_by_name` VARCHAR(100) NULL AFTER `added_by`");
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
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // JOIN PURCHASE
    // ================================================================
    if ($action === 'join_purchase') {
        $purchase_id_join = (int)($_POST['purchase_id'] ?? 0);
        
        if ($purchase_id_join > 0) {
            $stmt = $db->prepare("SELECT id, status, invoice_number, purchase_type FROM purchases WHERE id = ? AND status = 'IN_PROGRESS'");
            $stmt->execute([$purchase_id_join]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($purchase) {
                header('Location: purchases.php?id=' . $purchase_id_join . '&type=' . $purchase['purchase_type']);
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
    if ($action === 'create_purchase') {
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
            header('Location: purchases.php?id=' . $new_purchase_id . '&type=' . $purchase_type_new);
            exit;
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // ADD MEDICINE TO PURCHASE
    // ================================================================
    if ($action === 'add_medicine') {
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);
        $medicine_id = (int)($_POST['medicine_id'] ?? 0);
        $medication_name = trim($_POST['medication_name'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $buying_price = (float)str_replace(',', '', $_POST['buying_price'] ?? 0);
        $selling_price = (float)str_replace(',', '', $_POST['selling_price'] ?? 0);
        $expiry_date = $_POST['expiry_date'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $category = trim($_POST['category'] ?? '');
        if (empty($category) && !empty($_POST['category_manual'])) {
            $category = trim($_POST['category_manual']);
        }
        $unit = trim($_POST['unit'] ?? 'pcs');
        $reorder_level = (int)($_POST['reorder_level'] ?? 10);
        $supplier = trim($_POST['supplier'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
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
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=' . $purchase_type);
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
    // ADD EQUIPMENT TO PURCHASE
    // ================================================================
    if ($action === 'add_equipment') {
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);
        $equipment_id = (int)($_POST['equipment_id'] ?? 0);
        $equipment_name = trim($_POST['equipment_name'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $buying_price = (float)str_replace(',', '', $_POST['buying_price'] ?? 0);
        $selling_price = (float)str_replace(',', '', $_POST['selling_price'] ?? 0);
        $expiry_date = $_POST['expiry_date'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $category = trim($_POST['category'] ?? '');
        if (empty($category) && !empty($_POST['category_manual'])) {
            $category = trim($_POST['category_manual']);
        }
        $unit = trim($_POST['unit'] ?? 'pcs');
        $reorder_level = (int)($_POST['reorder_level'] ?? 5);
        $supplier = trim($_POST['supplier'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
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
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=' . $purchase_type);
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
    // COMPLETE PURCHASE - UPDATE INVENTORY
    // ================================================================
    if ($action === 'complete_purchase') {
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
        } elseif ($purchase['created_by'] != $user_id) {
            $errors[] = 'Only the creator of this purchase can complete it';
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
                            SELECT id, quantity FROM medications_inventory 
                            WHERE id = ?
                            LIMIT 1
                        ");
                        $stmt->execute([$item['item_id']]);
                        $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($inventory) {
                            $new_qty = $inventory['quantity'] + $item['quantity'];
                            $stmt = $db->prepare("
                                UPDATE medications_inventory 
                                SET quantity = ?, 
                                    unit_cost = ?,
                                    selling_price = ?,
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
                            SELECT id, quantity FROM medical_equipment 
                            WHERE id = ?
                            LIMIT 1
                        ");
                        $stmt->execute([$item['item_id']]);
                        $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($inventory) {
                            $new_qty = $inventory['quantity'] + $item['quantity'];
                            $stmt = $db->prepare("
                                UPDATE medical_equipment 
                                SET quantity = ?, 
                                    unit_cost = ?,
                                    selling_price = ?,
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
                header('Location: purchases.php?action=view&id=' . $purchase_id_post);
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
    if ($action === 'delete_item') {
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
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=' . $purchase_type);
                exit;
                
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "❌ Cannot delete item from completed purchase";
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
        SELECT p.*, u.full_name as creator_name 
        FROM purchases p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$purchase_id]);
    $current_purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_purchase) {
        $is_creator = ($current_purchase['created_by'] == $user_id);
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
// GET ALL IN PROGRESS PURCHASES
// ================================================================
$in_progress_purchases = [];
$stmt = $db->prepare("
    SELECT p.*, u.full_name as creator_name 
    FROM purchases p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.status = 'IN_PROGRESS'
    ORDER BY p.created_at DESC
");
$stmt->execute();
$in_progress_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Pharmacy Purchases - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
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
            --teal: #0D9488;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
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
        
        .header-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            position: relative;
            z-index: 1;
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
        
        /* ================================================================
           PDF MODAL STYLES
           ================================================================ */
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
            background: white;
            border-radius: 12px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 2px solid #E2E8F0;
            margin-bottom: 16px;
        }
        
        .modal-header .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0B5ED7;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748B;
            transition: all 0.3s ease;
        }
        
        .modal-close:hover {
            color: #DC2626;
            transform: rotate(90deg);
        }
        
        .modal-actions {
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
        
        .btn-close-modal {
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
        
        .btn-close-modal:hover {
            border-color: #DC2626;
            color: #DC2626;
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 14px; }
            .grid-3 { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 768px) {
            .grid-3 { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .form-grid .full-width { grid-column: 1; }
            .page-header-box .page-title { font-size: 1.1rem; }
            .purchase-details-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; }
            .header-actions .btn-add-purchase { width: 100%; justify-content: center; }
            .category-input-group { flex-direction: column; }
            .batch-input-group { flex-direction: column; }
            .batch-input-group .btn-generate { width: 100%; justify-content: center; }
            .form-actions { flex-direction: column; }
            .form-actions .btn-save,
            .form-actions .btn-cancel { width: 100%; justify-content: center; }
            .purchase-info-grid { grid-template-columns: 1fr 1fr; }
            .modal-content { padding: 12px; }
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
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-box">
        <div>
            <h1 class="page-title">
                <i class="fas fa-shopping-cart"></i>
                Pharmacy Purchases
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <span class="branch-name-display">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </h1>
        </div>
        <div class="header-actions">
            <?php if (!$purchase_id || ($current_purchase && $current_purchase['status'] === 'COMPLETED')): ?>
                <a href="inventory.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Inventory
                </a>
            <?php endif; ?>
            <?php if ($purchase_id && $current_purchase): ?>
                <a href="purchases.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
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
    <!-- IF VIEWING A SPECIFIC PURCHASE -->
    <!-- ================================================================ -->
    <?php if ($purchase_id && $current_purchase): ?>
        
        <!-- ================================================================ -->
        <!-- PURCHASE DETAILS -->
        <!-- ================================================================ -->
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
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span class="purchase-status <?= strtolower($current_purchase['status']) ?>">
                        <i class="fas <?= $current_purchase['status'] === 'IN_PROGRESS' ? 'fa-spinner fa-spin' : 'fa-check-circle' ?>"></i>
                        <?= $current_purchase['status'] ?>
                    </span>
                    <?php if ($is_creator && $current_purchase['status'] === 'IN_PROGRESS'): ?>
                        <span style="font-size:0.6rem;color:var(--success);background:var(--success-light);padding:2px 10px;border-radius:12px;">
                            <i class="fas fa-crown"></i> Creator
                        </span>
                    <?php endif; ?>
                    <?php if ($current_purchase['status'] === 'COMPLETED'): ?>
                        <!-- PDF Invoice Button instead of Print -->
                        <button onclick="openPDFView(<?= $purchase_id ?>)" class="btn-print">
                            <i class="fas fa-file-pdf"></i> PDF Invoice
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ================================================================ -->
            <!-- PURCHASE INFO GRID -->
            <!-- ================================================================ -->
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
            
            <!-- ================================================================ -->
            <!-- ADD MEDICINE FORM -->
            <!-- ================================================================ -->
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
            
            <!-- ================================================================ -->
            <!-- ADD EQUIPMENT FORM -->
            <!-- ================================================================ -->
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
            
            <!-- ================================================================ -->
            <!-- ITEMS TABLE -->
            <!-- ================================================================ -->
            <div style="margin-top:16px;">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="fas fa-list title-blue"></i> Items 
                        <span class="result-count">(<strong><?= count($purchase_items) ?></strong> items)</span>
                    </h4>
                    <?php if ($current_purchase['status'] === 'IN_PROGRESS' && $is_creator): ?>
                        <div>
                            <form method="POST" style="display:inline;" 
                                  onsubmit="return confirm('Are you sure you want to complete this purchase?\nThis will update inventory and cannot be undone.');">
                                <input type="hidden" name="action" value="complete_purchase">
                                <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
                                <button type="submit" class="btn-complete" <?= count($purchase_items) == 0 ? 'disabled' : '' ?>>
                                    <i class="fas fa-check-circle"></i> Complete Purchase
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
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
                                This purchase has been completed.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    <!-- ================================================================ -->
    <!-- IF VIEWING LIST -->
    <!-- ================================================================ -->
    <?php else: ?>
        
        <!-- ================================================================ -->
        <!-- IN PROGRESS PURCHASES -->
        <!-- ================================================================ -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-spinner title-blue fa-spin"></i> In Progress Purchases
                    <span class="result-count">(<strong><?= count($in_progress_purchases) ?></strong>)</span>
                </h3>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <?php 
                        $has_med_in_progress = false;
                        $has_equip_in_progress = false;
                        foreach ($in_progress_purchases as $p) {
                            if ($p['purchase_type'] === 'medicine') $has_med_in_progress = true;
                            if ($p['purchase_type'] === 'equipment') $has_equip_in_progress = true;
                        }
                    ?>
                    <?php if (!$has_med_in_progress): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="create_purchase">
                            <input type="hidden" name="purchase_type" value="medicine">
                            <button type="submit" class="btn-add-purchase" style="padding:4px 14px;font-size:0.7rem;">
                                <i class="fas fa-plus-circle"></i> New Medicine
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if (!$has_equip_in_progress): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="create_purchase">
                            <input type="hidden" name="purchase_type" value="equipment">
                            <button type="submit" class="btn-add-purchase" style="padding:4px 14px;font-size:0.7rem;background:var(--purple);">
                                <i class="fas fa-plus-circle"></i> New Equipment
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($has_med_in_progress && $has_equip_in_progress): ?>
                        <span style="font-size:0.7rem;color:var(--text-secondary);padding:4px 10px;background:var(--bg-body);border-radius:6px;">
                            <i class="fas fa-info-circle"></i> Both types already in progress
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (count($in_progress_purchases) > 0): ?>
                <div class="grid-3">
                    <?php foreach ($in_progress_purchases as $purchase): ?>
                        <div class="purchase-card">
                            <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:4px;">
                                <div>
                                    <div class="invoice-number">
                                        <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($purchase['invoice_number']) ?>
                                        <span style="font-size:0.55rem;font-weight:400;color:var(--text-secondary);">
                                            (<?= ucfirst($purchase['purchase_type']) ?>)
                                        </span>
                                    </div>
                                    <div class="meta-text">
                                        <i class="fas fa-user"></i> <?= htmlspecialchars($purchase['creator_name'] ?? 'Unknown') ?>
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
                                </div>
                                <div style="display:flex;gap:4px;">
                                    <?php if ($purchase['created_by'] != $user_id): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="join_purchase">
                                            <input type="hidden" name="purchase_id" value="<?= $purchase['id'] ?>">
                                            <input type="hidden" name="purchase_type" value="<?= $purchase['purchase_type'] ?>">
                                            <button type="submit" class="btn-join">
                                                <i class="fas fa-sign-in-alt"></i> Join
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="purchases.php?id=<?= $purchase['id'] ?>&type=<?= $purchase['purchase_type'] ?>" class="btn-join" style="background:var(--success);">
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
                    <p>No purchases in progress</p>
                    <p style="font-size:0.8rem;margin-top:4px;">Click "New Medicine" or "New Equipment" to start one.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- ================================================================ -->
        <!-- COMPLETED PURCHASES -->
        <!-- ================================================================ -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-check-circle title-green"></i> Completed Purchases
                    <span class="result-count">(<strong><?= count($completed_purchases) ?></strong>)</span>
                </h3>
            </div>
            
            <?php if (count($completed_purchases) > 0): ?>
                <div class="grid-3">
                    <?php foreach ($completed_purchases as $purchase): ?>
                        <?php 
                            $profit = ($purchase['total_selling_value'] ?? 0) - ($purchase['total_buying_cost'] ?? 0);
                        ?>
                        <div class="purchase-card" style="border-color:var(--success-light);">
                            <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:4px;">
                                <div>
                                    <div class="invoice-number" style="color:var(--success);">
                                        <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($purchase['invoice_number']) ?>
                                        <span style="font-size:0.55rem;font-weight:400;color:var(--text-secondary);">
                                            (<?= ucfirst($purchase['purchase_type']) ?>)
                                        </span>
                                    </div>
                                    <div class="meta-text">
                                        <i class="fas fa-user"></i> <?= htmlspecialchars($purchase['creator_name'] ?? 'Unknown') ?>
                                    </div>
                                    <div class="meta-text">
                                        <i class="fas fa-check-circle"></i> <?= date('d/m/Y H:i', strtotime($purchase['completed_at'] ?? $purchase['created_at'])) ?>
                                    </div>
                                </div>
                                <span class="status-badge completed">
                                    <i class="fas fa-check-circle"></i> COMPLETED
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
                                    <?php if ($profit >= 0): ?>
                                        <span style="margin-left:6px;font-size:0.55rem;color:var(--success);background:var(--success-light);padding:1px 8px;border-radius:10px;">
                                            +<?= number_format($profit) ?> profit
                                        </span>
                                    <?php else: ?>
                                        <span style="margin-left:6px;font-size:0.55rem;color:var(--danger);background:var(--danger-light);padding:1px 8px;border-radius:10px;">
                                            <?= number_format($profit) ?> loss
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <a href="purchases.php?id=<?= $purchase['id'] ?>&type=<?= $purchase['purchase_type'] ?>" class="btn-view" style="background:var(--purple);color:white;padding:4px 14px;border-radius:6px;font-size:0.7rem;font-weight:600;border:none;cursor:pointer;transition:all 0.3s ease;text-decoration:none;">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>No completed purchases yet</p>
                </div>
            <?php endif; ?>
        </div>
        
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PDF VIEW MODAL -->
    <!-- ================================================================ -->
    <div class="modal-overlay" id="pdfModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-file-pdf" style="color:#DC2626;"></i> Purchase Invoice
                </div>
                <button class="modal-close" onclick="closePDF()">&times;</button>
            </div>
            
            <!-- Invoice Content -->
            <div id="pdfInvoiceContent" style="background:white;padding:10px;max-height:70vh;overflow-y:auto;">
                <div style="text-align:center;padding:30px;color:#64748B;">
                    <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i>
                    <p>Loading invoice...</p>
                </div>
            </div>
            
            <div class="modal-actions">
                <button class="btn-print-invoice" onclick="printPDFInvoice()">
                    <i class="fas fa-print"></i> Print Invoice
                </button>
                <button class="btn-close-modal" onclick="closePDF()">
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
            Pharmacy Purchases
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
// OPEN PDF VIEW - Loads invoice from get_invoice.php
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
    
    // Fetch invoice data from get_invoice.php
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
                    <button class="btn-close-modal" onclick="closePDF()" style="margin-top:10px;">
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
        // Fallback: print on current page
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
// CLOSE MODAL ON ESCAPE
// ================================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePDF();
    }
});

// ================================================================
// CLOSE MODAL ON OUTSIDE CLICK
// ================================================================
document.getElementById('pdfModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePDF();
    }
});

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
            
            // Category
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
            
            // Unit
            if (unitSelect && selected.unit) {
                for (var j = 0; j < unitSelect.options.length; j++) {
                    if (unitSelect.options[j].value === selected.unit) {
                        unitSelect.value = selected.unit;
                        break;
                    }
                }
            }
            
            // Buying Price
            if (buyingPriceInput) {
                buyingPriceInput.value = Number(selected.unit_cost || 0).toLocaleString();
            }
            
            // Selling Price
            if (sellingPriceInput) {
                sellingPriceInput.value = Number(selected.selling_price || 0).toLocaleString();
            }
            
            // Reorder Level
            if (reorderInput && selected.reorder_level > 0) {
                reorderInput.value = selected.reorder_level;
            }
            
            // Supplier
            if (supplierInput && selected.supplier) {
                supplierInput.value = selected.supplier;
            }
            
            // Default quantity
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
            
            // Category
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
            
            // Unit
            if (unitSelect && selected.unit) {
                for (var j = 0; j < unitSelect.options.length; j++) {
                    if (unitSelect.options[j].value === selected.unit) {
                        unitSelect.value = selected.unit;
                        break;
                    }
                }
            }
            
            // Buying Price
            if (buyingPriceInput) {
                buyingPriceInput.value = Number(selected.unit_cost || 0).toLocaleString();
            }
            
            // Selling Price
            if (sellingPriceInput) {
                sellingPriceInput.value = Number(selected.selling_price || 0).toLocaleString();
            }
            
            // Reorder Level
            if (reorderInput && selected.reorder_level > 0) {
                reorderInput.value = selected.reorder_level;
            }
            
            // Supplier
            if (supplierInput && selected.supplier) {
                supplierInput.value = selected.supplier;
            }
            
            // Default quantity
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
console.log('%c💊 Braick - Pharmacy Purchases', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Medicine Form: Auto-search, expiry required', 'font-size:13px; color:#34D399;');
console.log('%c✅ Equipment Form: Auto-search, expiry optional', 'font-size:13px; color:#7C3AED;');
console.log('%c✅ Items saved to purchase_items only', 'font-size:13px; color:#34D399;');
console.log('%c✅ Inventory updated ONLY on Complete Purchase', 'font-size:13px; color:#34D399;');
console.log('%c✅ PDF Invoice: Click PDF Invoice button to view', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>