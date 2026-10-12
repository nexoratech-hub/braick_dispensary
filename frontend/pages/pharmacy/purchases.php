<?php
// ================================================================
// FILE: frontend/pages/pharmacy/purchases.php
// PHARMACY - PURCHASE MANAGEMENT V9.2
// ✅ Kila purchase inaunda NEW BATCH (kama admin)
// ✅ Autocomplete inajaza category, unit, prices, supplier
// ✅ purchase_items inahifadhi taarifa zote za batch
// ✅ Auto-migration: item_id, medicine_id, equipment_id NULL-able
// ✅ UI/UX inafanana na admin
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
    header('Location: ../dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacist';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// AUTO-MIGRATION
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

// ✅ Rekebisha item_id kuwa NULL-able
try {
    $stmt = $db->query("SHOW COLUMNS FROM purchase_items LIKE 'item_id'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($col && strtoupper($col['Null']) === 'NO') {
        $db->exec("ALTER TABLE `purchase_items` MODIFY COLUMN `item_id` INT NULL");
    }
} catch (Exception $e) {}

try {
    $stmt = $db->query("SHOW COLUMNS FROM purchase_items LIKE 'medicine_id'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($col && strtoupper($col['Null']) === 'NO') {
        $db->exec("ALTER TABLE `purchase_items` MODIFY COLUMN `medicine_id` INT NULL");
    }
} catch (Exception $e) {}

try {
    $stmt = $db->query("SHOW COLUMNS FROM purchase_items LIKE 'equipment_id'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($col && strtoupper($col['Null']) === 'NO') {
        $db->exec("ALTER TABLE `purchase_items` MODIFY COLUMN `equipment_id` INT NULL");
    }
} catch (Exception $e) {}

// ✅ Ongeza columns mpya kwenye purchase_items
try {
    $stmt = $db->query("SHOW COLUMNS FROM purchase_items LIKE 'item_name'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `purchase_items` 
            ADD COLUMN `item_name` VARCHAR(255) NULL AFTER `item_id`,
            ADD COLUMN `item_category` VARCHAR(100) NULL AFTER `item_name`,
            ADD COLUMN `item_unit` VARCHAR(50) NULL AFTER `item_category`,
            ADD COLUMN `item_supplier` VARCHAR(255) NULL AFTER `item_unit`,
            ADD COLUMN `item_expiry_date` DATE NULL AFTER `item_supplier`,
            ADD COLUMN `item_batch_number` VARCHAR(100) NULL AFTER `item_expiry_date`,
            ADD COLUMN `item_status` VARCHAR(20) DEFAULT 'active' AFTER `item_batch_number`,
            ADD COLUMN `item_reorder_level` INT DEFAULT 10 AFTER `item_status`
        ");
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
    $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'cancelled_reason'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `purchases` ADD COLUMN `cancelled_reason` TEXT NULL AFTER `status`");
    }
} catch (Exception $e) {}

try {
    $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'cancelled_by'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `purchases` ADD COLUMN `cancelled_by` INT NULL AFTER `cancelled_reason`");
    }
} catch (Exception $e) {}

try {
    $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'cancelled_by_name'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `purchases` ADD COLUMN `cancelled_by_name` VARCHAR(100) NULL AFTER `cancelled_by`");
    }
} catch (Exception $e) {}

try {
    $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'cancelled_at'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `purchases` ADD COLUMN `cancelled_at` DATETIME NULL AFTER `cancelled_by_name`");
    }
} catch (Exception $e) {}

try {
    $stmt = $db->query("SHOW INDEX FROM purchases WHERE Key_name = 'invoice_number' AND Non_unique = 0");
    if ($stmt->rowCount() > 0) {
        $db->exec("ALTER TABLE `purchases` DROP INDEX `invoice_number`");
        $db->exec("ALTER TABLE `purchases` ADD INDEX `idx_invoice_number` (`invoice_number`)");
    }
} catch (Exception $e) {}

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

$predefined_units = [
    'pcs' => 'Pieces (pcs)', 'tablets' => 'Tablets', 'capsules' => 'Capsules',
    'ml' => 'Milliliters (ml)', 'mg' => 'Milligrams (mg)', 'g' => 'Grams (g)',
    'bottle' => 'Bottle', 'box' => 'Box', 'strip' => 'Strip', 'vial' => 'Vial',
    'ampoule' => 'Ampoule', 'sachet' => 'Sachet', 'tube' => 'Tube', 'pack' => 'Pack',
    'set' => 'Set', 'roll' => 'Roll', 'pair' => 'Pair', 'kit' => 'Kit',
    'unit' => 'Unit', 'dozen' => 'Dozen', 'litre' => 'Litre (L)',
    'bag' => 'Bag', 'carton' => 'Carton'
];

$purchase_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$purchase_type = isset($_GET['type']) ? $_GET['type'] : 'medicine';
$show_create_new = isset($_GET['create_new']) ? (int)$_GET['create_new'] : 0;

function formatMoney($amount) {
    if ($amount === null || $amount === '') return '0.00';
    return number_format((float)$amount, 2, '.', ',');
}

function formatMoneyNoDecimal($amount) {
    if ($amount === null || $amount === '') return '0';
    return number_format((float)$amount, 0, '.', ',');
}

function cleanMoney($value) { return str_replace(',', '', $value); }
function getMoney($value) { return floatval(cleanMoney($value)); }

// ================================================================
// GENERATE UNIQUE INVOICE NUMBER
// ================================================================
function generateUniqueInvoiceNumber($db, $prefix, $date) {
    $max_attempts = 50;
    
    for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
        $pattern = $prefix . '-' . $date . '-%';
        
        $stmt = $db->prepare("
            SELECT invoice_number 
            FROM purchases 
            WHERE invoice_number LIKE ? 
            ORDER BY invoice_number DESC 
            LIMIT 1
        ");
        $stmt->execute([$pattern]);
        $last_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($last_invoice) {
            $last_number = (int)substr($last_invoice['invoice_number'], -4);
            $new_number = $last_number + 1 + $attempt;
        } else {
            $new_number = 1 + $attempt;
        }
        
        if ($new_number > 9999) {
            $new_number = $new_number % 9999;
            if ($new_number == 0) $new_number = 1;
        }
        
        $candidate = $prefix . '-' . $date . '-' . str_pad($new_number, 4, '0', STR_PAD_LEFT);
        
        $stmt = $db->prepare("SELECT COUNT(*) as c FROM purchases WHERE invoice_number = ?");
        $stmt->execute([$candidate]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
        
        if ($exists == 0) {
            return $candidate;
        }
        
        usleep(50000);
    }
    
    return $prefix . '-' . $date . '-' . substr(time(), -4);
}

// ================================================================
// PROCESS POST REQUESTS
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // JOIN PURCHASE
    if ($action === 'join_purchase') {
        $purchase_id_join = (int)($_POST['purchase_id'] ?? 0);
        
        if ($purchase_id_join > 0) {
            $stmt = $db->prepare("SELECT id, status, invoice_number, purchase_type FROM purchases WHERE id = ? AND status = 'IN_PROGRESS' AND branch_id = ?");
            $stmt->execute([$purchase_id_join, $user_branch_id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($purchase) {
                header('Location: purchases.php?id=' . $purchase_id_join . '&type=' . $purchase['purchase_type']);
                exit;
            } else {
                $message = "❌ This purchase is not available or belongs to a different branch.";
                $message_type = 'error';
            }
        }
    }
    
    // CREATE NEW PURCHASE
    if ($action === 'create_purchase') {
        $purchase_type_new = $_POST['purchase_type'] ?? 'medicine';
        $force_new = isset($_POST['force_new']) ? (int)$_POST['force_new'] : 0;
        
        if (!$force_new) {
            $stmt = $db->prepare("
                SELECT p.id, p.invoice_number 
                FROM purchases p 
                WHERE p.status = 'IN_PROGRESS' 
                AND p.purchase_type = ? 
                AND p.created_by = ? 
                AND p.branch_id = ? 
                LIMIT 1
            ");
            $stmt->execute([$purchase_type_new, $user_id, $user_branch_id]);
            $creator_purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($creator_purchase) {
                $_SESSION['purchase_message'] = "You already have an IN_PROGRESS purchase: <strong>{$creator_purchase['invoice_number']}</strong>. Continue adding items or click 'Create New' to start fresh.";
                $_SESSION['purchase_message_type'] = 'info';
                header('Location: purchases.php?id=' . $creator_purchase['id'] . '&type=' . $purchase_type_new);
                exit;
            }
        }
        
        $date = date('Ymd');
        $prefix = $purchase_type_new === 'medicine' ? 'INV-MED' : 'INV-EQP';
        $invoice_number = generateUniqueInvoiceNumber($db, $prefix, $date);
        
        try {
            $stmt = $db->prepare("
                INSERT INTO purchases (invoice_number, purchase_type, created_by, created_by_name, branch_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'IN_PROGRESS', NOW())
            ");
            $stmt->execute([$invoice_number, $purchase_type_new, $user_id, $user_full_name, $user_branch_id]);
            $new_purchase_id = $db->lastInsertId();
            
            $_SESSION['purchase_message'] = "✅ New " . ucfirst($purchase_type_new) . " purchase created! Invoice: <strong>$invoice_number</strong>";
            $_SESSION['purchase_message_type'] = 'success';
            header('Location: purchases.php?id=' . $new_purchase_id . '&type=' . $purchase_type_new);
            exit;
            
        } catch (PDOException $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // ✅ ADD MEDICINE - Hifadhi kwenye purchase_items tu
    // ================================================================
    if ($action === 'add_medicine') {
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);
        $medication_name = trim($_POST['medication_name'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $buying_price = (float)str_replace(',', '', $_POST['buying_price'] ?? 0);
        $selling_price = (float)str_replace(',', '', $_POST['selling_price'] ?? 0);
        $expiry_date = $_POST['expiry_date'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $category = trim($_POST['category'] ?? '');
        
        if ($category === '__other__' && !empty($_POST['category_manual'])) $category = trim($_POST['category_manual']);
        if (empty($category)) $category = 'Other';
        
        $unit = trim($_POST['unit'] ?? 'pcs');
        if (empty($unit) && !empty($_POST['unit_manual'])) $unit = trim($_POST['unit_manual']);
        if (empty($unit) || $unit === '__other__') $unit = 'pcs';
        
        $reorder_level = (int)($_POST['reorder_level'] ?? 10);
        $supplier = trim($_POST['supplier'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        if (empty($batch_number)) $batch_number = 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        $stmt = $db->prepare("SELECT status, created_by, purchase_type, branch_id FROM purchases WHERE id = ?");
        $stmt->execute([$purchase_id_post]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $errors = [];
        if (!$purchase) $errors[] = 'Purchase not found';
        elseif ($purchase['status'] !== 'IN_PROGRESS') $errors[] = 'Purchase is completed';
        elseif ($purchase['branch_id'] != $user_branch_id) $errors[] = 'This purchase belongs to a different branch.';
        if ($quantity <= 0) $errors[] = 'Quantity must be > 0';
        if (empty($medication_name)) $errors[] = 'Medicine name is required';
        if (empty($expiry_date)) $errors[] = 'Expiry date is required';
        
        if (empty($errors)) {
            try {
                // ✅ Hifadhi kwenye purchase_items tu (item_id = NULL)
                $stmt = $db->prepare("
                    INSERT INTO purchase_items 
                    (purchase_id, item_type, item_id, item_name, item_category, item_unit, 
                     item_supplier, item_expiry_date, item_batch_number, item_status, item_reorder_level,
                     quantity, buying_price, selling_price, 
                     total_buying_cost, total_selling_value, added_by, added_by_name, added_at)
                    VALUES (?, 'medicine', NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $purchase_id_post, $medication_name, $category, $unit,
                    $supplier, $expiry_date, $batch_number, $status, $reorder_level,
                    $quantity, $buying_price, $selling_price,
                    $quantity * $buying_price, $quantity * $selling_price,
                    $user_id, $user_full_name
                ]);
                
                // Update totals
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total_items, SUM(quantity) as total_quantity,
                           SUM(total_buying_cost) as total_buying_cost,
                           SUM(total_selling_value) as total_selling_value
                    FROM purchase_items WHERE purchase_id = ?
                ");
                $stmt->execute([$purchase_id_post]);
                $totals = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    UPDATE purchases SET total_items = ?, total_quantity = ?, 
                        total_buying_cost = ?, total_selling_value = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $totals['total_items'] ?? 0, $totals['total_quantity'] ?? 0,
                    $totals['total_buying_cost'] ?? 0, $totals['total_selling_value'] ?? 0,
                    $purchase_id_post
                ]);
                
                $_SESSION['purchase_message'] = "✅ Medicine <strong>" . htmlspecialchars($medication_name) . "</strong> x $quantity added (Batch: $batch_number)";
                $_SESSION['purchase_message_type'] = 'success';
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=medicine');
                exit;
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
        
        if (!empty($errors)) { $message = implode('<br>', $errors); $message_type = 'error'; }
    }
    
    // ================================================================
    // ✅ ADD EQUIPMENT
    // ================================================================
    if ($action === 'add_equipment') {
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);
        $equipment_name = trim($_POST['equipment_name'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $buying_price = (float)str_replace(',', '', $_POST['buying_price'] ?? 0);
        $selling_price = (float)str_replace(',', '', $_POST['selling_price'] ?? 0);
        $expiry_date = $_POST['expiry_date'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $category = trim($_POST['category'] ?? '');
        
        if ($category === '__other__' && !empty($_POST['category_manual'])) $category = trim($_POST['category_manual']);
        if (empty($category)) $category = 'Other';
        
        $unit = trim($_POST['unit'] ?? 'pcs');
        if (empty($unit) && !empty($_POST['unit_manual'])) $unit = trim($_POST['unit_manual']);
        if (empty($unit) || $unit === '__other__') $unit = 'pcs';
        
        $reorder_level = (int)($_POST['reorder_level'] ?? 5);
        $supplier = trim($_POST['supplier'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        if (empty($batch_number)) $batch_number = 'EQP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        if (empty($expiry_date)) $expiry_date = null;
        
        $stmt = $db->prepare("SELECT status, created_by, purchase_type, branch_id FROM purchases WHERE id = ?");
        $stmt->execute([$purchase_id_post]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $errors = [];
        if (!$purchase) $errors[] = 'Purchase not found';
        elseif ($purchase['status'] !== 'IN_PROGRESS') $errors[] = 'Purchase is completed';
        elseif ($purchase['branch_id'] != $user_branch_id) $errors[] = 'This purchase belongs to a different branch.';
        if ($quantity <= 0) $errors[] = 'Quantity must be > 0';
        if (empty($equipment_name)) $errors[] = 'Equipment name is required';
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO purchase_items 
                    (purchase_id, item_type, item_id, item_name, item_category, item_unit, 
                     item_supplier, item_expiry_date, item_batch_number, item_status, item_reorder_level,
                     quantity, buying_price, selling_price, 
                     total_buying_cost, total_selling_value, added_by, added_by_name, added_at)
                    VALUES (?, 'equipment', NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $purchase_id_post, $equipment_name, $category, $unit,
                    $supplier, $expiry_date, $batch_number, $status, $reorder_level,
                    $quantity, $buying_price, $selling_price,
                    $quantity * $buying_price, $quantity * $selling_price,
                    $user_id, $user_full_name
                ]);
                
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total_items, SUM(quantity) as total_quantity,
                           SUM(total_buying_cost) as total_buying_cost,
                           SUM(total_selling_value) as total_selling_value
                    FROM purchase_items WHERE purchase_id = ?
                ");
                $stmt->execute([$purchase_id_post]);
                $totals = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    UPDATE purchases SET total_items = ?, total_quantity = ?, 
                        total_buying_cost = ?, total_selling_value = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $totals['total_items'] ?? 0, $totals['total_quantity'] ?? 0,
                    $totals['total_buying_cost'] ?? 0, $totals['total_selling_value'] ?? 0,
                    $purchase_id_post
                ]);
                
                $_SESSION['purchase_message'] = "✅ Equipment <strong>" . htmlspecialchars($equipment_name) . "</strong> x $quantity added (Batch: $batch_number)";
                $_SESSION['purchase_message_type'] = 'success';
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=equipment');
                exit;
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
        
        if (!empty($errors)) { $message = implode('<br>', $errors); $message_type = 'error'; }
    }
    
    // ================================================================
    // ✅ COMPLETE PURCHASE - Inaunda NEW BATCH kila item
    // ================================================================
    if ($action === 'complete_purchase') {
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);
        
        $stmt = $db->prepare("
            SELECT id, status, created_by, invoice_number, purchase_type, branch_id, total_items
            FROM purchases WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$purchase_id_post, $user_branch_id]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $errors = [];
        if (!$purchase) $errors[] = 'Purchase not found or belongs to a different branch';
        elseif ($purchase['status'] !== 'IN_PROGRESS') $errors[] = 'This purchase is already completed';
        elseif ($purchase['created_by'] != $user_id) $errors[] = 'Only the creator of this purchase can complete it';
        elseif ($purchase['total_items'] <= 0) $errors[] = 'Cannot complete empty purchase';
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Chukua items zote zilizo kwenye purchase
                $stmt = $db->prepare("SELECT * FROM purchase_items WHERE purchase_id = ?");
                $stmt->execute([$purchase_id_post]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $batches_created = 0;
                foreach ($items as $item) {
                    if ($item['item_type'] === 'medicine') {
                        // ✅ UNDA BATCH MPYA kila wakati
                        $stmt = $db->prepare("
                            INSERT INTO medications_inventory 
                            (medication_name, category, unit, quantity, reorder_level, 
                             unit_cost, selling_price, supplier, expiry_date, batch_number, 
                             branch_id, status, added_by, added_by_name, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $item['item_name'], 
                            $item['item_category'] ?: 'Other', 
                            $item['item_unit'] ?: 'pcs',
                            $item['quantity'], 
                            $item['item_reorder_level'] ?: 10,
                            $item['buying_price'], 
                            $item['selling_price'], 
                            $item['item_supplier'],
                            $item['item_expiry_date'], 
                            $item['item_batch_number'],
                            $purchase['branch_id'], 
                            $item['item_status'] ?: 'active',
                            $item['added_by'] ?: $user_id, 
                            $item['added_by_name'] ?: $user_full_name
                        ]);
                        $batches_created++;
                    } else {
                        // ✅ Equipment
                        $stmt = $db->prepare("
                            INSERT INTO medical_equipment 
                            (equipment_name, category, unit, quantity, reorder_level, 
                             unit_cost, selling_price, supplier, expiry_date, batch_number, 
                             branch_id, status, added_by, added_by_name, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $item['item_name'], 
                            $item['item_category'] ?: 'Other', 
                            $item['item_unit'] ?: 'pcs',
                            $item['quantity'], 
                            $item['item_reorder_level'] ?: 5,
                            $item['buying_price'], 
                            $item['selling_price'], 
                            $item['item_supplier'],
                            $item['item_expiry_date'], 
                            $item['item_batch_number'],
                            $purchase['branch_id'], 
                            $item['item_status'] ?: 'active',
                            $item['added_by'] ?: $user_id, 
                            $item['added_by_name'] ?: $user_full_name
                        ]);
                        $batches_created++;
                    }
                }
                
                $stmt = $db->prepare("UPDATE purchases SET status = 'COMPLETED', completed_at = NOW(), updated_at = NOW() WHERE id = ? AND branch_id = ?");
                $stmt->execute([$purchase_id_post, $user_branch_id]);
                
                $db->commit();
                
                $_SESSION['purchase_message'] = "✅ Purchase <strong>{$purchase['invoice_number']}</strong> completed! ($batches_created batches created)";
                $_SESSION['purchase_message_type'] = 'success';
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=' . $purchase['purchase_type']);
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
    // CANCEL PURCHASE
    // ================================================================
    if ($action === 'cancel_purchase') {
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);
        $cancel_reason = trim($_POST['cancel_reason_final'] ?? $_POST['cancel_reason'] ?? 'Cancelled by creator');
        
        $stmt = $db->prepare("SELECT id, status, created_by, invoice_number FROM purchases WHERE id = ? AND branch_id = ?");
        $stmt->execute([$purchase_id_post, $user_branch_id]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $errors = [];
        if (!$purchase) $errors[] = 'Purchase not found';
        elseif ($purchase['status'] !== 'IN_PROGRESS') $errors[] = 'Only IN_PROGRESS can be cancelled';
        elseif ($purchase['created_by'] != $user_id) $errors[] = 'Only creator can cancel';
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("DELETE FROM purchase_items WHERE purchase_id = ?");
                $stmt->execute([$purchase_id_post]);
                
                $stmt = $db->prepare("
                    UPDATE purchases SET status = 'CANCELLED', cancelled_reason = ?, 
                        cancelled_by = ?, cancelled_by_name = ?, cancelled_at = NOW(), updated_at = NOW()
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([$cancel_reason, $user_id, $user_full_name, $purchase_id_post, $user_branch_id]);
                
                $db->commit();
                
                $_SESSION['purchase_message'] = "🗑️ Purchase <strong>{$purchase['invoice_number']}</strong> cancelled!";
                $_SESSION['purchase_message_type'] = 'success';
                header('Location: purchases.php');
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
        
        $stmt = $db->prepare("SELECT status FROM purchases WHERE id = ? AND branch_id = ?");
        $stmt->execute([$purchase_id_post, $user_branch_id]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($purchase && $purchase['status'] === 'IN_PROGRESS') {
            try {
                $stmt = $db->prepare("DELETE FROM purchase_items WHERE id = ? AND purchase_id = ?");
                $stmt->execute([$item_id, $purchase_id_post]);
                
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total_items, SUM(quantity) as total_quantity,
                           SUM(total_buying_cost) as total_buying_cost,
                           SUM(total_selling_value) as total_selling_value
                    FROM purchase_items WHERE purchase_id = ?
                ");
                $stmt->execute([$purchase_id_post]);
                $totals = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    UPDATE purchases SET total_items = ?, total_quantity = ?, 
                        total_buying_cost = ?, total_selling_value = ?, updated_at = NOW()
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([
                    $totals['total_items'] ?? 0, $totals['total_quantity'] ?? 0,
                    $totals['total_buying_cost'] ?? 0, $totals['total_selling_value'] ?? 0,
                    $purchase_id_post, $user_branch_id
                ]);
                
                $_SESSION['purchase_message'] = "✅ Item removed!";
                $_SESSION['purchase_message_type'] = 'success';
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=' . $purchase_type);
                exit;
                
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

if (isset($_SESSION['purchase_message'])) {
    $message = $_SESSION['purchase_message'];
    $message_type = $_SESSION['purchase_message_type'] ?? 'success';
    unset($_SESSION['purchase_message']);
    unset($_SESSION['purchase_message_type']);
}

// ================================================================
// GET PURCHASE DATA
// ================================================================
$current_purchase = null;
$purchase_items = [];
$is_creator = false;
$can_edit = false;
$can_cancel = false;
$all_medicines = [];
$all_equipment = [];

if ($purchase_id > 0) {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as creator_name 
        FROM purchases p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$purchase_id, $user_branch_id]);
    $current_purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_purchase) {
        $is_creator = ($current_purchase['created_by'] == $user_id);
        $can_edit = ($current_purchase['status'] === 'IN_PROGRESS');
        $can_cancel = ($is_creator && $current_purchase['status'] === 'IN_PROGRESS');
        
        // ✅ Chukua purchase_items - tumia columns mpya
        $stmt = $db->prepare("
            SELECT pi.*, u.full_name as added_by_full_name
            FROM purchase_items pi
            LEFT JOIN users u ON pi.added_by = u.id
            WHERE pi.purchase_id = ?
            ORDER BY pi.added_at DESC
        ");
        $stmt->execute([$purchase_id]);
        $purchase_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ✅ Autocomplete data - GROUP BY medication_name (record ya mwisho)
        $stmt = $db->prepare("
            SELECT mi.*
            FROM medications_inventory mi
            INNER JOIN (
                SELECT medication_name, MAX(id) as max_id
                FROM medications_inventory
                WHERE branch_id = ? AND status = 'active'
                GROUP BY medication_name
            ) latest ON mi.id = latest.max_id
            WHERE mi.branch_id = ? AND mi.status = 'active'
            ORDER BY mi.medication_name
        ");
        $stmt->execute([$user_branch_id, $user_branch_id]);
        $all_medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("
            SELECT me.*
            FROM medical_equipment me
            INNER JOIN (
                SELECT equipment_name, MAX(id) as max_id
                FROM medical_equipment
                WHERE branch_id = ? AND status = 'active'
                GROUP BY equipment_name
            ) latest ON me.id = latest.max_id
            WHERE me.branch_id = ? AND me.status = 'active'
            ORDER BY me.equipment_name
        ");
        $stmt->execute([$user_branch_id, $user_branch_id]);
        $all_equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// GET ALL IN PROGRESS PURCHASES
$in_progress_purchases = [];
$stmt = $db->prepare("
    SELECT p.*, u.full_name as creator_name 
    FROM purchases p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.status = 'IN_PROGRESS' AND p.branch_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$user_branch_id]);
$in_progress_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GET COMPLETED PURCHASES
$completed_purchases = [];
$stmt = $db->prepare("
    SELECT p.*, u.full_name as creator_name 
    FROM purchases p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.status = 'COMPLETED' AND p.branch_id = ?
    ORDER BY p.completed_at DESC
    LIMIT 50
");
$stmt->execute([$user_branch_id]);
$completed_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GET CANCELLED PURCHASES
$cancelled_purchases = [];
try {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as creator_name 
        FROM purchases p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.status = 'CANCELLED' AND p.branch_id = ?
        ORDER BY COALESCE(p.cancelled_at, p.updated_at, p.created_at) DESC
        LIMIT 20
    ");
    $stmt->execute([$user_branch_id]);
    $cancelled_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as creator_name 
        FROM purchases p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.status = 'CANCELLED' AND p.branch_id = ?
        ORDER BY p.created_at DESC LIMIT 20
    ");
    $stmt->execute([$user_branch_id]);
    $cancelled_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$has_own_med = false;
$has_own_equip = false;
foreach ($in_progress_purchases as $p) {
    if ($p['created_by'] == $user_id && $p['purchase_type'] === 'medicine') $has_own_med = true;
    if ($p['created_by'] == $user_id && $p['purchase_type'] === 'equipment') $has_own_equip = true;
}

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {}

$profile_pic_url = !empty($profile_pic) ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG';

include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --pp-primary: #0B5ED7;
        --pp-primary-dark: #0A4CA8;
        --pp-primary-light: #E8F0FE;
        --pp-success: #059669;
        --pp-success-dark: #047857;
        --pp-success-light: #D1FAE5;
        --pp-warning: #D97706;
        --pp-warning-light: #FEF3C7;
        --pp-danger: #DC2626;
        --pp-danger-light: #FEE2E2;
        --pp-purple: #7C3AED;
        --pp-purple-light: #EDE9FE;
        --pp-teal: #0D9488;
        --pp-teal-light: #CCFBF1;
        --pp-bg-body: #F1F5F9;
        --pp-bg-card: #FFFFFF;
        --pp-border-color: #E2E8F0;
        --pp-text-primary: #1E293B;
        --pp-text-secondary: #64748B;
        --pp-text-muted: #94A3B8;
    }
    
    [data-theme="dark"] {
        --pp-bg-body: #0F172A;
        --pp-bg-card: #1E293B;
        --pp-border-color: #334155;
        --pp-text-primary: #F1F5F9;
        --pp-text-secondary: #94A3B8;
        --pp-text-muted: #64748B;
    }
    
    /* MAIN CONTENT */
    .main-content-pp {
        margin-left: 270px;
        margin-top: 68px;
        padding: 28px 32px;
        min-height: calc(100vh - 68px);
        background: var(--pp-bg-body);
    }
    
    /* PAGE HEADER BOX */
    .page-header-box-pp {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 16px;
        padding: 18px 24px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
        position: relative;
        overflow: hidden;
    }
    
    .page-header-box-pp::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }
    
    .page-header-box-pp.equipment-mode { background: linear-gradient(135deg, #7C3AED, #5B21B6); }
    
    .page-header-box-pp .page-title-pp {
        color: white; font-size: 1.4rem; font-weight: 700;
        display: flex; align-items: center; gap: 8px;
        flex-wrap: wrap; position: relative; z-index: 1; margin: 0;
    }
    
    .page-header-box-pp .role-badge-display {
        background: rgba(255,255,255,0.2); color: white;
        padding: 2px 10px; border-radius: 20px;
        font-size: 0.55rem; font-weight: 600; text-transform: uppercase;
    }
    
    .page-header-box-pp .branch-name-display {
        background: rgba(255,255,255,0.15); padding: 2px 12px;
        border-radius: 20px; font-size: 0.7rem; font-weight: 500;
        color: white;
    }
    
    .page-header-box-pp .page-subtitle-pp {
        color: rgba(255,255,255,0.85); font-size: 0.8rem;
        display: flex; align-items: center; gap: 6px;
        flex-wrap: wrap; position: relative; z-index: 1; margin-top: 4px;
    }
    
    .header-badge-pp {
        background: rgba(255,255,255,0.15); color: white;
        padding: 2px 10px; border-radius: 20px;
        font-size: 0.6rem; font-weight: 500;
        display: inline-flex; align-items: center; gap: 4px;
    }
    
    .header-actions-pp {
        display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
        position: relative; z-index: 1;
    }
    
    /* BUTTONS */
    .btn-pp {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 18px; border-radius: 10px;
        font-weight: 600; font-size: 0.82rem;
        transition: all 0.3s ease; cursor: pointer;
        text-decoration: none; border: none; font-family: inherit;
    }
    .btn-pp:hover { transform: translateY(-2px); }
    
    .btn-back-header-pp {
        background: rgba(255,255,255,0.2); color: white;
        border: 2px solid rgba(255,255,255,0.3);
        backdrop-filter: blur(4px);
    }
    .btn-back-header-pp:hover {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5); color: white;
    }
    
    .btn-add-purchase-pp {
        background: #059669; color: white;
        padding: 10px 24px; font-weight: 700;
        font-size: 0.9rem; border-radius: 8px;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
    }
    .btn-add-purchase-pp:hover { background: #047857; color: white; }
    
    .btn-complete-pp {
        background: #059669; color: white;
        padding: 10px 24px; border-radius: 8px;
        font-weight: 700; font-size: 0.85rem;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    .btn-complete-pp:hover { background: #047857; color: white; }
    .btn-complete-pp:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
    
    .btn-cancel-purchase-pp {
        background: #DC2626; color: white;
        padding: 10px 24px; border-radius: 8px;
        font-weight: 700; font-size: 0.85rem;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }
    .btn-cancel-purchase-pp:hover { background: #991B1B; color: white; }
    
    .btn-delete-item-pp {
        background: #DC2626; color: white; border: none;
        padding: 3px 10px; border-radius: 4px;
        font-size: 0.65rem; cursor: pointer;
    }
    .btn-delete-item-pp:hover { background: #991B1B; }
    
    .btn-print-pp {
        background: #DC2626; color: white;
        padding: 8px 20px; border-radius: 8px;
        font-weight: 600; font-size: 0.85rem;
    }
    .btn-print-pp:hover { background: #991B1B; color: white; }
    
    .btn-save-pp {
        background: #059669; color: white;
        padding: 10px 28px; border-radius: 10px;
        font-weight: 600; font-size: 0.9rem;
    }
    .btn-save-pp:hover { background: #047857; color: white; }
    
    .btn-cancel-pp {
        background: transparent; color: var(--pp-text-secondary);
        border: 2px solid var(--pp-border-color);
        padding: 10px 24px; border-radius: 10px;
        font-weight: 600; font-size: 0.9rem;
    }
    .btn-cancel-pp:hover { border-color: #DC2626; color: #DC2626; }
    
    .btn-generate-pp, .btn-toggle-pp {
        background: #0B5ED7; color: white; border: none;
        border-radius: 10px; padding: 8px 14px;
        font-size: 0.75rem; font-weight: 600;
        cursor: pointer; white-space: nowrap; height: 42px;
        display: inline-flex; align-items: center; gap: 4px;
    }
    .btn-generate-pp:hover, .btn-toggle-pp:hover { background: #0A4CA8; }
    
    /* MESSAGE BOX */
    .message-box-pp {
        padding: 12px 18px; border-radius: 10px;
        margin-bottom: 16px; display: flex;
        align-items: center; gap: 10px;
        font-weight: 500; font-size: 0.9rem;
        border-left: 5px solid transparent;
        animation: slideDownPP 0.4s ease;
    }
    @keyframes slideDownPP {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .message-box-pp.success { background: #D1FAE5; color: #065F46; border-left-color: #059669; }
    .message-box-pp.error { background: #FEE2E2; color: #991B1B; border-left-color: #DC2626; }
    .message-box-pp.info { background: #E8F0FE; color: #0A4CA8; border-left-color: #0B5ED7; }
    
    [data-theme="dark"] .message-box-pp.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .message-box-pp.error { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .message-box-pp.info { background: #1E3A5F; color: #6EA8FE; }
    
    .message-box-pp .message-close {
        margin-left: auto; background: none;
        border: none; cursor: pointer;
        font-size: 1.1rem; color: inherit;
    }
    
    /* CARD */
    .card-pp {
        background: var(--pp-bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 2px solid var(--pp-border-color);
        margin-bottom: 20px;
    }
    
    .card-header-pp {
        display: flex; justify-content: space-between;
        align-items: center; margin-bottom: 10px;
        flex-wrap: wrap; gap: 6px;
    }
    
    .card-title-pp {
        font-size: 0.9rem; font-weight: 600;
        color: var(--pp-text-primary); margin: 0;
    }
    
    .card-title-pp i.blue { color: #0B5ED7; }
    .card-title-pp i.purple { color: #7C3AED; }
    .card-title-pp i.green { color: #059669; }
    
    .result-count-pp {
        font-size: 0.75rem; color: var(--pp-text-secondary);
    }
    .result-count-pp strong { color: #0B5ED7; }
    
    /* GRID 3 */
    .grid-3-pp {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    
    /* PURCHASE CARD */
    .purchase-card-pp {
        background: var(--pp-bg-card);
        border: 2px solid var(--pp-border-color);
        border-radius: 10px;
        padding: 14px 16px;
        transition: all 0.3s ease;
    }
    
    .purchase-card-pp:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    
    .purchase-card-pp .invoice-number-pp { font-size: 1rem; font-weight: 700; }
    
    .purchase-card-pp .status-badge-pp {
        padding: 2px 10px; border-radius: 12px;
        font-size: 0.6rem; font-weight: 600;
    }
    .status-badge-pp.in-progress { background: #FEF3C7; color: #D97706; }
    .status-badge-pp.completed { background: #D1FAE5; color: #059669; }
    .status-badge-pp.cancelled { background: #FEE2E2; color: #DC2626; }
    
    .purchase-card-pp .meta-text-pp {
        font-size: 0.7rem; color: var(--pp-text-secondary);
    }
    
    /* PURCHASE DETAILS */
    .purchase-details-header-pp {
        display: flex; justify-content: space-between;
        align-items: center; flex-wrap: wrap;
        gap: 10px; margin-bottom: 16px;
    }
    
    .purchase-details-header-pp .invoice-number-pp {
        font-size: 1.4rem; font-weight: 700; color: #0B5ED7;
    }
    
    .purchase-status-pp {
        padding: 4px 16px; border-radius: 20px;
        font-size: 0.7rem; font-weight: 600;
    }
    .purchase-status-pp.in_progress { background: #FEF3C7; color: #D97706; }
    .purchase-status-pp.completed { background: #D1FAE5; color: #059669; }
    .purchase-status-pp.cancelled { background: #FEE2E2; color: #DC2626; }
    
    .purchase-info-grid-pp {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 10px; margin-bottom: 16px;
    }
    
    .purchase-info-item-pp {
        padding: 8px 12px;
        background: var(--pp-bg-body);
        border-radius: 6px;
    }
    
    .purchase-info-item-pp .label-pp {
        font-size: 0.55rem; text-transform: uppercase;
        color: var(--pp-text-secondary); font-weight: 600;
    }
    
    .purchase-info-item-pp .value-pp {
        font-size: 0.85rem; font-weight: 600; margin-top: 2px;
    }
    
    /* FORMS */
    .form-grid-pp {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }
    .form-grid-pp .full-width { grid-column: 1 / -1; }
    
    .form-label-pp {
        font-size: 0.75rem; font-weight: 600;
        margin-bottom: 3px; display: block;
        color: var(--pp-text-primary);
    }
    .form-label-pp .required { color: #DC2626; }
    
    .form-control-pp {
        width: 100%; padding: 7px 12px;
        border: 2px solid var(--pp-border-color);
        border-radius: 8px; font-size: 0.8rem;
        outline: none;
        background: var(--pp-bg-card);
        color: var(--pp-text-primary);
        height: 42px; font-family: inherit;
    }
    
    .form-control-pp:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .form-actions-pp {
        display: flex; gap: 10px; margin-top: 18px;
        padding-top: 14px;
        border-top: 2px solid var(--pp-border-color);
        flex-wrap: wrap;
    }
    
    .category-input-group-pp,
    .unit-input-group-pp,
    .batch-input-group-pp {
        display: flex; gap: 6px; align-items: center;
    }
    
    .category-input-group-pp .form-control-pp,
    .unit-input-group-pp .form-control-pp,
    .batch-input-group-pp .form-control-pp { flex: 1; }
    
    /* AUTOCOMPLETE */
    .autocomplete-container-pp { position: relative; width: 100%; }
    
    .autocomplete-list-pp {
        position: absolute; top: 100%; left: 0; right: 0;
        background: var(--pp-bg-card);
        border: 2px solid var(--pp-border-color);
        border-top: none;
        border-radius: 0 0 8px 8px;
        z-index: 100; max-height: 200px; overflow-y: auto;
        display: none;
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    }
    .autocomplete-list-pp.show { display: block; }
    
    .autocomplete-item-pp {
        padding: 8px 14px; cursor: pointer;
        border-bottom: 1px solid var(--pp-border-color);
        font-size: 0.82rem;
        color: var(--pp-text-primary);
    }
    .autocomplete-item-pp:hover { background: #E8F0FE; color: #0B5ED7; }
    [data-theme="dark"] .autocomplete-item-pp:hover { background: #1E3A5F; color: #6EA8FE; }
    
    .autocomplete-item-pp .item-detail-pp {
        font-size: 0.65rem; color: var(--pp-text-muted);
        display: block;
    }
    
    .batch-info-pp {
        background: #E8F0FE; border: 1px solid #0B5ED7;
        border-radius: 6px; padding: 6px 10px;
        font-size: 0.7rem; color: #0A4CA8;
        margin-top: 4px;
    }
    .batch-info-pp strong { color: #0B5ED7; }
    
    /* TABLE */
    .table-wrapper-pp { overflow-x: auto; }
    
    .data-table-pp {
        width: 100%; border-collapse: separate;
        border-spacing: 0; font-size: 0.78rem;
    }
    
    .data-table-pp thead th {
        background: #0B5ED7; color: white;
        padding: 8px 10px; font-size: 0.6rem;
        text-transform: uppercase; font-weight: 700;
        white-space: nowrap; text-align: left;
    }
    
    .data-table-pp tbody tr:nth-child(even) { background: #E8F0FE; }
    [data-theme="dark"] .data-table-pp tbody tr:nth-child(even) { background: #1E3A5F; }
    
    .data-table-pp tbody tr:hover td { background: #D1FAE5; }
    [data-theme="dark"] .data-table-pp tbody tr:hover td { background: #1A3A2A; }
    
    .data-table-pp td {
        padding: 8px 10px;
        border-bottom: 1px solid var(--pp-border-color);
        vertical-align: middle;
        color: var(--pp-text-primary);
    }
    
    .added-by-tag-pp {
        display: inline-flex; align-items: center; gap: 3px;
        padding: 1px 8px; border-radius: 10px;
        font-size: 0.6rem; font-weight: 600;
        background: #EDE9FE; color: #7C3AED;
    }
    
    .unit-badge-pp {
        display: inline-flex; align-items: center; gap: 3px;
        padding: 1px 6px; border-radius: 6px;
        font-size: 0.6rem; font-weight: 600;
        background: #CCFBF1; color: #0D9488;
    }
    
    /* EMPTY STATE */
    .empty-state-pp {
        text-align: center; padding: 40px 20px;
        color: var(--pp-text-secondary);
    }
    .empty-state-pp i {
        font-size: 3rem; color: var(--pp-text-muted);
        display: block; margin-bottom: 12px;
    }
    .empty-state-pp p { font-size: 0.95rem; margin-bottom: 6px; }
    .empty-state-pp .sub { font-size: 0.8rem; }
    
    /* MODAL */
    .modal-overlay-pp {
        display: none; position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.7); z-index: 999999;
        justify-content: center; align-items: center;
        padding: 20px; backdrop-filter: blur(4px);
    }
    .modal-overlay-pp.show { display: flex; }
    
    .modal-content-pp {
        background: var(--pp-bg-card);
        border-radius: 12px; max-width: 550px;
        width: 100%; max-height: 90vh; overflow-y: auto;
        padding: 24px 28px;
        border: 2px solid var(--pp-border-color);
        position: relative; z-index: 1000000;
    }
    
    .modal-content-pp.modal-pdf { max-width: 900px; background: white; padding: 20px; }
    
    .modal-header-pp {
        display: flex; justify-content: space-between;
        align-items: center; padding-bottom: 12px;
        border-bottom: 2px solid var(--pp-border-color);
        margin-bottom: 16px;
    }
    
    .modal-title-pp { font-size: 1.1rem; font-weight: 700; color: #DC2626; }
    
    .modal-close-pp {
        background: none; border: none;
        font-size: 1.5rem; cursor: pointer;
        color: var(--pp-text-secondary);
    }
    .modal-close-pp:hover { color: #DC2626; }
    
    .modal-actions-pp {
        display: flex; gap: 10px; padding-top: 14px;
        border-top: 2px solid var(--pp-border-color);
        margin-top: 16px; flex-wrap: wrap;
    }
    
    .btn-confirm-cancel-pp {
        background: #DC2626; color: white;
        padding: 10px 28px; border-radius: 8px;
        font-weight: 600; font-size: 0.9rem;
        border: none; cursor: pointer; flex: 1;
    }
    .btn-confirm-cancel-pp:hover { background: #991B1B; }
    
    .btn-close-modal-pp {
        background: transparent; color: var(--pp-text-secondary);
        border: 2px solid var(--pp-border-color);
        padding: 10px 24px; border-radius: 8px;
        font-weight: 600; font-size: 0.9rem; cursor: pointer;
    }
    .btn-close-modal-pp:hover { border-color: #DC2626; color: #DC2626; }
    
    .cancel-warning-icon-pp {
        text-align: center; font-size: 3rem;
        color: #DC2626; margin-bottom: 10px;
    }
    
    .cancel-reason-group-pp { margin-bottom: 12px; }
    .cancel-reason-group-pp label {
        display: block; font-size: 0.75rem;
        font-weight: 600; color: var(--pp-text-primary);
        margin-bottom: 4px;
    }
    
    .cancel-reason-group-pp select,
    .cancel-reason-group-pp textarea {
        width: 100%; padding: 8px 12px;
        border: 2px solid var(--pp-border-color);
        border-radius: 8px; font-size: 0.8rem;
        color: var(--pp-text-primary);
        outline: none; font-family: inherit;
        background: var(--pp-bg-card);
    }
    
    .cancel-reason-group-pp select:focus,
    .cancel-reason-group-pp textarea:focus {
        border-color: #DC2626;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
    }
    
    .btn-print-invoice-pp {
        background: #0B5ED7; color: white;
        padding: 8px 20px; border-radius: 8px;
        font-weight: 600; font-size: 0.85rem;
        border: none; cursor: pointer;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-print-invoice-pp:hover { background: #0A4CA8; }
    
    .btn-close-modal-pdf-pp {
        background: transparent; color: #64748B;
        border: 2px solid #E2E8F0;
        padding: 8px 20px; border-radius: 8px;
        font-weight: 600; font-size: 0.85rem; cursor: pointer;
    }
    .btn-close-modal-pdf-pp:hover { border-color: #DC2626; color: #DC2626; }
    
    .admin-badge-pp {
        background: linear-gradient(135deg, #7C3AED, #6D28D9);
        color: white; padding: 2px 10px; border-radius: 20px;
        font-size: 0.6rem; font-weight: 700;
        display: inline-flex; align-items: center; gap: 4px;
    }
    
    /* ACTION BAR */
    .action-bar-pp {
        background: linear-gradient(135deg, #FEF3C7, #FDE68A);
        border: 2px solid #D97706; border-radius: 12px;
        padding: 14px 20px; margin-top: 16px;
        display: flex; justify-content: space-between;
        align-items: center; flex-wrap: wrap; gap: 12px;
    }
    
    .action-bar-pp .action-info-pp {
        font-size: 0.85rem; color: #92400E; font-weight: 600;
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    
    .action-bar-pp .action-buttons-pp {
        display: flex; gap: 10px; flex-wrap: wrap;
    }
    
    /* FOOTER */
    .footer-pp {
        padding: 10px 0;
        border-top: 1px solid var(--pp-border-color);
        margin-top: 16px; text-align: center;
        font-size: 0.6rem; color: var(--pp-text-secondary);
    }
    .footer-pp .footer-brand-pp { color: #0B5ED7; font-weight: 600; }
    
    input.numeric-only { text-align: left; letter-spacing: 0.5px; }
    
    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .main-content-pp { margin-left: 0; padding: 16px; }
        .grid-3-pp { grid-template-columns: 1fr 1fr; }
    }
    
    @media (max-width: 768px) {
        .grid-3-pp { grid-template-columns: 1fr; }
        .form-grid-pp { grid-template-columns: 1fr; }
        .form-grid-pp .full-width { grid-column: 1; }
        .page-header-box-pp .page-title-pp { font-size: 1.1rem; }
        .header-actions-pp { width: 100%; }
        .data-table-pp { min-width: 900px; }
        .btn-back-header-pp { width: 100%; justify-content: center; }
        .action-bar-pp { flex-direction: column; align-items: stretch; }
        .action-bar-pp .action-buttons-pp { width: 100%; }
        .action-bar-pp .action-buttons-pp button { width: 100%; justify-content: center; }
        .purchase-info-grid-pp { grid-template-columns: 1fr; }
    }
</style>

<main class="main-content-pp">

    <!-- PAGE HEADER -->
    <div class="page-header-box-pp <?= $purchase_type === 'equipment' ? 'equipment-mode' : '' ?>">
        <div>
            <h1 class="page-title-pp">
                <?php if ($purchase_id && $current_purchase): ?>
                    <i class="fas fa-shopping-cart"></i> <?= htmlspecialchars($current_purchase['invoice_number']) ?>
                <?php else: ?>
                    <i class="fas fa-shopping-cart"></i> Pharmacy Purchases
                <?php endif; ?>
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <span class="branch-name-display">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </h1>
            <p class="page-subtitle-pp">
                <?php if ($purchase_id && $current_purchase): ?>
                    <strong><?= $current_purchase['total_items'] ?></strong> items · 
                    <strong><?= number_format($current_purchase['total_quantity']) ?></strong> units
                <?php else: ?>
                    <span class="header-badge-pp">
                        <i class="fas fa-spinner fa-spin"></i> 
                        <?= count($in_progress_purchases) ?> IN PROGRESS
                    </span>
                    <span class="header-badge-pp">
                        <i class="fas fa-check-circle"></i> 
                        <?= count($completed_purchases) ?> COMPLETED
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="header-actions-pp">
            <?php if ($purchase_id && $current_purchase): ?>
                <a href="purchases.php" class="btn-pp btn-back-header-pp">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            <?php else: ?>
                <a href="inventory.php" class="btn-pp btn-back-header-pp">
                    <i class="fas fa-arrow-left"></i> Back to Inventory
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="message-box-pp <?= $message_type ?>" id="messageBox">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'info' ? 'fa-info-circle' : 'fa-exclamation-circle') ?>"></i>
            <span><?= $message ?></span>
            <button class="message-close" onclick="closeMessage()">&times;</button>
        </div>
    <?php endif; ?>

    <!-- VIEW SPECIFIC PURCHASE -->
    <?php if ($purchase_id && $current_purchase): ?>
        <div class="card-pp">
            <div class="purchase-details-header-pp">
                <div>
                    <div class="invoice-number-pp">
                        <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($current_purchase['invoice_number']) ?>
                        <span style="font-size:0.65rem;font-weight:400;color:var(--pp-text-secondary);margin-left:8px;">
                            <?= ucfirst($current_purchase['purchase_type']) ?>
                        </span>
                    </div>
                    <div style="font-size:0.7rem;color:var(--pp-text-secondary);margin-top:2px;">
                        Created: <?= date('d/m/Y H:i', strtotime($current_purchase['created_at'])) ?>
                        <?php if ($current_purchase['completed_at']): ?>
                            | Completed: <?= date('d/m/Y H:i', strtotime($current_purchase['completed_at'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span class="purchase-status-pp <?= strtolower($current_purchase['status']) ?>">
                        <i class="fas <?= $current_purchase['status'] === 'IN_PROGRESS' ? 'fa-spinner fa-spin' : ($current_purchase['status'] === 'COMPLETED' ? 'fa-check-circle' : 'fa-times-circle') ?>"></i>
                        <?= $current_purchase['status'] ?>
                    </span>
                    <?php if ($is_creator): ?>
                        <span style="font-size:0.6rem;color:#059669;background:#D1FAE5;padding:2px 10px;border-radius:12px;">
                            <i class="fas fa-crown"></i> Creator
                        </span>
                    <?php endif; ?>
                    <?php if ($current_purchase['status'] === 'COMPLETED'): ?>
                        <button onclick="openPDFView(<?= $purchase_id ?>)" class="btn-pp btn-print-pp">
                            <i class="fas fa-file-pdf"></i> PDF Invoice
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php $profit = ($current_purchase['total_selling_value'] ?? 0) - ($current_purchase['total_buying_cost'] ?? 0); ?>
            <div class="purchase-info-grid-pp">
                <div class="purchase-info-item-pp">
                    <div class="label-pp">Created By</div>
                    <div class="value-pp">
                        <i class="fas fa-user" style="color:#0B5ED7;"></i>
                        <?= htmlspecialchars($current_purchase['creator_name'] ?? 'Unknown') ?>
                    </div>
                </div>
                <div class="purchase-info-item-pp">
                    <div class="label-pp">Total Items</div>
                    <div class="value-pp">
                        <i class="fas fa-boxes" style="color:#D97706;"></i>
                        <?= number_format($current_purchase['total_items']) ?> items
                    </div>
                </div>
                <div class="purchase-info-item-pp">
                    <div class="label-pp">Total Quantity</div>
                    <div class="value-pp">
                        <i class="fas fa-cubes" style="color:#0D9488;"></i>
                        <?= number_format($current_purchase['total_quantity']) ?> units
                    </div>
                </div>
                <div class="purchase-info-item-pp" style="background:#FEE2E2;border:2px solid #DC2626;">
                    <div class="label-pp" style="color:#DC2626;">💰 Buying Cost</div>
                    <div class="value-pp" style="color:#DC2626;">
                        TSh <?= number_format($current_purchase['total_buying_cost'] ?? 0) ?>
                    </div>
                </div>
                <div class="purchase-info-item-pp" style="background:#D1FAE5;border:2px solid #059669;">
                    <div class="label-pp" style="color:#059669;">💰 Selling Value</div>
                    <div class="value-pp" style="color:#059669;">
                        TSh <?= number_format($current_purchase['total_selling_value'] ?? 0) ?>
                    </div>
                </div>
                <div class="purchase-info-item-pp" style="background:<?= $profit >= 0 ? '#D1FAE5' : '#FEE2E2' ?>;border:2px solid <?= $profit >= 0 ? '#059669' : '#DC2626' ?>;">
                    <div class="label-pp" style="color:<?= $profit >= 0 ? '#059669' : '#DC2626' ?>;">📈 Expected Profit</div>
                    <div class="value-pp" style="color:<?= $profit >= 0 ? '#059669' : '#DC2626' ?>;">
                        TSh <?= number_format($profit) ?>
                    </div>
                </div>
            </div>
            
            <!-- ACTION BAR -->
            <?php if ($current_purchase['status'] === 'IN_PROGRESS'): ?>
                <div class="action-bar-pp">
                    <div class="action-info-pp">
                        <i class="fas fa-info-circle"></i>
                        This purchase has <strong><?= count($purchase_items) ?></strong> item(s). Click to complete or cancel.
                    </div>
                    <div class="action-buttons-pp">
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Complete this purchase? Batches zote zitaundwa kwenye inventory.');">
                            <input type="hidden" name="action" value="complete_purchase">
                            <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
                            <button type="submit" class="btn-pp btn-complete-pp" <?= count($purchase_items) == 0 ? 'disabled' : '' ?>>
                                <i class="fas fa-check-circle"></i> Complete Purchase
                            </button>
                        </form>
                        
                        <button type="button" class="btn-pp btn-cancel-purchase-pp" onclick="openCancelModal()">
                            <i class="fas fa-times-circle"></i> Cancel Purchase
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- ADD MEDICINE FORM -->
            <?php if ($current_purchase['purchase_type'] === 'medicine' && $current_purchase['status'] === 'IN_PROGRESS'): ?>
                <div class="card-pp" style="margin-top:12px;border-color:#059669;">
                    <div class="card-header-pp">
                        <h4 class="card-title-pp" style="font-size:0.85rem;">
                            <i class="fas fa-plus-circle green"></i> Add Medicine Batch
                        </h4>
                    </div>
                    
                    <form method="POST" id="addMedicineForm">
                        <input type="hidden" name="action" value="add_medicine">
                        <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
                        
                        <div class="form-grid-pp">
                            <div class="full-width">
                                <label class="form-label-pp">Medicine Name <span class="required">*</span></label>
                                <div class="autocomplete-container-pp">
                                    <input type="text" name="medication_name" id="purchaseMedicineName" class="form-control-pp" 
                                           placeholder="Type medicine name..." required autocomplete="off">
                                    <input type="hidden" name="medicine_id" id="purchaseMedicineId" value="">
                                    <div class="autocomplete-list-pp" id="purchaseMedicineAutocomplete"></div>
                                </div>
                                <div class="batch-info-pp" id="medicineBatchInfo" style="display:none;">
                                    <i class="fas fa-info-circle"></i> 
                                    Dawa hii ina <strong id="existingBatchesCount">0</strong> batches tayari kwenye inventory. 
                                    Unaongeza batch mpya.
                                </div>
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Category <span class="required">*</span></label>
                                <div class="category-input-group-pp">
                                    <select name="category" id="purchaseCategorySelect" class="form-control-pp" required>
                                        <option value="">Select</option>
                                        <?php foreach ($predefined_med_categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                        <?php endforeach; ?>
                                        <option value="__other__">+ Other</option>
                                    </select>
                                    <input type="text" name="category_manual" id="purchaseCategoryManual" class="form-control-pp" placeholder="Custom..." style="display:none;">
                                    <button type="button" class="btn-pp btn-toggle-pp" onclick="toggleCategory('med')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Unit <span class="required">*</span></label>
                                <div class="unit-input-group-pp">
                                    <select name="unit" id="purchaseUnit" class="form-control-pp" required>
                                        <option value="">Select Unit</option>
                                        <?php foreach ($predefined_units as $unit_key => $unit_label): ?>
                                            <option value="<?= htmlspecialchars($unit_key) ?>"><?= htmlspecialchars($unit_label) ?></option>
                                        <?php endforeach; ?>
                                        <option value="__other__">+ Other (Manual)</option>
                                    </select>
                                    <input type="text" name="unit_manual" id="purchaseUnitManual" class="form-control-pp" placeholder="Custom unit..." style="display:none;">
                                    <button type="button" class="btn-pp btn-toggle-pp" onclick="toggleUnit('med')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Quantity <span class="required">*</span></label>
                                <input type="text" name="quantity" id="purchaseQuantity" class="form-control-pp numeric-only" 
                                       placeholder="0" inputmode="numeric" pattern="[0-9]*" required autocomplete="off">
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Reorder Level</label>
                                <input type="text" name="reorder_level" id="purchaseReorderLevel" class="form-control-pp numeric-only" 
                                       value="10" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Buying Price (TSh) <span class="required">*</span></label>
                                <input type="text" name="buying_price" id="purchaseBuyingPrice" class="form-control-pp money-input" value="0" required autocomplete="off">
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Selling Price (TSh) <span class="required">*</span></label>
                                <input type="text" name="selling_price" id="purchaseUnitPrice" class="form-control-pp money-input" value="0" required autocomplete="off">
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Supplier</label>
                                <input type="text" name="supplier" id="purchaseSupplier" class="form-control-pp" placeholder="Supplier">
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Expiry Date <span class="required">*</span></label>
                                <input type="date" name="expiry_date" id="purchaseExpiryDate" class="form-control-pp" required>
                            </div>
                            
                            <div class="full-width">
                                <label class="form-label-pp">Batch Number</label>
                                <div class="batch-input-group-pp">
                                    <input type="text" name="batch_number" id="purchaseBatchInput" class="form-control-pp" 
                                           value="<?= 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)) ?>">
                                    <button type="button" class="btn-pp btn-generate-pp" onclick="generateBatch('med')">
                                        <i class="fas fa-sync-alt"></i> Generate
                                    </button>
                                </div>
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Status</label>
                                <select name="status" id="purchaseStatus" class="form-control-pp">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions-pp">
                            <button type="submit" class="btn-pp btn-save-pp">
                                <i class="fas fa-plus-circle"></i> Add Medicine Batch
                            </button>
                            <button type="reset" class="btn-pp btn-cancel-pp">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- ADD EQUIPMENT FORM -->
            <?php if ($current_purchase['purchase_type'] === 'equipment' && $current_purchase['status'] === 'IN_PROGRESS'): ?>
                <div class="card-pp" style="margin-top:12px;border-color:#7C3AED;">
                    <div class="card-header-pp">
                        <h4 class="card-title-pp" style="font-size:0.85rem;">
                            <i class="fas fa-plus-circle purple"></i> Add Equipment Batch
                        </h4>
                    </div>
                    
                    <form method="POST" id="addEquipmentForm">
                        <input type="hidden" name="action" value="add_equipment">
                        <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
                        
                        <div class="form-grid-pp">
                            <div class="full-width">
                                <label class="form-label-pp">Equipment Name <span class="required">*</span></label>
                                <div class="autocomplete-container-pp">
                                    <input type="text" name="equipment_name" id="purchaseEquipmentName" class="form-control-pp" 
                                           placeholder="Type equipment name..." required autocomplete="off">
                                    <input type="hidden" name="equipment_id" id="purchaseEquipmentId" value="">
                                    <div class="autocomplete-list-pp" id="purchaseEquipmentAutocomplete"></div>
                                </div>
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Category <span class="required">*</span></label>
                                <div class="category-input-group-pp">
                                    <select name="category" id="purchaseEquipCategorySelect" class="form-control-pp" required>
                                        <option value="">Select</option>
                                        <?php foreach ($predefined_equip_categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                        <?php endforeach; ?>
                                        <option value="__other__">+ Other</option>
                                    </select>
                                    <input type="text" name="category_manual" id="purchaseEquipCategoryManual" class="form-control-pp" placeholder="Custom..." style="display:none;">
                                    <button type="button" class="btn-pp btn-toggle-pp" onclick="toggleCategory('equip')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Unit <span class="required">*</span></label>
                                <div class="unit-input-group-pp">
                                    <select name="unit" id="purchaseEquipUnit" class="form-control-pp" required>
                                        <option value="">Select Unit</option>
                                        <option value="pcs">Pieces (pcs)</option>
                                        <option value="set">Set</option>
                                        <option value="box">Box</option>
                                        <option value="pack">Pack</option>
                                        <option value="pair">Pair</option>
                                        <option value="roll">Roll</option>
                                        <option value="kit">Kit</option>
                                        <option value="unit">Unit</option>
                                        <option value="dozen">Dozen</option>
                                        <option value="bag">Bag</option>
                                        <option value="carton">Carton</option>
                                        <option value="__other__">+ Other (Manual)</option>
                                    </select>
                                    <input type="text" name="unit_manual" id="purchaseEquipUnitManual" class="form-control-pp" placeholder="Custom unit..." style="display:none;">
                                    <button type="button" class="btn-pp btn-toggle-pp" onclick="toggleUnit('equip')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Quantity <span class="required">*</span></label>
                                <input type="text" name="quantity" id="purchaseEquipQuantity" class="form-control-pp numeric-only" 
                                       placeholder="0" inputmode="numeric" pattern="[0-9]*" required autocomplete="off">
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Reorder Level</label>
                                <input type="text" name="reorder_level" id="purchaseEquipReorderLevel" class="form-control-pp numeric-only" 
                                       value="5" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Buying Price (TSh) <span class="required">*</span></label>
                                <input type="text" name="buying_price" id="purchaseEquipBuyingPrice" class="form-control-pp money-input" value="0" required autocomplete="off">
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Selling Price (TSh) <span class="required">*</span></label>
                                <input type="text" name="selling_price" id="purchaseEquipSellingPrice" class="form-control-pp money-input" value="0" required autocomplete="off">
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Supplier</label>
                                <input type="text" name="supplier" id="purchaseEquipSupplier" class="form-control-pp" placeholder="Supplier">
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Expiry Date (Optional)</label>
                                <input type="date" name="expiry_date" id="purchaseEquipExpiryDate" class="form-control-pp">
                            </div>
                            
                            <div class="full-width">
                                <label class="form-label-pp">Batch Number</label>
                                <div class="batch-input-group-pp">
                                    <input type="text" name="batch_number" id="purchaseEquipBatchInput" class="form-control-pp" 
                                           value="<?= 'EQP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)) ?>">
                                    <button type="button" class="btn-pp btn-generate-pp" onclick="generateBatch('equip')">
                                        <i class="fas fa-sync-alt"></i> Generate
                                    </button>
                                </div>
                            </div>
                            
                            <div>
                                <label class="form-label-pp">Status</label>
                                <select name="status" id="purchaseEquipStatus" class="form-control-pp">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions-pp">
                            <button type="submit" class="btn-pp btn-save-pp" style="background:#7C3AED;">
                                <i class="fas fa-plus-circle"></i> Add Equipment Batch
                            </button>
                            <button type="reset" class="btn-pp btn-cancel-pp">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- ITEMS TABLE -->
            <div style="margin-top:16px;">
                <div class="card-header-pp">
                    <h4 class="card-title-pp">
                        <i class="fas fa-list blue"></i> Items (Batches) 
                        <span class="result-count-pp">(<strong><?= count($purchase_items) ?></strong> items)</span>
                    </h4>
                </div>
                
                <?php if (count($purchase_items) > 0): ?>
                    <div class="table-wrapper-pp">
                        <table class="data-table-pp">
                            <thead>
                                <tr>
                                    <th style="width:30px;text-align:center;">#</th>
                                    <th style="min-width:160px;">Item</th>
                                    <th style="min-width:90px;">Category</th>
                                    <th style="text-align:center;">Qty</th>
                                    <th style="text-align:center;">Unit</th>
                                    <th>Buy Price</th>
                                    <th>Buy Total</th>
                                    <th>Sell Price</th>
                                    <th>Sell Total</th>
                                    <th>Batch #</th>
                                    <th>Expiry</th>
                                    <th>Added By</th>
                                    <?php if ($current_purchase['status'] === 'IN_PROGRESS'): ?>
                                        <th style="text-align:center;">Action</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $counter = 1; ?>
                                <?php foreach ($purchase_items as $item): ?>
                                    <tr>
                                        <td style="text-align:center;"><?= $counter++ ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['item_name'] ?? 'Unknown') ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($item['item_category'] ?? 'N/A') ?></td>
                                        <td style="text-align:center;"><?= number_format($item['quantity']) ?></td>
                                        <td style="text-align:center;">
                                            <span class="unit-badge-pp">
                                                <i class="fas fa-balance-scale"></i>
                                                <?= htmlspecialchars($item['item_unit'] ?? 'pcs') ?>
                                            </span>
                                        </td>
                                        <td style="color:#DC2626;font-weight:600;">TSh <?= number_format($item['buying_price'] ?? 0) ?></td>
                                        <td style="color:#DC2626;font-weight:600;">TSh <?= number_format($item['total_buying_cost'] ?? 0) ?></td>
                                        <td style="color:#059669;font-weight:600;">TSh <?= number_format($item['selling_price'] ?? 0) ?></td>
                                        <td style="color:#059669;font-weight:600;">TSh <?= number_format($item['total_selling_value'] ?? 0) ?></td>
                                        <td>
                                            <span style="font-size:0.65rem;background:#FEF3C7;color:#D97706;padding:2px 8px;border-radius:6px;font-weight:600;">
                                                <?= htmlspecialchars($item['item_batch_number'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-size:0.7rem;color:#DC2626;font-weight:600;">
                                                <?= $item['item_expiry_date'] ? date('d/m/Y', strtotime($item['item_expiry_date'])) : 'N/A' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="added-by-tag-pp">
                                                <i class="fas fa-user-circle"></i>
                                                <?= htmlspecialchars($item['added_by_full_name'] ?? 'Unknown') ?>
                                            </span>
                                        </td>
                                        <?php if ($current_purchase['status'] === 'IN_PROGRESS'): ?>
                                            <td style="text-align:center;">
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this item?');">
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
                                                    <button type="submit" class="btn-delete-item-pp" title="Remove">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state-pp">
                        <i class="fas fa-box-open"></i>
                        <p>No items added yet</p>
                        <p class="sub">Use the form above to add items.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    <!-- VIEW LIST -->
    <?php else: ?>
        
        <!-- IN PROGRESS PURCHASES -->
        <div class="card-pp">
            <div class="card-header-pp">
                <h3 class="card-title-pp">
                    <i class="fas fa-spinner blue fa-spin"></i> In Progress Purchases
                    <span class="result-count-pp">(<strong><?= count($in_progress_purchases) ?></strong>)</span>
                </h3>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="create_purchase">
                        <input type="hidden" name="purchase_type" value="medicine">
                        <input type="hidden" name="force_new" value="1">
                        <button type="submit" class="btn-pp btn-add-purchase-pp" style="padding:6px 16px;font-size:0.75rem;">
                            <i class="fas fa-plus-circle"></i> New Medicine
                        </button>
                    </form>
                    
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="create_purchase">
                        <input type="hidden" name="purchase_type" value="equipment">
                        <input type="hidden" name="force_new" value="1">
                        <button type="submit" class="btn-pp btn-add-purchase-pp" style="padding:6px 16px;font-size:0.75rem;background:#7C3AED;">
                            <i class="fas fa-plus-circle"></i> New Equipment
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if (count($in_progress_purchases) > 0): ?>
                <div class="grid-3-pp">
                    <?php foreach ($in_progress_purchases as $purchase): ?>
                        <div class="purchase-card-pp" style="border-color:#D97706;">
                            <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:4px;">
                                <div>
                                    <div class="invoice-number-pp" style="color:#D97706;">
                                        <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($purchase['invoice_number']) ?>
                                    </div>
                                    <div class="meta-text-pp" style="margin-top:4px;">
                                        <i class="fas fa-user"></i> <?= htmlspecialchars($purchase['creator_name'] ?? 'Unknown') ?>
                                        <?php if ($purchase['created_by'] == $user_id): ?>
                                            <span style="color:#059669;font-weight:600;"> (You)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="meta-text-pp">
                                        <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?>
                                    </div>
                                </div>
                                <span class="status-badge-pp in-progress">
                                    <i class="fas fa-spinner fa-spin"></i> IN PROGRESS
                                </span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;flex-wrap:wrap;gap:4px;">
                                <div class="meta-text-pp">
                                    <i class="fas fa-boxes"></i> <?= number_format($purchase['total_items']) ?> items
                                </div>
                                <div style="display:flex;gap:4px;">
                                    <?php if ($purchase['created_by'] != $user_id): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="join_purchase">
                                            <input type="hidden" name="purchase_id" value="<?= $purchase['id'] ?>">
                                            <input type="hidden" name="purchase_type" value="<?= $purchase['purchase_type'] ?>">
                                            <button type="submit" class="btn-pp btn-add-purchase-pp" style="padding:6px 16px;font-size:0.75rem;">
                                                <i class="fas fa-sign-in-alt"></i> Join
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="purchases.php?id=<?= $purchase['id'] ?>&type=<?= $purchase['purchase_type'] ?>" class="btn-pp btn-add-purchase-pp" style="padding:6px 16px;font-size:0.75rem;">
                                            <i class="fas fa-arrow-right"></i> Continue
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state-pp">
                    <i class="fas fa-inbox"></i>
                    <p>No purchases in progress</p>
                    <p class="sub">Click "New Medicine" or "New Equipment" to start one.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- COMPLETED PURCHASES -->
        <div class="card-pp">
            <div class="card-header-pp">
                <h3 class="card-title-pp">
                    <i class="fas fa-check-circle green"></i> Completed Purchases
                    <span class="result-count-pp">(<strong><?= count($completed_purchases) ?></strong>)</span>
                </h3>
            </div>
            
            <?php if (count($completed_purchases) > 0): ?>
                <div class="grid-3-pp">
                    <?php foreach ($completed_purchases as $purchase): ?>
                        <div class="purchase-card-pp" style="border-color:#D1FAE5;">
                            <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:4px;">
                                <div>
                                    <div class="invoice-number-pp" style="color:#059669;">
                                        <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($purchase['invoice_number']) ?>
                                    </div>
                                    <div class="meta-text-pp">
                                        <i class="fas fa-user"></i> <?= htmlspecialchars($purchase['creator_name'] ?? 'Unknown') ?>
                                    </div>
                                    <div class="meta-text-pp">
                                        <i class="fas fa-check-circle"></i> <?= date('d/m/Y H:i', strtotime($purchase['completed_at'] ?? $purchase['created_at'])) ?>
                                    </div>
                                </div>
                                <span class="status-badge-pp completed">
                                    <i class="fas fa-check-circle"></i> COMPLETED
                                </span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
                                <div class="meta-text-pp">
                                    <i class="fas fa-boxes"></i> <?= number_format($purchase['total_items']) ?> items
                                    <span style="margin-left:6px;font-weight:600;color:#059669;">
                                        TSh <?= number_format($purchase['total_selling_value'] ?? 0) ?>
                                    </span>
                                </div>
                                <a href="purchases.php?id=<?= $purchase['id'] ?>&type=<?= $purchase['purchase_type'] ?>" class="btn-pp" style="padding:4px 12px;font-size:0.7rem;background:#7C3AED;color:white;">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state-pp">
                    <i class="fas fa-history"></i>
                    <p>No completed purchases yet</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- CANCELLED PURCHASES -->
        <?php if (count($cancelled_purchases) > 0): ?>
        <div class="card-pp">
            <div class="card-header-pp">
                <h3 class="card-title-pp">
                    <i class="fas fa-ban" style="color:#DC2626;"></i> Cancelled Purchases
                    <span class="result-count-pp">(<strong><?= count($cancelled_purchases) ?></strong>)</span>
                </h3>
            </div>
            
            <div class="grid-3-pp">
                <?php foreach ($cancelled_purchases as $purchase): ?>
                    <div class="purchase-card-pp" style="border-color:#FEE2E2;opacity:0.85;">
                        <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:4px;">
                            <div>
                                <div class="invoice-number-pp" style="color:#DC2626;text-decoration:line-through;">
                                    <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($purchase['invoice_number']) ?>
                                </div>
                                <div class="meta-text-pp">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($purchase['creator_name'] ?? 'Unknown') ?>
                                </div>
                                <?php if (!empty($purchase['cancelled_reason'])): ?>
                                    <div class="meta-text-pp" style="color:#DC2626;margin-top:4px;">
                                        <i class="fas fa-info-circle"></i> <?= htmlspecialchars($purchase['cancelled_reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="status-badge-pp cancelled">
                                <i class="fas fa-ban"></i> CANCELLED
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
    <?php endif; ?>

    <footer class="footer-pp">
        <p>
            <span class="footer-brand-pp">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Pharmacy Purchases
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<!-- CANCEL MODAL -->
<?php if ($purchase_id && $current_purchase && $can_cancel): ?>
<div class="modal-overlay-pp" id="cancelModal">
    <div class="modal-content-pp">
        <div class="modal-header-pp">
            <div class="modal-title-pp">
                <i class="fas fa-exclamation-triangle"></i> Cancel Purchase
            </div>
            <button class="modal-close-pp" onclick="closeCancelModal()">&times;</button>
        </div>
        
        <div class="cancel-warning-icon-pp">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        
        <p style="text-align:center;color:var(--pp-text-primary);font-weight:500;margin-bottom:8px;">
            Are you sure you want to cancel this purchase?
        </p>
        <p style="text-align:center;color:var(--pp-text-secondary);font-size:0.85rem;margin-bottom:16px;">
            <strong><?= htmlspecialchars($current_purchase['invoice_number']) ?></strong>
        </p>
        
        <div style="background:#FEE2E2;border:2px solid #DC2626;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:0.8rem;color:#991B1B;">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>WARNING:</strong> All items will be permanently deleted. Inventory will NOT be affected.
        </div>
        
        <form method="POST" id="cancelForm">
            <input type="hidden" name="action" value="cancel_purchase">
            <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
            
            <div class="cancel-reason-group-pp">
                <label>Reason for Cancellation <span style="color:#DC2626;">*</span></label>
                <select id="cancelReasonSelect" onchange="toggleOtherReason()" required>
                    <option value="">-- Select Reason --</option>
                    <option value="Added wrong item">Added wrong item</option>
                    <option value="Added wrong quantity">Added wrong quantity</option>
                    <option value="Added wrong price">Added wrong price</option>
                    <option value="Wrong medicine selected">Wrong medicine selected</option>
                    <option value="Changed my mind">Changed my mind</option>
                    <option value="Other">Other - Please specify below</option>
                </select>
            </div>
            
            <div class="cancel-reason-group-pp" id="otherReasonGroup" style="display:none;">
                <label>Please Explain <span style="color:#DC2626;">*</span></label>
                <textarea name="cancel_reason" id="cancelReasonText" rows="3" maxlength="500"></textarea>
            </div>
            
            <input type="hidden" name="cancel_reason_final" id="cancelReasonFinal">
            
            <div class="modal-actions-pp">
                <button type="submit" class="btn-confirm-cancel-pp" id="confirmCancelBtn">
                    <i class="fas fa-ban"></i> Yes, Cancel Purchase
                </button>
                <button type="button" class="btn-close-modal-pp" onclick="closeCancelModal()">
                    <i class="fas fa-times"></i> No, Go Back
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- PDF MODAL -->
<div class="modal-overlay-pp" id="pdfModal">
    <div class="modal-content-pp modal-pdf">
        <div class="modal-header-pp">
            <div class="modal-title-pp" style="color:#0B5ED7;">
                <i class="fas fa-file-pdf" style="color:#DC2626;"></i> Purchase Invoice
            </div>
            <button class="modal-close-pp" onclick="closePDF()">&times;</button>
        </div>
        
        <div id="pdfInvoiceContent" style="background:white;padding:10px;max-height:70vh;overflow-y:auto;">
            <div style="text-align:center;padding:30px;color:#64748B;">
                <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i>
                <p>Loading invoice...</p>
            </div>
        </div>
        
        <div class="modal-actions-pp">
            <button class="btn-pp btn-print-invoice-pp" onclick="printPDFInvoice()">
                <i class="fas fa-print"></i> Print Invoice
            </button>
            <button class="btn-pp btn-close-modal-pdf-pp" onclick="closePDF()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<script>
    // FOOTER TIME
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    // ZUIA SCROLL WHEEL KUBADILISHA NUMBER INPUTS
    document.addEventListener('wheel', function(e) {
        if (document.activeElement && 
            (document.activeElement.type === 'number' || 
             document.activeElement.classList.contains('numeric-only'))) {
            document.activeElement.blur();
        }
    }, { passive: true });

    function setupNumericOnlyInputsPurch() {
        document.querySelectorAll('.numeric-only').forEach(function(input) {
            if (input.dataset.numericInit) return;
            input.dataset.numericInit = 'true';
            
            input.addEventListener('keypress', function(e) {
                var char = String.fromCharCode(e.which);
                if (!/[0-9]/.test(char) && e.which !== 8 && e.which !== 0 && e.which !== 46) {
                    e.preventDefault();
                }
            });
            
            input.addEventListener('input', function() {
                var cursorPos = this.selectionStart;
                var oldVal = this.value;
                this.value = this.value.replace(/[^0-9]/g, '');
                if (oldVal !== this.value) {
                    this.setSelectionRange(cursorPos - 1, cursorPos - 1);
                }
            });
            
            input.addEventListener('wheel', function(e) {
                e.preventDefault();
                this.blur();
            }, { passive: false });
            
            input.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowUp' || e.key === 'ArrowDown') e.preventDefault();
            });
            
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                var pasted = (e.clipboardData || window.clipboardData).getData('text');
                var numbers = pasted.replace(/[^0-9]/g, '');
                if (numbers) document.execCommand('insertText', false, numbers);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', setupNumericOnlyInputsPurch);
    new MutationObserver(function() { setTimeout(setupNumericOnlyInputsPurch, 100); }).observe(document.body, { childList: true, subtree: true });

    // HELPERS
    function closeMessage() {
        var messageBox = document.getElementById('messageBox');
        if (messageBox) messageBox.style.display = 'none';
    }

    setTimeout(function() {
        var messageBox = document.getElementById('messageBox');
        if (messageBox) messageBox.style.display = 'none';
    }, 5000);

    function generateBatch(type) {
        var now = new Date();
        var dateStr = now.getFullYear() + String(now.getMonth() + 1).padStart(2, '0') + String(now.getDate()).padStart(2, '0');
        var random = Math.random().toString(36).substring(2, 8).toUpperCase();
        var prefix = type === 'med' ? 'BATCH' : 'EQP';
        var batch = prefix + '-' + dateStr + '-' + random;
        var inputId = type === 'med' ? 'purchaseBatchInput' : 'purchaseEquipBatchInput';
        var input = document.getElementById(inputId);
        if (input) input.value = batch;
    }

    function toggleCategory(type) {
        var select, manual;
        if (type === 'med') {
            select = document.getElementById('purchaseCategorySelect');
            manual = document.getElementById('purchaseCategoryManual');
        } else if (type === 'equip') {
            select = document.getElementById('purchaseEquipCategorySelect');
            manual = document.getElementById('purchaseEquipCategoryManual');
        } else return;
        
        if (!select || !manual) return;
        
        if (manual.style.display === 'none') {
            manual.style.display = 'block';
            select.style.display = 'none';
            manual.focus();
            manual.required = true;
            select.required = false;
            select.value = '';
        } else {
            manual.style.display = 'none';
            select.style.display = 'block';
            select.value = '';
            manual.required = false;
            select.required = true;
        }
    }

    function toggleUnit(type) {
        var select, manual;
        if (type === 'med') {
            select = document.getElementById('purchaseUnit');
            manual = document.getElementById('purchaseUnitManual');
        } else if (type === 'equip') {
            select = document.getElementById('purchaseEquipUnit');
            manual = document.getElementById('purchaseEquipUnitManual');
        } else return;
        
        if (!select || !manual) return;
        
        if (manual.style.display === 'none') {
            manual.style.display = 'block';
            select.style.display = 'none';
            manual.focus();
            manual.required = true;
            select.required = false;
            select.value = '';
        } else {
            manual.style.display = 'none';
            select.style.display = 'block';
            select.value = '';
            manual.required = false;
            select.required = true;
            manual.value = '';
        }
    }

    // CANCEL MODAL FUNCTIONS
    function openCancelModal() {
        var modal = document.getElementById('cancelModal');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeCancelModal() {
        var modal = document.getElementById('cancelModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
        var select = document.getElementById('cancelReasonSelect');
        var otherGroup = document.getElementById('otherReasonGroup');
        var text = document.getElementById('cancelReasonText');
        if (select) select.value = '';
        if (otherGroup) otherGroup.style.display = 'none';
        if (text) { text.value = ''; text.required = false; }
    }

    function toggleOtherReason() {
        var select = document.getElementById('cancelReasonSelect');
        var otherGroup = document.getElementById('otherReasonGroup');
        var text = document.getElementById('cancelReasonText');
        if (!select || !otherGroup || !text) return;
        
        if (select.value === 'Other') {
            otherGroup.style.display = 'block';
            text.required = true;
            text.focus();
        } else {
            otherGroup.style.display = 'none';
            text.required = false;
            text.value = '';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var cancelForm = document.getElementById('cancelForm');
        if (cancelForm) {
            cancelForm.addEventListener('submit', function(e) {
                var select = document.getElementById('cancelReasonSelect');
                var text = document.getElementById('cancelReasonText');
                var finalInput = document.getElementById('cancelReasonFinal');
                
                var finalReason = '';
                
                if (select.value === 'Other') {
                    if (!text.value.trim()) {
                        e.preventDefault();
                        alert('Please type your reason!');
                        text.focus();
                        return false;
                    }
                    finalReason = text.value.trim();
                } else if (select.value) {
                    finalReason = select.value;
                } else {
                    e.preventDefault();
                    alert('Please select a reason!');
                    select.focus();
                    return false;
                }
                
                finalInput.value = finalReason;
                
                if (!confirm('⚠️ Are you sure? All items will be permanently deleted!')) {
                    e.preventDefault();
                    return false;
                }
                
                var btn = document.getElementById('confirmCancelBtn');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
                }
            });
        }
    });

    // PDF FUNCTIONS
    function openPDFView(purchaseId) {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfInvoiceContent');
        
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        content.innerHTML = '<div style="text-align:center;padding:30px;color:#64748B;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i><p>Loading invoice...</p></div>';
        
        fetch('get_invoice.php?id=' + purchaseId)
            .then(function(r) { return r.text(); })
            .then(function(html) { content.innerHTML = html; })
            .catch(function(error) {
                content.innerHTML = '<div style="text-align:center;padding:30px;color:#DC2626;"><i class="fas fa-exclamation-circle" style="font-size:2rem;"></i><p>Error: ' + error.message + '</p></div>';
            });
    }

    function closePDF() {
        var modal = document.getElementById('pdfModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    }

    function printPDFInvoice() {
        var content = document.getElementById('pdfInvoiceContent');
        if (!content) return;
        
        var printContents = content.innerHTML;
        var win = window.open('', '_blank', 'width=900,height=700');
        if (!win) {
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            return;
        }
        
        win.document.write('<!DOCTYPE html><html><head><title>Invoice</title>');
        win.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">');
        win.document.write('<style>body{font-family:Arial;padding:20px;}</style>');
        win.document.write('</head><body>');
        win.document.write('<div class="invoice-container">' + printContents + '</div>');
        win.document.write('</body></html>');
        win.document.close();
        
        setTimeout(function() { win.focus(); win.print(); win.close(); }, 500);
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePDF();
            closeCancelModal();
        }
    });

    document.getElementById('pdfModal')?.addEventListener('click', function(e) {
        if (e.target === this) closePDF();
    });

    document.getElementById('cancelModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeCancelModal();
    });

    // MONEY FORMATTING
    (function() {
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
                    if (counter % 3 === 0 && i !== 0) formatted = ',' + formatted;
                }
                integerPart = formatted;
            }
            return integerPart + decimalPart;
        }
        
        function initMoneyInputs() {
            document.querySelectorAll('.money-input').forEach(function(input) {
                if (input.dataset.moneyInit) return;
                input.dataset.moneyInit = 'true';
                
                input.addEventListener('input', function() {
                    var cursorPos = this.selectionStart;
                    var before = this.value.length;
                    var formatted = formatWithCommas(this.value);
                    if (formatted !== this.value) {
                        this.value = formatted;
                        var diff = formatted.length - before;
                        this.setSelectionRange(cursorPos + diff, cursorPos + diff);
                    }
                });
                
                input.addEventListener('focus', function() {
                    this.value = this.value.replace(/,/g, '');
                    this.select();
                });
                
                input.addEventListener('blur', function() {
                    if (this.value) this.value = formatWithCommas(this.value);
                    else this.value = '0';
                });
            });
        }
        
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initMoneyInputs);
        else initMoneyInputs();
        
        new MutationObserver(function() { setTimeout(initMoneyInputs, 100); }).observe(document.body, { childList: true, subtree: true });
    })();

    // AUTOCOMPLETE - MEDICINE
    (function() {
        var medicineData = <?= json_encode($all_medicines) ?>;
        var input = document.getElementById('purchaseMedicineName');
        var autocomplete = document.getElementById('purchaseMedicineAutocomplete');
        var medicineIdInput = document.getElementById('purchaseMedicineId');
        var batchInfoDiv = document.getElementById('medicineBatchInfo');
        var batchesCountSpan = document.getElementById('existingBatchesCount');
        
        if (!input || !autocomplete) return;
        
        input.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();
            if (query.length < 1) { autocomplete.classList.remove('show'); return; }
            
            var matches = medicineData.filter(function(item) {
                return item.medication_name.toLowerCase().includes(query);
            });
            
            if (matches.length === 0) { autocomplete.classList.remove('show'); return; }
            
            var html = '';
            matches.forEach(function(item) {
                html += '<div class="autocomplete-item-pp" ' +
                    'data-id="' + item.id + '" ' +
                    'data-name="' + escapeHtml(item.medication_name) + '" ' +
                    'data-category="' + escapeHtml(item.category || '') + '" ' +
                    'data-unit="' + escapeHtml(item.unit || 'pcs') + '" ' +
                    'data-cost="' + (item.unit_cost || 0) + '" ' +
                    'data-price="' + (item.selling_price || 0) + '" ' +
                    'data-reorder="' + (item.reorder_level || 10) + '" ' +
                    'data-supplier="' + escapeHtml(item.supplier || '') + '">' +
                    '<strong>' + escapeHtml(item.medication_name) + '</strong>' +
                    '<span class="item-detail-pp">' +
                        'Category: ' + escapeHtml(item.category || 'N/A') + 
                        ' | Unit: ' + escapeHtml(item.unit || 'pcs') + 
                        ' | Buy: TSh ' + Number(item.unit_cost || 0).toLocaleString() + 
                        ' | Sell: TSh ' + Number(item.selling_price || 0).toLocaleString() +
                    '</span>' +
                    '</div>';
            });
            
            autocomplete.innerHTML = html;
            autocomplete.classList.add('show');
            
            autocomplete.querySelectorAll('.autocomplete-item-pp').forEach(function(item) {
                item.addEventListener('click', function() {
                    input.value = this.dataset.name;
                    if (medicineIdInput) medicineIdInput.value = this.dataset.id;
                    autocomplete.classList.remove('show');
                    
                    // Jaza category
                    var catVal = this.dataset.category;
                    var catSelect = document.getElementById('purchaseCategorySelect');
                    var catManual = document.getElementById('purchaseCategoryManual');
                    if (catVal && catSelect && catManual) {
                        var found = false;
                        for (var i = 0; i < catSelect.options.length; i++) {
                            if (catSelect.options[i].value === catVal) {
                                catSelect.value = catVal;
                                found = true;
                                break;
                            }
                        }
                        if (!found) {
                            catManual.style.display = 'block';
                            catSelect.style.display = 'none';
                            catManual.value = catVal;
                            catManual.required = true;
                            catSelect.required = false;
                        } else {
                            catManual.style.display = 'none';
                            catSelect.style.display = 'block';
                            catManual.required = false;
                            catSelect.required = true;
                        }
                    }
                    
                    // Jaza unit
                    var unitVal = this.dataset.unit;
                    var unitSelect = document.getElementById('purchaseUnit');
                    var unitManual = document.getElementById('purchaseUnitManual');
                    if (unitVal && unitSelect && unitManual) {
                        var unitFound = false;
                        for (var j = 0; j < unitSelect.options.length; j++) {
                            if (unitSelect.options[j].value === unitVal) {
                                unitSelect.value = unitVal;
                                unitFound = true;
                                break;
                            }
                        }
                        if (!unitFound) {
                            unitManual.style.display = 'block';
                            unitSelect.style.display = 'none';
                            unitManual.value = unitVal;
                            unitManual.required = true;
                            unitSelect.required = false;
                        } else {
                            unitManual.style.display = 'none';
                            unitSelect.style.display = 'block';
                            unitManual.required = false;
                            unitSelect.required = true;
                        }
                    }
                    
                    // Jaza prices na supplier
                    if (document.getElementById('purchaseBuyingPrice')) 
                        document.getElementById('purchaseBuyingPrice').value = Number(this.dataset.cost || 0).toLocaleString();
                    if (document.getElementById('purchaseUnitPrice')) 
                        document.getElementById('purchaseUnitPrice').value = Number(this.dataset.price || 0).toLocaleString();
                    if (document.getElementById('purchaseReorderLevel')) 
                        document.getElementById('purchaseReorderLevel').value = this.dataset.reorder || 10;
                    if (document.getElementById('purchaseSupplier')) 
                        document.getElementById('purchaseSupplier').value = this.dataset.supplier || '';
                    
                    // Onyesha info ya batches zilizopo
                    if (batchInfoDiv && batchesCountSpan) {
                        batchInfoDiv.style.display = 'block';
                        batchesCountSpan.textContent = '1+';
                    }
                    
                    // Focus quantity
                    var qtyInput = document.getElementById('purchaseQuantity');
                    if (qtyInput) qtyInput.focus();
                });
            });
        });
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.autocomplete-container-pp')) autocomplete.classList.remove('show');
        });
    })();

    // AUTOCOMPLETE - EQUIPMENT
    (function() {
        var equipmentData = <?= json_encode($all_equipment) ?>;
        var input = document.getElementById('purchaseEquipmentName');
        var autocomplete = document.getElementById('purchaseEquipmentAutocomplete');
        var equipmentIdInput = document.getElementById('purchaseEquipmentId');
        
        if (!input || !autocomplete) return;
        
        input.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();
            if (query.length < 1) { autocomplete.classList.remove('show'); return; }
            
            var matches = equipmentData.filter(function(item) {
                return item.equipment_name.toLowerCase().includes(query);
            });
            
            if (matches.length === 0) { autocomplete.classList.remove('show'); return; }
            
            var html = '';
            matches.forEach(function(item) {
                html += '<div class="autocomplete-item-pp" ' +
                    'data-id="' + item.id + '" ' +
                    'data-name="' + escapeHtml(item.equipment_name) + '" ' +
                    'data-category="' + escapeHtml(item.category || '') + '" ' +
                    'data-unit="' + escapeHtml(item.unit || 'pcs') + '" ' +
                    'data-cost="' + (item.unit_cost || 0) + '" ' +
                    'data-price="' + (item.selling_price || 0) + '" ' +
                    'data-reorder="' + (item.reorder_level || 5) + '" ' +
                    'data-supplier="' + escapeHtml(item.supplier || '') + '">' +
                    '<strong>' + escapeHtml(item.equipment_name) + '</strong>' +
                    '<span class="item-detail-pp">' +
                        'Category: ' + escapeHtml(item.category || 'N/A') + 
                        ' | Unit: ' + escapeHtml(item.unit || 'pcs') + 
                        ' | Buy: TSh ' + Number(item.unit_cost || 0).toLocaleString() + 
                        ' | Sell: TSh ' + Number(item.selling_price || 0).toLocaleString() +
                    '</span>' +
                    '</div>';
            });
            
            autocomplete.innerHTML = html;
            autocomplete.classList.add('show');
            
            autocomplete.querySelectorAll('.autocomplete-item-pp').forEach(function(item) {
                item.addEventListener('click', function() {
                    input.value = this.dataset.name;
                    if (equipmentIdInput) equipmentIdInput.value = this.dataset.id;
                    autocomplete.classList.remove('show');
                    
                    // Category
                    var catVal = this.dataset.category;
                    var catSelect = document.getElementById('purchaseEquipCategorySelect');
                    var catManual = document.getElementById('purchaseEquipCategoryManual');
                    if (catVal && catSelect && catManual) {
                        var found = false;
                        for (var i = 0; i < catSelect.options.length; i++) {
                            if (catSelect.options[i].value === catVal) {
                                catSelect.value = catVal;
                                found = true;
                                break;
                            }
                        }
                        if (!found) {
                            catManual.style.display = 'block';
                            catSelect.style.display = 'none';
                            catManual.value = catVal;
                            catManual.required = true;
                            catSelect.required = false;
                        } else {
                            catManual.style.display = 'none';
                            catSelect.style.display = 'block';
                            catManual.required = false;
                            catSelect.required = true;
                        }
                    }
                    
                    // Unit
                    var unitVal = this.dataset.unit;
                    var unitSelect = document.getElementById('purchaseEquipUnit');
                    var unitManual = document.getElementById('purchaseEquipUnitManual');
                    if (unitVal && unitSelect && unitManual) {
                        var unitFound = false;
                        for (var j = 0; j < unitSelect.options.length; j++) {
                            if (unitSelect.options[j].value === unitVal) {
                                unitSelect.value = unitVal;
                                unitFound = true;
                                break;
                            }
                        }
                        if (!unitFound) {
                            unitManual.style.display = 'block';
                            unitSelect.style.display = 'none';
                            unitManual.value = unitVal;
                            unitManual.required = true;
                            unitSelect.required = false;
                        } else {
                            unitManual.style.display = 'none';
                            unitSelect.style.display = 'block';
                            unitManual.required = false;
                            unitSelect.required = true;
                        }
                    }
                    
                    // Prices na supplier
                    if (document.getElementById('purchaseEquipBuyingPrice')) 
                        document.getElementById('purchaseEquipBuyingPrice').value = Number(this.dataset.cost || 0).toLocaleString();
                    if (document.getElementById('purchaseEquipSellingPrice')) 
                        document.getElementById('purchaseEquipSellingPrice').value = Number(this.dataset.price || 0).toLocaleString();
                    if (document.getElementById('purchaseEquipReorderLevel')) 
                        document.getElementById('purchaseEquipReorderLevel').value = this.dataset.reorder || 5;
                    if (document.getElementById('purchaseEquipSupplier')) 
                        document.getElementById('purchaseEquipSupplier').value = this.dataset.supplier || '';
                    
                    var qtyInput = document.getElementById('purchaseEquipQuantity');
                    if (qtyInput) qtyInput.focus();
                });
            });
        });
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.autocomplete-container-pp')) autocomplete.classList.remove('show');
        });
    })();

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    console.log('%c💊 Braick - Pharmacy Purchases V9.2', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Kila purchase inaunda NEW BATCH', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Autocomplete inajaza category, unit, prices, supplier', 'font-size:13px; color:#34D399;');
    console.log('%c✅ purchase_items inahifadhi taarifa zote za batch', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Mouse scroll hairuhusiwi kupunguza quantities', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>