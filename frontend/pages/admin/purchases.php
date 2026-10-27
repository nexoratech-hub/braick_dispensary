<?php
// ================================================================
// FILE: frontend/pages/admin/purchases.php
// ADMIN - PURCHASE MANAGEMENT V10.1
// ✅ V10.1: GLOBAL DISCOUNT (Amount default / Percent) - inapunguza BUYING COST
// ✅ V10.1: Inventory unit_cost = baada ya discount
// ✅ Cancel reason OPTIONAL kwa admin
// ✅ Kila IN_PROGRESS invoice ina button ya DELETE
// ✅ Kila purchase inaunda NEW BATCH (haiongezi kwenye iliyopo)
// ✅ Autocomplete inajaza category, unit, prices, supplier
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_is_online = $_SESSION['is_online'] ?? 1;

$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

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
    $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'branch_id'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `purchases` ADD COLUMN `branch_id` INT NULL AFTER `created_by_name`");
        $db->exec("UPDATE `purchases` SET `branch_id` = 1 WHERE `branch_id` IS NULL");
    }
} catch (Exception $e) {}

// ✅ V10.1: DISCOUNT COLUMNS
try {
    $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'global_discount_type'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `purchases` 
            ADD COLUMN `global_discount_type` VARCHAR(20) DEFAULT 'amount' AFTER `total_selling_value`,
            ADD COLUMN `global_discount_value` DECIMAL(12,2) DEFAULT 0 AFTER `global_discount_type`,
            ADD COLUMN `global_discount_amount` DECIMAL(12,2) DEFAULT 0 AFTER `global_discount_value`,
            ADD COLUMN `final_total_buying_cost` DECIMAL(12,2) DEFAULT 0 AFTER `global_discount_amount`,
            ADD COLUMN `final_total_selling_value` DECIMAL(12,2) DEFAULT 0 AFTER `final_total_buying_cost`
        ");
    }
} catch (Exception $e) {}

try {
    $stmt = $db->query("SHOW COLUMNS FROM purchases LIKE 'final_total_buying_cost'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `purchases` ADD COLUMN `final_total_buying_cost` DECIMAL(12,2) DEFAULT 0 AFTER `global_discount_amount`");
    }
} catch (Exception $e) {}

// ================================================================
// PREDEFINED DATA
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

$predefined_units = [
    'pcs' => 'Pieces (pcs)', 'tablets' => 'Tablets', 'capsules' => 'Capsules',
    'ml' => 'Milliliters (ml)', 'mg' => 'Milligrams (mg)', 'g' => 'Grams (g)',
    'bottle' => 'Bottle', 'box' => 'Box', 'strip' => 'Strip', 'vial' => 'Vial',
    'ampoule' => 'Ampoule', 'sachet' => 'Sachet', 'tube' => 'Tube', 'pack' => 'Pack',
    'set' => 'Set', 'roll' => 'Roll', 'pair' => 'Pair', 'kit' => 'Kit',
    'unit' => 'Unit', 'dozen' => 'Dozen', 'litre' => 'Litre (L)',
    'bag' => 'Bag', 'carton' => 'Carton'
];

// ================================================================
// ✅ V10.1 HELPER: Recalculate Purchase Totals (DISCOUNT INAPUNGUZA BUYING)
// ================================================================
function recalculatePurchaseTotalsAdmin($db, $purchase_id) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_items, SUM(quantity) as total_quantity,
               SUM(total_buying_cost) as total_buying_cost,
               SUM(total_selling_value) as total_selling_value
        FROM purchase_items WHERE purchase_id = ?
    ");
    $stmt->execute([$purchase_id]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_items = (int)($totals['total_items'] ?? 0);
    $total_quantity = (int)($totals['total_quantity'] ?? 0);
    $total_buying_cost = (float)($totals['total_buying_cost'] ?? 0);
    $total_selling_value = (float)($totals['total_selling_value'] ?? 0);

    // Pata discount info ya sasa
    $stmt = $db->prepare("SELECT global_discount_type, global_discount_value FROM purchases WHERE id = ?");
    $stmt->execute([$purchase_id]);
    $disc = $stmt->fetch(PDO::FETCH_ASSOC);

    $discount_type = $disc['global_discount_type'] ?? 'amount';
    $discount_value = (float)($disc['global_discount_value'] ?? 0);

    // ✅ DISCOUNT INAPUNGUZA BUYING COST
    $global_discount_amount = 0;
    if ($discount_type === 'percent') {
        $pct = min(max($discount_value, 0), 100);
        $global_discount_amount = $total_buying_cost * ($pct / 100);
    } else {
        $global_discount_amount = min(max($discount_value, 0), $total_buying_cost);
    }

    $final_total_buying_cost = $total_buying_cost - $global_discount_amount;
    if ($final_total_buying_cost < 0) $final_total_buying_cost = 0;

    // Selling haibadiliki
    $final_total_selling_value = $total_selling_value;

    $stmt = $db->prepare("
        UPDATE purchases SET 
            total_items = ?, total_quantity = ?,
            total_buying_cost = ?, total_selling_value = ?,
            global_discount_amount = ?, final_total_buying_cost = ?,
            final_total_selling_value = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $total_items, $total_quantity,
        $total_buying_cost, $total_selling_value,
        $global_discount_amount, $final_total_buying_cost,
        $final_total_selling_value,
        $purchase_id
    ]);

    return [
        'total_items' => $total_items,
        'total_quantity' => $total_quantity,
        'total_buying_cost' => $total_buying_cost,
        'total_selling_value' => $total_selling_value,
        'global_discount_amount' => $global_discount_amount,
        'final_total_buying_cost' => $final_total_buying_cost,
        'final_total_selling_value' => $final_total_selling_value
    ];
}

// ================================================================
// GET PARAMETERS
// ================================================================
$purchase_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$url_type = isset($_GET['type']) ? trim($_GET['type']) : '';

$active_view = 'none';
if ($action === 'list_medicine') $active_view = 'medicine';
elseif ($action === 'list_equipment') $active_view = 'equipment';
elseif ($action === 'create' && $url_type === 'medicine') $active_view = 'medicine';
elseif ($action === 'create' && $url_type === 'equipment') $active_view = 'equipment';
elseif ($purchase_id > 0 && $url_type === 'medicine') $active_view = 'medicine';
elseif ($purchase_id > 0 && $url_type === 'equipment') $active_view = 'equipment';
elseif ($url_type === 'medicine') $active_view = 'medicine';
elseif ($url_type === 'equipment') $active_view = 'equipment';

$purchase_type = ($active_view === 'equipment') ? 'equipment' : 'medicine';

function formatMoney($amount) {
    if ($amount === null || $amount === '') return '0.00';
    return number_format((float)$amount, 2, '.', ',');
}
function cleanMoney($value) { return str_replace(',', '', $value); }
function getMoney($value) { return floatval(cleanMoney($value)); }

// ================================================================
// PROCESS POST REQUESTS
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_post = $_POST['action'] ?? '';
    $post_branch = $_POST['branch'] ?? $selected_branch_id;

    // CREATE NEW PURCHASE
    if ($action_post === 'create_purchase') {
        $purchase_type_new = $_POST['purchase_type'] ?? 'medicine';
        $create_branch_id = $user_branch_id;
        if ($post_branch !== 'all' && is_numeric($post_branch)) {
            $create_branch_id = (int)$post_branch;
        }

        $date = date('Ymd');
        $prefix = $purchase_type_new === 'medicine' ? 'INV-MED' : 'INV-EQP';
        $pattern = $prefix . '-' . $date . '-%';

        $stmt = $db->prepare("SELECT invoice_number FROM purchases WHERE invoice_number LIKE ?");
        $stmt->execute([$pattern]);
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $used = [];
        foreach ($existing as $inv) {
            if (preg_match('/-(\d+)$/', $inv, $m)) $used[(int)$m[1]] = true;
        }

        $new_num = 1;
        for ($i = 1; $i <= 9999; $i++) {
            if (!isset($used[$i])) { $new_num = $i; break; }
        }

        $invoice_number = $prefix . '-' . $date . '-' . str_pad($new_num, 4, '0', STR_PAD_LEFT);

        $max_attempts = 10;
        $inserted = false;
        $new_purchase_id = null;

        for ($attempt = 0; $attempt < $max_attempts && !$inserted; $attempt++) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO purchases (invoice_number, purchase_type, created_by, created_by_name, branch_id, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'IN_PROGRESS', NOW())
                ");
                $stmt->execute([$invoice_number, $purchase_type_new, $user_id, $user_full_name, $create_branch_id]);
                $new_purchase_id = $db->lastInsertId();
                $inserted = true;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $new_num++;
                    if ($new_num > 9999) $new_num = 1;
                    $invoice_number = $prefix . '-' . $date . '-' . str_pad($new_num, 4, '0', STR_PAD_LEFT);
                    usleep(100000);
                } else {
                    $message = "❌ Error: " . $e->getMessage();
                    $message_type = 'error';
                    break;
                }
            }
        }

        if ($inserted && $new_purchase_id) {
            $_SESSION['purchase_message'] = "✅ New " . ucfirst($purchase_type_new) . " purchase created! Invoice: <strong>$invoice_number</strong>";
            $_SESSION['purchase_message_type'] = 'success';
            header('Location: purchases.php?id=' . $new_purchase_id . '&type=' . $purchase_type_new . '&branch=' . $post_branch);
            exit;
        }
    }

    // ADD MEDICINE
    if ($action_post === 'add_medicine') {
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
        if ($quantity <= 0) $errors[] = 'Quantity must be > 0';
        if (empty($medication_name)) $errors[] = 'Medicine name is required';
        if (empty($expiry_date)) $errors[] = 'Expiry date is required';

        if (empty($errors)) {
            try {
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

                recalculatePurchaseTotalsAdmin($db, $purchase_id_post);

                $_SESSION['purchase_message'] = "✅ Medicine <strong>" . htmlspecialchars($medication_name) . "</strong> x $quantity added (Batch: $batch_number)";
                $_SESSION['purchase_message_type'] = 'success';
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=medicine&branch=' . $post_branch);
                exit;
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }

        if (!empty($errors)) { $message = implode('<br>', $errors); $message_type = 'error'; }
    }

    // ADD EQUIPMENT
    if ($action_post === 'add_equipment') {
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

                recalculatePurchaseTotalsAdmin($db, $purchase_id_post);

                $_SESSION['purchase_message'] = "✅ Equipment <strong>" . htmlspecialchars($equipment_name) . "</strong> x $quantity added (Batch: $batch_number)";
                $_SESSION['purchase_message_type'] = 'success';
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=equipment&branch=' . $post_branch);
                exit;
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }

        if (!empty($errors)) { $message = implode('<br>', $errors); $message_type = 'error'; }
    }

    // ✅ V10.1: UPDATE GLOBAL DISCOUNT
    if ($action_post === 'update_global_discount') {
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);
        $discount_type = $_POST['global_discount_type'] ?? 'amount';
        $discount_value = (float)str_replace(',', '', $_POST['global_discount_value'] ?? 0);

        if (!in_array($discount_type, ['amount', 'percent'])) $discount_type = 'amount';

        if ($discount_type === 'percent') {
            $discount_value = min(max($discount_value, 0), 100);
        } else {
            $discount_value = max($discount_value, 0);
        }

        $stmt = $db->prepare("SELECT status FROM purchases WHERE id = ?");
        $stmt->execute([$purchase_id_post]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($purchase && $purchase['status'] === 'IN_PROGRESS') {
            try {
                $stmt = $db->prepare("
                    UPDATE purchases SET 
                        global_discount_type = ?,
                        global_discount_value = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$discount_type, $discount_value, $purchase_id_post]);

                $result = recalculatePurchaseTotalsAdmin($db, $purchase_id_post);

                $display = $discount_type === 'percent' 
                    ? number_format($discount_value, 2) . '%' 
                    : 'TSh ' . number_format($discount_value);

                $_SESSION['purchase_message'] = "✅ Discount: <strong>$display</strong> = TSh " . number_format($result['global_discount_amount']) . " (imepunguza buying cost)";
                $_SESSION['purchase_message_type'] = 'success';
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=' . $purchase_type . '&branch=' . $post_branch);
                exit;
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }

    // COMPLETE PURCHASE - ✅ unit_cost baada ya discount
    if ($action_post === 'complete_purchase') {
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);
        $stmt = $db->prepare("
            SELECT id, status, created_by, invoice_number, purchase_type, branch_id, total_items,
                   total_buying_cost, global_discount_amount
            FROM purchases WHERE id = ?
        ");
        $stmt->execute([$purchase_id_post]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        $errors = [];
        if (!$purchase) $errors[] = 'Purchase not found';
        elseif ($purchase['status'] !== 'IN_PROGRESS') $errors[] = 'Already completed';
        elseif ($purchase['total_items'] <= 0) $errors[] = 'Cannot complete empty purchase';

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("SELECT * FROM purchase_items WHERE purchase_id = ?");
                $stmt->execute([$purchase_id_post]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // ✅ Kokotoa discount ratio
                $global_discount = (float)($purchase['global_discount_amount'] ?? 0);
                $total_buying = (float)($purchase['total_buying_cost'] ?? 0);
                $discount_ratio = ($total_buying > 0) ? ($global_discount / $total_buying) : 0;

                $batches_created = 0;
                foreach ($items as $item) {
                    // ✅ unit_cost inapunguzwa kwa discount ratio
                    $final_unit_cost = $item['buying_price'] * (1 - $discount_ratio);
                    
                    if ($item['item_type'] === 'medicine') {
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
                            $final_unit_cost,
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
                            $final_unit_cost,
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

                $stmt = $db->prepare("UPDATE purchases SET status = 'COMPLETED', completed_at = NOW(), updated_at = NOW() WHERE id = ?");
                $stmt->execute([$purchase_id_post]);
                $db->commit();

                $discount_msg = ($global_discount > 0) ? " (Discount: TSh " . number_format($global_discount) . " imepunguza buying cost)" : "";
                $_SESSION['purchase_message'] = "✅ Purchase <strong>{$purchase['invoice_number']}</strong> completed! ($batches_created batches created)$discount_msg";
                $_SESSION['purchase_message_type'] = 'success';
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=' . $purchase['purchase_type'] . '&branch=' . $post_branch);
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
        if (!empty($errors)) { $message = implode('<br>', $errors); $message_type = 'error'; }
    }

    // CANCEL PURCHASE - Reason OPTIONAL
    if ($action_post === 'cancel_purchase') {
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);
        $cancel_reason_raw = trim($_POST['cancel_reason'] ?? '');
        $cancel_reason = !empty($cancel_reason_raw) ? $cancel_reason_raw : 'Cancelled by Admin';

        $stmt = $db->prepare("SELECT id, status, invoice_number, purchase_type FROM purchases WHERE id = ?");
        $stmt->execute([$purchase_id_post]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        $errors = [];
        if (!$purchase) $errors[] = 'Purchase not found';
        elseif ($purchase['status'] === 'COMPLETED') $errors[] = 'Cannot cancel completed';
        elseif ($purchase['status'] === 'CANCELLED') $errors[] = 'Already cancelled';

        if (empty($errors)) {
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("DELETE FROM purchase_items WHERE purchase_id = ?");
                $stmt->execute([$purchase_id_post]);

                $stmt = $db->prepare("
                    UPDATE purchases SET status = 'CANCELLED', cancelled_reason = ?, cancelled_by = ?, 
                        cancelled_by_name = ?, cancelled_at = NOW(), updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$cancel_reason, $user_id, $user_full_name, $purchase_id_post]);
                $db->commit();

                $_SESSION['purchase_message'] = "✅ Purchase <strong>{$purchase['invoice_number']}</strong> cancelled!";
                $_SESSION['purchase_message_type'] = 'success';
                header('Location: purchases.php?type=' . $purchase['purchase_type'] . '&branch=' . $post_branch);
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
        if (!empty($errors)) { $message = implode('<br>', $errors); $message_type = 'error'; }
    }

    // DELETE PURCHASE
    if ($action_post === 'delete_purchase') {
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);
        
        $stmt = $db->prepare("SELECT id, status, invoice_number, purchase_type FROM purchases WHERE id = ?");
        $stmt->execute([$purchase_id_post]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        $errors = [];
        if (!$purchase) $errors[] = 'Purchase not found';
        elseif ($purchase['status'] === 'COMPLETED') $errors[] = 'Cannot delete completed purchase.';
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("DELETE FROM purchase_items WHERE purchase_id = ?");
                $stmt->execute([$purchase_id_post]);
                $stmt = $db->prepare("DELETE FROM purchases WHERE id = ?");
                $stmt->execute([$purchase_id_post]);
                $db->commit();
                
                $_SESSION['purchase_message'] = "🗑️ Purchase <strong>{$purchase['invoice_number']}</strong> deleted!";
                $_SESSION['purchase_message_type'] = 'success';
                header('Location: purchases.php?action=list_' . $purchase['purchase_type'] . '&branch=' . $post_branch);
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
        if (!empty($errors)) { $message = implode('<br>', $errors); $message_type = 'error'; }
    }

    // DELETE ITEM
    if ($action_post === 'delete_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $purchase_id_post = (int)($_POST['purchase_id'] ?? 0);

        $stmt = $db->prepare("SELECT status, purchase_type FROM purchases WHERE id = ?");
        $stmt->execute([$purchase_id_post]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($purchase && $purchase['status'] === 'IN_PROGRESS') {
            try {
                $stmt = $db->prepare("DELETE FROM purchase_items WHERE id = ? AND purchase_id = ?");
                $stmt->execute([$item_id, $purchase_id_post]);
                recalculatePurchaseTotalsAdmin($db, $purchase_id_post);

                $_SESSION['purchase_message'] = "✅ Item removed!";
                $_SESSION['purchase_message_type'] = 'success';
                header('Location: purchases.php?id=' . $purchase_id_post . '&type=' . $purchase['purchase_type'] . '&branch=' . $post_branch);
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
// GET CURRENT PURCHASE
// ================================================================
$current_purchase = null;
$purchase_items = [];
$is_creator = false;
$can_edit = false;
$all_medicines = [];
$all_equipment = [];

if ($purchase_id > 0) {
    $branch_where = "";
    $branch_params = [$purchase_id];
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $branch_where = " AND p.branch_id = ?";
        $branch_params[] = (int)$selected_branch_id;
    }

    $stmt = $db->prepare("
        SELECT p.*, u.full_name as creator_name, a.full_name as cancelled_by_name, b.name as branch_name
        FROM purchases p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN users a ON p.cancelled_by = a.id
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.id = ?" . $branch_where
    );
    $stmt->execute($branch_params);
    $current_purchase = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($current_purchase) {
        $is_creator = ($current_purchase['created_by'] == $user_id);
        $can_edit = ($current_purchase['status'] === 'IN_PROGRESS');
        $purchase_branch = $current_purchase['branch_id'] ?? $user_branch_id;

        // Default discount values
        if (!isset($current_purchase['global_discount_type']) || empty($current_purchase['global_discount_type'])) {
            $current_purchase['global_discount_type'] = 'amount';
        }
        if (!isset($current_purchase['global_discount_value'])) $current_purchase['global_discount_value'] = 0;
        if (!isset($current_purchase['global_discount_amount'])) $current_purchase['global_discount_amount'] = 0;
        if (!isset($current_purchase['final_total_buying_cost']) || $current_purchase['final_total_buying_cost'] == 0) {
            $current_purchase['final_total_buying_cost'] = $current_purchase['total_buying_cost'] ?? 0;
        }
        if (!isset($current_purchase['final_total_selling_value']) || $current_purchase['final_total_selling_value'] == 0) {
            $current_purchase['final_total_selling_value'] = $current_purchase['total_selling_value'] ?? 0;
        }

        $stmt = $db->prepare("
            SELECT pi.*, u.full_name as added_by_full_name
            FROM purchase_items pi
            LEFT JOIN users u ON pi.added_by = u.id
            WHERE pi.purchase_id = ?
            ORDER BY pi.added_at DESC
        ");
        $stmt->execute([$purchase_id]);
        $purchase_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        $stmt->execute([$purchase_branch, $purchase_branch]);
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
        $stmt->execute([$purchase_branch, $purchase_branch]);
        $all_equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ================================================================
// GET IN_PROGRESS PURCHASES
// ================================================================
$all_med_in_progress = [];
$all_equip_in_progress = [];

if ($active_view === 'medicine') {
    $med_where = "p.status = 'IN_PROGRESS' AND p.purchase_type = 'medicine'";
    $med_params = [];
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $med_where .= " AND p.branch_id = ?";
        $med_params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as creator_name, b.name as branch_name
        FROM purchases p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE " . $med_where . "
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($med_params);
    $all_med_in_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($active_view === 'equipment') {
    $equip_where = "p.status = 'IN_PROGRESS' AND p.purchase_type = 'equipment'";
    $equip_params = [];
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $equip_where .= " AND p.branch_id = ?";
        $equip_params[] = (int)$selected_branch_id;
    }
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as creator_name, b.name as branch_name
        FROM purchases p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE " . $equip_where . "
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($equip_params);
    $all_equip_in_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG';

$display_branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $stmt->execute([(int)$selected_branch_id]);
    $bd = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($bd) $display_branch_name = $bd['name'];
}

$page_title = 'Admin Purchases';
if ($active_view === 'medicine') $page_title = 'Medicine Purchases';
elseif ($active_view === 'equipment') $page_title = 'Equipment Purchases';
elseif ($purchase_id > 0 && $current_purchase) $page_title = 'Purchase: ' . $current_purchase['invoice_number'];

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<style>
    .page-header-box-purch {
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
    .page-header-box-purch::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }
    .page-header-box-purch.equipment-mode { background: linear-gradient(135deg, #7C3AED, #5B21B6); }
    .page-header-box-purch .page-title-purch {
        color: white; font-size: 1.4rem; font-weight: 700;
        display: flex; align-items: center; gap: 8px;
        flex-wrap: wrap; position: relative; z-index: 1; margin: 0;
    }
    .page-header-box-purch .page-subtitle-purch {
        color: rgba(255,255,255,0.85); font-size: 0.8rem;
        display: flex; align-items: center; gap: 6px;
        flex-wrap: wrap; position: relative; z-index: 1; margin-top: 4px;
    }
    .page-header-box-purch .role-badge-display {
        background: rgba(255,255,255,0.2); color: white;
        padding: 2px 10px; border-radius: 20px;
        font-size: 0.55rem; font-weight: 600; text-transform: uppercase;
    }
    .page-header-box-purch .branch-name-display {
        background: rgba(255,255,255,0.15); padding: 2px 12px;
        border-radius: 20px; font-size: 0.7rem; font-weight: 500; color: white;
    }
    .header-badge-purch {
        background: rgba(255,255,255,0.15); color: white;
        padding: 2px 10px; border-radius: 20px;
        font-size: 0.6rem; font-weight: 500;
        display: inline-flex; align-items: center; gap: 4px;
    }
    .header-actions-purch {
        display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
        position: relative; z-index: 1;
    }
    .btn-purch {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 18px; border-radius: 10px;
        font-weight: 600; font-size: 0.82rem;
        transition: all 0.3s ease; cursor: pointer;
        text-decoration: none; border: none; font-family: inherit;
    }
    .btn-purch:hover { transform: translateY(-2px); }
    .btn-back-header-purch {
        background: rgba(255,255,255,0.2); color: white;
        border: 2px solid rgba(255,255,255,0.3);
        backdrop-filter: blur(4px);
    }
    .btn-back-header-purch:hover {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5); color: white;
    }
    .btn-add-purchase-purch {
        background: #059669; color: white;
        padding: 10px 24px; font-weight: 700;
        font-size: 0.9rem; border-radius: 8px;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
    }
    .btn-add-purchase-purch:hover { background: #047857; color: white; }
    .btn-add-purchase-purch.purple { background: #7C3AED; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.35); }
    .btn-add-purchase-purch.purple:hover { background: #6D28D9; }
    .btn-join-purch {
        background: #0B5ED7; color: white;
        padding: 6px 18px; border-radius: 6px;
        font-size: 0.75rem; font-weight: 600;
    }
    .btn-join-purch:hover { background: #0A4CA8; color: white; }
    .btn-join-purch.purple { background: #7C3AED; }
    .btn-join-purch.purple:hover { background: #6D28D9; }
    .btn-delete-purchase-purch {
        background: #DC2626; color: white;
        padding: 6px 14px; border-radius: 6px;
        font-size: 0.75rem; font-weight: 600;
        border: none; cursor: pointer;
        display: inline-flex; align-items: center; gap: 4px;
        transition: all 0.3s ease;
        font-family: inherit;
    }
    .btn-delete-purchase-purch:hover {
        background: #991B1B; color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }
    .btn-complete-purch {
        background: #059669; color: white;
        padding: 10px 24px; border-radius: 8px;
        font-weight: 700; font-size: 0.85rem;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    .btn-complete-purch:hover { background: #047857; color: white; }
    .btn-complete-purch:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
    .btn-cancel-purchase-purch {
        background: #DC2626; color: white;
        padding: 10px 24px; border-radius: 8px;
        font-weight: 700; font-size: 0.85rem;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }
    .btn-cancel-purchase-purch:hover { background: #991B1B; color: white; }
    .btn-delete-item-purch {
        background: #DC2626; color: white; border: none;
        padding: 3px 10px; border-radius: 4px;
        font-size: 0.65rem; cursor: pointer;
    }
    .btn-delete-item-purch:hover { background: #991B1B; }
    .btn-print-purch {
        background: #DC2626; color: white;
        padding: 8px 20px; border-radius: 8px;
        font-weight: 600; font-size: 0.85rem;
    }
    .btn-print-purch:hover { background: #991B1B; color: white; }
    .btn-save-purch {
        background: #059669; color: white;
        padding: 10px 28px; border-radius: 10px;
        font-weight: 600; font-size: 0.9rem;
    }
    .btn-save-purch:hover { background: #047857; color: white; }
    .btn-save-purch.purple { background: #7C3AED; }
    .btn-save-purch.purple:hover { background: #6D28D9; }
    .btn-cancel-purch {
        background: transparent; color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
        padding: 10px 24px; border-radius: 10px;
        font-weight: 600; font-size: 0.9rem;
    }
    .btn-cancel-purch:hover { border-color: #DC2626; color: #DC2626; }
    .btn-generate-purch, .btn-toggle-purch {
        background: #0B5ED7; color: white; border: none;
        border-radius: 10px; padding: 8px 14px;
        font-size: 0.75rem; font-weight: 600;
        cursor: pointer; white-space: nowrap; height: 42px;
        display: inline-flex; align-items: center; gap: 4px;
    }
    .btn-generate-purch:hover, .btn-toggle-purch:hover { background: #0A4CA8; }
    .message-box-purch {
        padding: 12px 18px; border-radius: 10px;
        margin-bottom: 16px; display: flex;
        align-items: center; gap: 10px;
        font-weight: 500; font-size: 0.9rem;
        border-left: 5px solid transparent;
        animation: slideDownPurch 0.4s ease;
    }
    @keyframes slideDownPurch {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .message-box-purch.success { background: #D1FAE5; color: #065F46; border-left-color: #059669; }
    .message-box-purch.error { background: #FEE2E2; color: #991B1B; border-left-color: #DC2626; }
    .message-box-purch.info { background: #E8F0FE; color: #0A4CA8; border-left-color: #0B5ED7; }
    [data-theme="dark"] .message-box-purch.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .message-box-purch.error { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .message-box-purch.info { background: #1E3A5F; color: #6EA8FE; }
    .message-box-purch .message-close {
        margin-left: auto; background: none;
        border: none; cursor: pointer;
        font-size: 1.1rem; color: inherit;
    }
    .card-purch {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 12px; padding: 14px 18px;
        border: 2px solid var(--page-border, #E2E8F0);
        margin-bottom: 20px;
    }
    .card-header-purch {
        display: flex; justify-content: space-between;
        align-items: center; margin-bottom: 10px;
        flex-wrap: wrap; gap: 6px;
    }
    .card-title-purch {
        font-size: 0.9rem; font-weight: 600;
        color: var(--page-text-primary, #1E293B); margin: 0;
    }
    .card-title-purch i.blue { color: #0B5ED7; }
    .card-title-purch i.purple { color: #7C3AED; }
    .card-title-purch i.green { color: #059669; }
    .result-count-purch { font-size: 0.75rem; color: var(--page-text-secondary, #64748B); }
    .result-count-purch strong { color: #0B5ED7; }
    .grid-3-purch {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    .purchase-card-purch {
        background: var(--page-bg-card, #FFFFFF);
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 10px; padding: 14px 16px;
        transition: all 0.3s ease;
    }
    .purchase-card-purch:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    .purchase-card-purch .invoice-number-purch { font-size: 1rem; font-weight: 700; }
    .purchase-card-purch .status-badge-purch {
        padding: 2px 10px; border-radius: 12px;
        font-size: 0.6rem; font-weight: 600;
    }
    .status-badge-purch.in-progress { background: #FEF3C7; color: #D97706; }
    .status-badge-purch.completed { background: #D1FAE5; color: #059669; }
    .status-badge-purch.cancelled { background: #FEE2E2; color: #DC2626; }
    .purchase-card-purch .meta-text-purch {
        font-size: 0.7rem; color: var(--page-text-secondary, #64748B);
    }
    .branch-tag-small-purch {
        display: inline-flex; align-items: center; gap: 3px;
        padding: 1px 8px; border-radius: 10px;
        font-size: 0.6rem; font-weight: 600;
        background: #E8F0FE; color: #0B5ED7;
    }
    [data-theme="dark"] .branch-tag-small-purch { background: #1E3A5F; color: #6EA8FE; }
    .purchase-details-header-purch {
        display: flex; justify-content: space-between;
        align-items: center; flex-wrap: wrap;
        gap: 10px; margin-bottom: 16px;
    }
    .purchase-details-header-purch .invoice-number-purch {
        font-size: 1.4rem; font-weight: 700; color: #0B5ED7;
    }
    .purchase-status-purch {
        padding: 4px 16px; border-radius: 20px;
        font-size: 0.7rem; font-weight: 600;
    }
    .purchase-status-purch.in_progress { background: #FEF3C7; color: #D97706; }
    .purchase-status-purch.completed { background: #D1FAE5; color: #059669; }
    .purchase-status-purch.cancelled { background: #FEE2E2; color: #DC2626; }
    [data-theme="dark"] .purchase-status-purch.in_progress { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .purchase-status-purch.completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .purchase-status-purch.cancelled { background: #3A1A1A; color: #F87171; }
    .purchase-info-grid-purch {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 10px; margin-bottom: 16px;
    }
    .purchase-info-item-purch {
        padding: 8px 12px;
        background: var(--page-hover, #F8FAFC);
        border-radius: 6px;
    }
    [data-theme="dark"] .purchase-info-item-purch { background: #0F172A; }
    .purchase-info-item-purch .label-purch {
        font-size: 0.55rem; text-transform: uppercase;
        color: var(--page-text-secondary, #64748B); font-weight: 600;
    }
    .purchase-info-item-purch .value-purch {
        font-size: 0.85rem; font-weight: 600; margin-top: 2px;
    }
    .form-grid-purch {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }
    .form-grid-purch .full-width { grid-column: 1 / -1; }
    .form-label-purch {
        font-size: 0.75rem; font-weight: 600;
        margin-bottom: 3px; display: block;
        color: var(--page-text-primary, #1E293B);
    }
    .form-label-purch .required { color: #DC2626; }
    .form-control-purch {
        width: 100%; padding: 7px 12px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 8px; font-size: 0.8rem;
        outline: none; background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        height: 42px; font-family: inherit;
    }
    .form-control-purch:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    .form-actions-purch {
        display: flex; gap: 10px; margin-top: 18px;
        padding-top: 14px;
        border-top: 2px solid var(--page-border, #E2E8F0);
        flex-wrap: wrap;
    }
    .category-input-group-purch,
    .unit-input-group-purch,
    .batch-input-group-purch {
        display: flex; gap: 6px; align-items: center;
    }
    .category-input-group-purch .form-control-purch,
    .unit-input-group-purch .form-control-purch,
    .batch-input-group-purch .form-control-purch { flex: 1; }
    .autocomplete-container-purch { position: relative; width: 100%; }
    .autocomplete-list-purch {
        position: absolute; top: 100%; left: 0; right: 0;
        background: var(--page-bg-card, #FFFFFF);
        border: 2px solid var(--page-border, #E2E8F0);
        border-top: none; border-radius: 0 0 8px 8px;
        z-index: 100; max-height: 200px; overflow-y: auto;
        display: none;
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    }
    .autocomplete-list-purch.show { display: block; }
    .autocomplete-item-purch {
        padding: 8px 14px; cursor: pointer;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        font-size: 0.82rem;
        color: var(--page-text-primary, #1E293B);
    }
    .autocomplete-item-purch:hover { background: #E8F0FE; color: #0B5ED7; }
    [data-theme="dark"] .autocomplete-item-purch:hover { background: #1E3A5F; color: #6EA8FE; }
    .autocomplete-item-purch .item-detail-purch {
        font-size: 0.65rem; color: var(--page-text-muted, #94A3B8);
        display: block;
    }
    .table-wrapper-purch { overflow-x: auto; }
    .data-table-purch {
        width: 100%; border-collapse: separate;
        border-spacing: 0; font-size: 0.78rem;
    }
    .data-table-purch thead th {
        background: #0B5ED7; color: white;
        padding: 8px 10px; font-size: 0.6rem;
        text-transform: uppercase; font-weight: 700;
        white-space: nowrap; text-align: left;
    }
    .data-table-purch tbody tr:nth-child(even) { background: #E8F0FE; }
    [data-theme="dark"] .data-table-purch tbody tr:nth-child(even) { background: #1E3A5F; }
    .data-table-purch tbody tr:hover td { background: #D1FAE5; }
    [data-theme="dark"] .data-table-purch tbody tr:hover td { background: #1A3A2A; }
    .data-table-purch td {
        padding: 8px 10px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        vertical-align: middle;
        color: var(--page-text-primary, #1E293B);
    }
    .added-by-tag-purch {
        display: inline-flex; align-items: center; gap: 3px;
        padding: 1px 8px; border-radius: 10px;
        font-size: 0.6rem; font-weight: 600;
        background: #EDE9FE; color: #7C3AED;
    }
    .unit-badge-purch {
        display: inline-flex; align-items: center; gap: 3px;
        padding: 1px 6px; border-radius: 6px;
        font-size: 0.6rem; font-weight: 600;
        background: #CCFBF1; color: #0D9488;
    }
    .empty-state-purch {
        text-align: center; padding: 40px 20px;
        color: var(--page-text-secondary, #64748B);
    }
    .empty-state-purch i {
        font-size: 3rem; color: var(--page-text-muted, #94A3B8);
        display: block; margin-bottom: 12px;
    }
    .empty-state-purch p { font-size: 0.95rem; margin-bottom: 6px; }
    .empty-state-purch .sub { font-size: 0.8rem; }
    .modal-overlay-purch {
        display: none; position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.7); z-index: 9999;
        justify-content: center; align-items: center;
        padding: 20px; backdrop-filter: blur(4px);
    }
    .modal-overlay-purch.show { display: flex; }
    .modal-content-purch {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 12px; max-width: 550px;
        width: 100%; max-height: 90vh; overflow-y: auto;
        padding: 24px 28px;
        border: 2px solid var(--page-border, #E2E8F0);
    }
    .modal-content-purch.modal-pdf { max-width: 900px; background: white; padding: 20px; }
    .modal-header-purch {
        display: flex; justify-content: space-between;
        align-items: center; padding-bottom: 12px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        margin-bottom: 16px;
    }
    .modal-title-purch { font-size: 1.1rem; font-weight: 700; color: #DC2626; }
    .modal-title-purch.blue { color: #0B5ED7; }
    .modal-close-purch {
        background: none; border: none;
        font-size: 1.5rem; cursor: pointer;
        color: var(--page-text-secondary, #64748B);
    }
    .modal-close-purch:hover { color: #DC2626; }
    .modal-actions-purch {
        display: flex; gap: 10px; padding-top: 14px;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 16px; flex-wrap: wrap;
    }
    .btn-confirm-cancel-purch {
        background: #DC2626; color: white;
        padding: 10px 28px; border-radius: 8px;
        font-weight: 600; font-size: 0.9rem;
        border: none; cursor: pointer; flex: 1;
    }
    .btn-confirm-cancel-purch:hover { background: #991B1B; }
    .btn-close-modal-purch {
        background: transparent; color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
        padding: 10px 24px; border-radius: 8px;
        font-weight: 600; font-size: 0.9rem; cursor: pointer;
    }
    .btn-close-modal-purch:hover { border-color: #DC2626; color: #DC2626; }
    .cancel-reason-textarea-purch {
        width: 100%; padding: 10px 14px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 8px; font-size: 0.9rem;
        resize: vertical; min-height: 80px;
        background: var(--page-hover, #F8FAFC);
        color: var(--page-text-primary, #1E293B);
        font-family: inherit;
    }
    .cancel-reason-textarea-purch:focus {
        border-color: #DC2626;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        outline: none;
    }
    .cancel-warning-icon-purch {
        text-align: center; font-size: 3rem;
        color: #DC2626; margin-bottom: 10px;
    }
    .btn-print-invoice-purch {
        background: #0B5ED7; color: white;
        padding: 8px 20px; border-radius: 8px;
        font-weight: 600; font-size: 0.85rem;
        border: none; cursor: pointer;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-print-invoice-purch:hover { background: #0A4CA8; }
    .btn-close-modal-pdf-purch {
        background: transparent; color: #64748B;
        border: 2px solid #E2E8F0;
        padding: 8px 20px; border-radius: 8px;
        font-weight: 600; font-size: 0.85rem; cursor: pointer;
    }
    .btn-close-modal-pdf-purch:hover { border-color: #DC2626; color: #DC2626; }
    .admin-badge-purch {
        background: linear-gradient(135deg, #DC2626, #991B1B);
        color: white; padding: 2px 10px; border-radius: 20px;
        font-size: 0.6rem; font-weight: 700;
        display: inline-flex; align-items: center; gap: 4px;
    }
    .action-bar-purch {
        background: linear-gradient(135deg, #FEF3C7, #FDE68A);
        border: 2px solid #D97706; border-radius: 12px;
        padding: 14px 20px; margin-top: 16px;
        display: flex; justify-content: space-between;
        align-items: center; flex-wrap: wrap; gap: 12px;
    }
    .action-bar-purch .action-info-purch {
        font-size: 0.85rem; color: #92400E; font-weight: 600;
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .action-bar-purch .action-buttons-purch { display: flex; gap: 10px; flex-wrap: wrap; }
    .footer-purch {
        padding: 10px 0;
        border-top: 1px solid var(--page-border, #E2E8F0);
        margin-top: 16px; text-align: center;
        font-size: 0.6rem; color: var(--page-text-secondary, #64748B);
    }
    .footer-purch .footer-brand-purch { color: #0B5ED7; font-weight: 600; }
    input.numeric-only { text-align: left; letter-spacing: 0.5px; }
    .batch-info-purch {
        background: #E8F0FE; border: 1px solid #0B5ED7;
        border-radius: 6px; padding: 6px 10px;
        font-size: 0.7rem; color: #0A4CA8;
        margin-top: 4px;
    }
    .batch-info-purch strong { color: #0B5ED7; }

    /* ✅ V10.1: DISCOUNT SECTION */
    .discount-section-purch {
        background: linear-gradient(135deg, #FEF3C7, #FDE68A);
        border: 3px solid #D97706;
        border-radius: 14px;
        padding: 16px 20px;
        margin: 16px 0;
        box-shadow: 0 4px 16px rgba(217, 119, 6, 0.15);
    }
    .discount-section-purch .discount-title {
        font-size: 0.95rem;
        font-weight: 800;
        color: #92400E;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        flex-wrap: wrap;
    }
    .discount-section-purch .discount-title i {
        font-size: 1.1rem;
        background: #D97706;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .discount-section-purch .discount-subtitle {
        font-size: 0.62rem;
        font-weight: 600;
        text-transform: none;
        color: #92400E;
        margin-left: auto;
        background: rgba(255,255,255,0.5);
        padding: 3px 10px;
        border-radius: 10px;
        border: 1px dashed #D97706;
    }
    .discount-mode-toggle {
        display: inline-flex;
        background: white;
        border-radius: 10px;
        padding: 4px;
        border: 2px solid #D97706;
        margin-bottom: 12px;
        box-shadow: 0 2px 8px rgba(217, 119, 6, 0.15);
    }
    .discount-mode-btn {
        padding: 8px 20px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.8rem;
        border: none;
        cursor: pointer;
        background: transparent;
        color: #92400E;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
        font-family: inherit;
    }
    .discount-mode-btn:hover { background: #FEF3C7; }
    .discount-mode-btn.active {
        background: #D97706;
        color: white;
        box-shadow: 0 3px 10px rgba(217, 119, 6, 0.4);
    }
    .discount-inputs-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        align-items: end;
    }
    .discount-input-group label {
        font-size: 0.7rem;
        font-weight: 700;
        color: #92400E;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        display: block;
        margin-bottom: 4px;
    }
    .discount-input-group .input-wrapper { position: relative; }
    .discount-input-group .currency-prefix,
    .discount-input-group .percent-suffix {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        font-weight: 800;
        color: #D97706;
        font-size: 0.85rem;
        pointer-events: none;
        z-index: 1;
    }
    .discount-input-group .currency-prefix { left: 12px; }
    .discount-input-group .percent-suffix { right: 12px; }
    .discount-input-group input {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid #D97706;
        border-radius: 10px;
        font-size: 1rem;
        font-weight: 700;
        color: #92400E;
        background: white;
        outline: none;
        height: 46px;
        font-family: 'JetBrains Mono', monospace;
    }
    .discount-input-group input.amount-mode { padding-left: 38px; }
    .discount-input-group input.percent-mode { padding-right: 38px; }
    .discount-input-group input:focus {
        border-color: #92400E;
        box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.2);
    }
    .discount-preview {
        background: white;
        border: 2px dashed #D97706;
        border-radius: 10px;
        padding: 10px 14px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        height: 46px;
        justify-content: center;
    }
    .discount-preview .preview-label {
        font-size: 0.6rem;
        font-weight: 700;
        color: #92400E;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .discount-preview .preview-value {
        font-size: 1.05rem;
        font-weight: 900;
        color: #DC2626;
        font-family: 'JetBrains Mono', monospace;
    }
    .discount-actions {
        display: flex;
        gap: 10px;
        margin-top: 14px;
        padding-top: 12px;
        border-top: 2px dashed rgba(217, 119, 6, 0.4);
        flex-wrap: wrap;
    }
    .discount-total-summary {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-top: 14px;
        padding-top: 14px;
        border-top: 2px solid #D97706;
    }
    .discount-total-summary .summary-box {
        background: white;
        border-radius: 10px;
        padding: 10px 14px;
        border: 2px solid #E2E8F0;
    }
    .discount-total-summary .summary-box.buying { border-color: #DC2626; background: #FEE2E2; }
    .discount-total-summary .summary-box.discount { border-color: #D97706; background: #FEF3C7; }
    .discount-total-summary .summary-box.final { border-color: #059669; background: #D1FAE5; }
    .discount-total-summary .summary-label {
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .discount-total-summary .summary-value {
        font-size: 1.1rem;
        font-weight: 900;
        margin-top: 2px;
        font-family: 'JetBrains Mono', monospace;
    }
    .discount-total-summary .summary-box.buying .summary-label { color: #DC2626; }
    .discount-total-summary .summary-box.buying .summary-value { color: #DC2626; }
    .discount-total-summary .summary-box.discount .summary-label { color: #D97706; }
    .discount-total-summary .summary-box.discount .summary-value { color: #D97706; }
    .discount-total-summary .summary-box.final .summary-label { color: #059669; }
    .discount-total-summary .summary-box.final .summary-value { color: #059669; }
    .discount-badge-on-purchase {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        background: linear-gradient(135deg, #FEF3C7, #FDE68A);
        color: #92400E;
        border: 1.5px solid #D97706;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        margin-left: 8px;
    }

    @media (max-width: 1024px) {
        .grid-3-purch { grid-template-columns: 1fr 1fr; }
        .discount-inputs-grid { grid-template-columns: 1fr; }
        .discount-total-summary { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
        .grid-3-purch { grid-template-columns: 1fr; }
        .form-grid-purch { grid-template-columns: 1fr; }
        .form-grid-purch .full-width { grid-column: 1; }
        .page-header-box-purch .page-title-purch { font-size: 1.1rem; }
        .header-actions-purch { width: 100%; }
        .data-table-purch { min-width: 900px; }
        .btn-back-header-purch { width: 100%; justify-content: center; }
        .action-bar-purch { flex-direction: column; align-items: stretch; }
        .action-bar-purch .action-buttons-purch { width: 100%; }
        .action-bar-purch .action-buttons-purch button { width: 100%; justify-content: center; }
        .discount-mode-toggle { width: 100%; }
        .discount-mode-btn { flex: 1; justify-content: center; padding: 8px 10px; font-size: 0.7rem; }
    }
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-box-purch <?= $active_view === 'equipment' ? 'equipment-mode' : '' ?>">
        <div>
            <h1 class="page-title-purch">
                <?php if ($active_view === 'medicine'): ?>
                    <i class="fas fa-pills"></i> Medicine Purchases
                <?php elseif ($active_view === 'equipment'): ?>
                    <i class="fas fa-tools"></i> Equipment Purchases
                <?php elseif ($purchase_id > 0 && $current_purchase): ?>
                    <i class="fas fa-shopping-cart"></i> <?= htmlspecialchars($current_purchase['invoice_number']) ?>
                <?php else: ?>
                    <i class="fas fa-shopping-cart"></i> Admin Purchases
                <?php endif; ?>
                <span class="role-badge-display">ADMIN</span>
                <span class="branch-name-display">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($display_branch_name) ?>
                </span>
            </h1>
            <p class="page-subtitle-purch">
                <?php if ($purchase_id > 0 && $current_purchase): ?>
                    <strong><?= $current_purchase['total_items'] ?></strong> items · 
                    <strong><?= number_format($current_purchase['total_quantity']) ?></strong> units
                    <?php if (!$is_creator): ?>
                        <span class="admin-badge-purch"><i class="fas fa-shield-alt"></i> ADMIN MODE</span>
                    <?php endif; ?>
                <?php elseif ($active_view === 'medicine'): ?>
                    <span class="header-badge-purch"><i class="fas fa-pills"></i> <?= count($all_med_in_progress) ?> MEDICINE in progress</span>
                <?php elseif ($active_view === 'equipment'): ?>
                    <span class="header-badge-purch"><i class="fas fa-tools"></i> <?= count($all_equip_in_progress) ?> EQUIPMENT in progress</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="header-actions-purch">
            <?php if ($purchase_id > 0 && $current_purchase): ?>
                <a href="purchases.php?action=list_<?= $current_purchase['purchase_type'] ?>&branch=<?= $selected_branch_id ?>" class="btn-purch btn-back-header-purch">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            <?php elseif ($active_view !== 'none'): ?>
                <a href="inventory.php?tab=<?= $active_view === 'medicine' ? 'medicines' : 'equipment' ?>&branch=<?= $selected_branch_id ?>" class="btn-purch btn-back-header-purch">
                    <i class="fas fa-arrow-left"></i> Back to Inventory
                </a>
            <?php endif; ?>

            <?php if ($active_view === 'medicine' && !$purchase_id): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="create_purchase">
                    <input type="hidden" name="purchase_type" value="medicine">
                    <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                    <button type="submit" class="btn-purch btn-add-purchase-purch">
                        <i class="fas fa-plus-circle"></i> New Medicine Purchase
                    </button>
                </form>
            <?php elseif ($active_view === 'equipment' && !$purchase_id): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="create_purchase">
                    <input type="hidden" name="purchase_type" value="equipment">
                    <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                    <button type="submit" class="btn-purch btn-add-purchase-purch purple">
                        <i class="fas fa-plus-circle"></i> New Equipment Purchase
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="message-box-purch <?= $message_type ?>" id="messageBox">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'info' ? 'fa-info-circle' : 'fa-exclamation-circle') ?>"></i>
            <span><?= $message ?></span>
            <button class="message-close" onclick="closeMessage()">&times;</button>
        </div>
    <?php endif; ?>

    <!-- MEDICINE IN PROGRESS -->
    <?php if ($active_view === 'medicine' && !$purchase_id): ?>
        <div class="card-purch">
            <div class="card-header-purch">
                <h3 class="card-title-purch">
                    <i class="fas fa-pills blue"></i> 
                    Medicine Purchases In Progress
                    <span class="result-count-purch">(<strong><?= count($all_med_in_progress) ?></strong> available)</span>
                </h3>
            </div>

            <?php if (count($all_med_in_progress) > 0): ?>
                <div class="grid-3-purch">
                    <?php foreach ($all_med_in_progress as $purchase): ?>
                        <div class="purchase-card-purch" style="border-color:#D97706;">
                            <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:4px;">
                                <div>
                                    <div class="invoice-number-purch" style="color:#D97706;">
                                        <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($purchase['invoice_number']) ?>
                                    </div>
                                    <div class="meta-text-purch" style="margin-top:4px;">
                                        <i class="fas fa-user"></i> <?= htmlspecialchars($purchase['creator_name'] ?? 'Unknown') ?>
                                        <?php if ($purchase['created_by'] == $user_id): ?>
                                            <span style="color:#059669;font-weight:600;"> (You)</span>
                                        <?php else: ?>
                                            <span class="admin-badge-purch" style="font-size:0.5rem;padding:0 6px;">
                                                <i class="fas fa-shield-alt"></i> OTHER
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="meta-text-purch">
                                        <i class="fas fa-store-alt"></i> 
                                        <span class="branch-tag-small-purch"><?= htmlspecialchars($purchase['branch_name'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="meta-text-purch">
                                        <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?>
                                    </div>
                                </div>
                                <span class="status-badge-purch in-progress">
                                    <i class="fas fa-spinner fa-spin"></i> IN PROGRESS
                                </span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;flex-wrap:wrap;gap:4px;">
                                <div class="meta-text-purch">
                                    <i class="fas fa-boxes"></i> <?= number_format($purchase['total_items']) ?> items
                                </div>
                                <div style="display:flex;gap:4px;">
                                    <a href="purchases.php?id=<?= $purchase['id'] ?>&type=medicine&branch=<?= $selected_branch_id ?>" class="btn-purch btn-join-purch">
                                        <i class="fas fa-sign-in-alt"></i> Open
                                    </a>
                                    <button type="button" class="btn-delete-purchase-purch" 
                                            onclick="openDeletePurchaseModal(<?= $purchase['id'] ?>, '<?= addslashes($purchase['invoice_number']) ?>')"
                                            title="Delete this purchase">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state-purch">
                    <i class="fas fa-pills"></i>
                    <p>No medicine purchases in progress</p>
                    <p class="sub">Click "New Medicine Purchase" above to start one.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- EQUIPMENT IN PROGRESS -->
    <?php if ($active_view === 'equipment' && !$purchase_id): ?>
        <div class="card-purch">
            <div class="card-header-purch">
                <h3 class="card-title-purch">
                    <i class="fas fa-tools purple"></i> 
                    Equipment Purchases In Progress
                    <span class="result-count-purch">(<strong><?= count($all_equip_in_progress) ?></strong> available)</span>
                </h3>
            </div>

            <?php if (count($all_equip_in_progress) > 0): ?>
                <div class="grid-3-purch">
                    <?php foreach ($all_equip_in_progress as $purchase): ?>
                        <div class="purchase-card-purch" style="border-color:#7C3AED;">
                            <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:4px;">
                                <div>
                                    <div class="invoice-number-purch" style="color:#7C3AED;">
                                        <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($purchase['invoice_number']) ?>
                                    </div>
                                    <div class="meta-text-purch" style="margin-top:4px;">
                                        <i class="fas fa-user"></i> <?= htmlspecialchars($purchase['creator_name'] ?? 'Unknown') ?>
                                        <?php if ($purchase['created_by'] == $user_id): ?>
                                            <span style="color:#059669;font-weight:600;"> (You)</span>
                                        <?php else: ?>
                                            <span class="admin-badge-purch" style="font-size:0.5rem;padding:0 6px;">
                                                <i class="fas fa-shield-alt"></i> OTHER
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="meta-text-purch">
                                        <i class="fas fa-store-alt"></i> 
                                        <span class="branch-tag-small-purch"><?= htmlspecialchars($purchase['branch_name'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="meta-text-purch">
                                        <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?>
                                    </div>
                                </div>
                                <span class="status-badge-purch in-progress">
                                    <i class="fas fa-spinner fa-spin"></i> IN PROGRESS
                                </span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;flex-wrap:wrap;gap:4px;">
                                <div class="meta-text-purch">
                                    <i class="fas fa-boxes"></i> <?= number_format($purchase['total_items']) ?> items
                                </div>
                                <div style="display:flex;gap:4px;">
                                    <a href="purchases.php?id=<?= $purchase['id'] ?>&type=equipment&branch=<?= $selected_branch_id ?>" class="btn-purch btn-join-purch purple">
                                        <i class="fas fa-sign-in-alt"></i> Open
                                    </a>
                                    <button type="button" class="btn-delete-purchase-purch" 
                                            onclick="openDeletePurchaseModal(<?= $purchase['id'] ?>, '<?= addslashes($purchase['invoice_number']) ?>')"
                                            title="Delete this purchase">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state-purch">
                    <i class="fas fa-tools"></i>
                    <p>No equipment purchases in progress</p>
                    <p class="sub">Click "New Equipment Purchase" above to start one.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- VIEW SPECIFIC PURCHASE -->
    <?php if ($purchase_id && $current_purchase): ?>
        <div class="card-purch">
            <div class="purchase-details-header-purch">
                <div>
                    <div class="invoice-number-purch">
                        <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($current_purchase['invoice_number']) ?>
                        <span style="font-size:0.65rem;font-weight:400;color:var(--page-text-secondary);margin-left:8px;">
                            <?= ucfirst($current_purchase['purchase_type']) ?>
                        </span>
                        <?php if (($current_purchase['global_discount_amount'] ?? 0) > 0): ?>
                            <span class="discount-badge-on-purchase">
                                <i class="fas fa-percent"></i>
                                Discount: 
                                <?php if (($current_purchase['global_discount_type'] ?? 'amount') === 'percent'): ?>
                                    <?= number_format($current_purchase['global_discount_value'], 2) ?>%
                                <?php else: ?>
                                    TSh <?= number_format($current_purchase['global_discount_value']) ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:0.7rem;color:var(--page-text-secondary);margin-top:2px;">
                        Created: <?= date('d/m/Y H:i', strtotime($current_purchase['created_at'])) ?>
                        <?php if ($current_purchase['completed_at']): ?>
                            | Completed: <?= date('d/m/Y H:i', strtotime($current_purchase['completed_at'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span class="purchase-status-purch <?= strtolower($current_purchase['status']) ?>">
                        <i class="fas <?= $current_purchase['status'] === 'IN_PROGRESS' ? 'fa-spinner fa-spin' : ($current_purchase['status'] === 'COMPLETED' ? 'fa-check-circle' : 'fa-times-circle') ?>"></i>
                        <?= $current_purchase['status'] ?>
                    </span>
                    <?php if ($is_creator): ?>
                        <span style="font-size:0.6rem;color:#059669;background:#D1FAE5;padding:2px 10px;border-radius:12px;">
                            <i class="fas fa-crown"></i> Creator
                        </span>
                    <?php else: ?>
                        <span class="admin-badge-purch">
                            <i class="fas fa-shield-alt"></i> ADMIN MODE
                        </span>
                    <?php endif; ?>
                    <?php if ($current_purchase['status'] === 'COMPLETED'): ?>
                        <button onclick="openPDFView(<?= $purchase_id ?>)" class="btn-purch btn-print-purch">
                            <i class="fas fa-file-pdf"></i> PDF Invoice
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php 
                // ✅ V10.1: Discount-aware amounts
                $total_buying = (float)($current_purchase['total_buying_cost'] ?? 0);
                $final_buying = (float)($current_purchase['final_total_buying_cost'] ?? 0);
                if ($final_buying == 0 && ($current_purchase['global_discount_amount'] ?? 0) == 0) $final_buying = $total_buying;
                $total_selling = (float)($current_purchase['total_selling_value'] ?? 0);
                $discount_amt = (float)($current_purchase['global_discount_amount'] ?? 0);
                $profit = $total_selling - $final_buying;
            ?>
            <div class="purchase-info-grid-purch">
                <div class="purchase-info-item-purch">
                    <div class="label-purch">Created By</div>
                    <div class="value-purch">
                        <i class="fas fa-user" style="color:#0B5ED7;"></i>
                        <?= htmlspecialchars($current_purchase['creator_name'] ?? 'Unknown') ?>
                    </div>
                </div>
                <div class="purchase-info-item-purch">
                    <div class="label-purch">Total Items</div>
                    <div class="value-purch">
                        <i class="fas fa-boxes" style="color:#D97706;"></i>
                        <?= number_format($current_purchase['total_items']) ?> items
                    </div>
                </div>
                <div class="purchase-info-item-purch">
                    <div class="label-purch">Total Quantity</div>
                    <div class="value-purch">
                        <i class="fas fa-cubes" style="color:#0D9488;"></i>
                        <?= number_format($current_purchase['total_quantity']) ?> units
                    </div>
                </div>
                <div class="purchase-info-item-purch" style="background:#FEE2E2;border:2px solid #DC2626;">
                    <div class="label-purch" style="color:#DC2626;">💰 Buying Cost (Before)</div>
                    <div class="value-purch" style="color:#DC2626;">
                        TSh <?= number_format($total_buying) ?>
                    </div>
                </div>
                <?php if ($discount_amt > 0): ?>
                <div class="purchase-info-item-purch" style="background:#FEF3C7;border:2px solid #D97706;">
                    <div class="label-purch" style="color:#D97706;">🎁 Discount</div>
                    <div class="value-purch" style="color:#D97706;">
                        -TSh <?= number_format($discount_amt) ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="purchase-info-item-purch" style="background:#FECACA;border:2px solid #991B1B;">
                    <div class="label-purch" style="color:#991B1B;">💰 Final Buying Cost</div>
                    <div class="value-purch" style="color:#991B1B;">
                        TSh <?= number_format($final_buying) ?>
                    </div>
                </div>
                <div class="purchase-info-item-purch" style="background:#E8F0FE;border:2px solid #0B5ED7;">
                    <div class="label-purch" style="color:#0B5ED7;">💰 Selling Value</div>
                    <div class="value-purch" style="color:#0B5ED7;">
                        TSh <?= number_format($total_selling) ?>
                    </div>
                </div>
                <div class="purchase-info-item-purch" style="background:<?= $profit >= 0 ? '#D1FAE5' : '#FEE2E2' ?>;border:2px solid <?= $profit >= 0 ? '#059669' : '#DC2626' ?>;">
                    <div class="label-purch" style="color:<?= $profit >= 0 ? '#059669' : '#DC2626' ?>;">📈 Expected Profit</div>
                    <div class="value-purch" style="color:<?= $profit >= 0 ? '#059669' : '#DC2626' ?>;">
                        TSh <?= number_format($profit) ?>
                    </div>
                </div>
            </div>

            <!-- ✅ V10.1: DISCOUNT SECTION -->
            <?php if ($current_purchase['status'] === 'IN_PROGRESS'): ?>
            <div class="discount-section-purch">
                <div class="discount-title">
                    <i class="fas fa-percent"></i>
                    Global Discount
                    <span class="discount-subtitle">
                        <i class="fas fa-info-circle"></i> Inapunguza BUYING COST (sio selling)
                    </span>
                </div>
                
                <?php 
                    $disc_type = $current_purchase['global_discount_type'] ?? 'amount';
                    $disc_value = $current_purchase['global_discount_value'] ?? 0;
                    $disc_amount = $current_purchase['global_discount_amount'] ?? 0;
                    $subtotal_buying = $current_purchase['total_buying_cost'] ?? 0;
                    $final_buying_display = $current_purchase['final_total_buying_cost'] ?? $subtotal_buying;
                ?>
                
                <form method="POST" id="discountForm">
                    <input type="hidden" name="action" value="update_global_discount">
                    <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
                    <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                    <input type="hidden" name="global_discount_type" id="discountTypeInput" value="<?= htmlspecialchars($disc_type) ?>">
                    
                    <div class="discount-mode-toggle">
                        <button type="button" class="discount-mode-btn <?= $disc_type === 'amount' ? 'active' : '' ?>" 
                                onclick="setDiscountMode('amount')" id="btnModeAmount">
                            <i class="fas fa-money-bill-wave"></i> Amount (TSh)
                        </button>
                        <button type="button" class="discount-mode-btn <?= $disc_type === 'percent' ? 'active' : '' ?>" 
                                onclick="setDiscountMode('percent')" id="btnModePercent">
                            <i class="fas fa-percent"></i> Percent (%)
                        </button>
                    </div>
                    
                    <div class="discount-inputs-grid">
                        <div class="discount-input-group">
                            <label id="discountInputLabel">
                                <?= $disc_type === 'percent' ? 'Discount Percent (%)' : 'Discount Amount (TSh)' ?>
                            </label>
                            <div class="input-wrapper">
                                <span class="currency-prefix" id="discountPrefix" style="<?= $disc_type === 'percent' ? 'display:none;' : '' ?>">TSh</span>
                                <input type="text" name="global_discount_value" id="discountValueInput" 
                                       class="money-input <?= $disc_type === 'percent' ? 'percent-mode' : 'amount-mode' ?>"
                                       value="<?= $disc_type === 'percent' ? number_format($disc_value, 2, '.', '') : number_format($disc_value, 0, '.', ',') ?>"
                                       placeholder="0" 
                                       oninput="updateDiscountPreview()">
                                <span class="percent-suffix" id="discountSuffix" style="<?= $disc_type === 'amount' ? 'display:none;' : '' ?>">%</span>
                            </div>
                        </div>
                        
                        <div class="discount-input-group">
                            <label>Discount Amount (Auto)</label>
                            <div class="discount-preview">
                                <span class="preview-label">Itakayopunguza buying</span>
                                <span class="preview-value" id="discountPreviewAmount">-TSh <?= number_format($disc_amount) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="discount-total-summary">
                        <div class="summary-box buying">
                            <div class="summary-label">Buying Cost (Before)</div>
                            <div class="summary-value">TSh <span id="summarySubtotal"><?= number_format($subtotal_buying) ?></span></div>
                        </div>
                        <div class="summary-box discount">
                            <div class="summary-label">Discount</div>
                            <div class="summary-value">-TSh <span id="summaryDiscount"><?= number_format($disc_amount) ?></span></div>
                        </div>
                        <div class="summary-box final">
                            <div class="summary-label">Final Buying Cost</div>
                            <div class="summary-value">TSh <span id="summaryFinal"><?= number_format($final_buying_display) ?></span></div>
                        </div>
                    </div>
                    
                    <div class="discount-actions">
                        <button type="submit" class="btn-purch btn-save-purch" style="background:#D97706;">
                            <i class="fas fa-save"></i> Apply Discount
                        </button>
                        <?php if ($disc_value > 0): ?>
                        <button type="button" class="btn-purch btn-cancel-purch" onclick="clearDiscount()">
                            <i class="fas fa-times"></i> Clear Discount
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- ACTION BAR -->
            <?php if ($current_purchase['status'] === 'IN_PROGRESS'): ?>
                <div class="action-bar-purch">
                    <div class="action-info-purch">
                        <i class="fas fa-info-circle"></i>
                        <?php if ($is_creator): ?>
                            This purchase has <strong><?= count($purchase_items) ?></strong> item(s). Click to complete or cancel.
                        <?php else: ?>
                            <span class="admin-badge-purch" style="font-size:0.6rem;">
                                <i class="fas fa-shield-alt"></i> ADMIN MODE
                            </span>
                            Viewing purchase by <strong><?= htmlspecialchars($current_purchase['creator_name'] ?? 'another user') ?></strong>.
                        <?php endif; ?>
                    </div>
                    <div class="action-buttons-purch">
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Complete this purchase? Batches zote zitaundwa kwenye inventory na unit_cost itakuwa baada ya discount.');">
                            <input type="hidden" name="action" value="complete_purchase">
                            <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
                            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                            <button type="submit" class="btn-purch btn-complete-purch" <?= count($purchase_items) == 0 ? 'disabled' : '' ?>>
                                <i class="fas fa-check-circle"></i> Complete Purchase
                            </button>
                        </form>

                        <button type="button" class="btn-purch btn-cancel-purchase-purch" onclick="openCancelModal(<?= $purchase_id ?>, '<?= htmlspecialchars($current_purchase['invoice_number']) ?>')">
                            <i class="fas fa-times-circle"></i> Cancel Purchase
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ADD MEDICINE FORM -->
            <?php if ($current_purchase['purchase_type'] === 'medicine' && $current_purchase['status'] === 'IN_PROGRESS'): ?>
                <div class="card-purch" style="margin-top:12px;border-color:#059669;">
                    <div class="card-header-purch">
                        <h4 class="card-title-purch" style="font-size:0.85rem;">
                            <i class="fas fa-plus-circle green"></i> Add Medicine Batch
                        </h4>
                    </div>

                    <form method="POST" id="addMedicineForm">
                        <input type="hidden" name="action" value="add_medicine">
                        <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
                        <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">

                        <div class="form-grid-purch">
                            <div class="full-width">
                                <label class="form-label-purch">Medicine Name <span class="required">*</span></label>
                                <div class="autocomplete-container-purch">
                                    <input type="text" name="medication_name" id="purchaseMedicineName" class="form-control-purch" 
                                           placeholder="Type medicine name..." required autocomplete="off">
                                    <input type="hidden" name="medicine_id" id="purchaseMedicineId" value="">
                                    <div class="autocomplete-list-purch" id="purchaseMedicineAutocomplete"></div>
                                </div>
                                <div class="batch-info-purch" id="medicineBatchInfo" style="display:none;">
                                    <i class="fas fa-info-circle"></i> 
                                    Dawa hii ina <strong id="existingBatchesCount">1</strong> batch(es) tayari kwenye inventory. 
                                    Unaongeza batch mpya.
                                </div>
                            </div>

                            <div>
                                <label class="form-label-purch">Category <span class="required">*</span></label>
                                <div class="category-input-group-purch">
                                    <select name="category" id="purchaseCategorySelect" class="form-control-purch" required>
                                        <option value="">Select</option>
                                        <?php foreach ($predefined_med_categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                        <?php endforeach; ?>
                                        <option value="__other__">+ Other</option>
                                    </select>
                                    <input type="text" name="category_manual" id="purchaseCategoryManual" class="form-control-purch" placeholder="Custom..." style="display:none;">
                                    <button type="button" class="btn-purch btn-toggle-purch" onclick="toggleCategory('med')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="form-label-purch">Unit <span class="required">*</span></label>
                                <div class="unit-input-group-purch">
                                    <select name="unit" id="purchaseUnit" class="form-control-purch" required>
                                        <option value="">Select Unit</option>
                                        <?php foreach ($predefined_units as $unit_key => $unit_label): ?>
                                            <option value="<?= htmlspecialchars($unit_key) ?>"><?= htmlspecialchars($unit_label) ?></option>
                                        <?php endforeach; ?>
                                        <option value="__other__">+ Other (Manual)</option>
                                    </select>
                                    <input type="text" name="unit_manual" id="purchaseUnitManual" class="form-control-purch" placeholder="Custom unit..." style="display:none;">
                                    <button type="button" class="btn-purch btn-toggle-purch" onclick="toggleUnit('med')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="form-label-purch">Quantity <span class="required">*</span></label>
                                <input type="text" name="quantity" id="purchaseQuantity" class="form-control-purch numeric-only" 
                                       placeholder="0" inputmode="numeric" pattern="[0-9]*" required autocomplete="off">
                            </div>

                            <div>
                                <label class="form-label-purch">Reorder Level</label>
                                <input type="text" name="reorder_level" id="purchaseReorderLevel" class="form-control-purch numeric-only" 
                                       value="10" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                            </div>

                            <div>
                                <label class="form-label-purch">Buying Price (TSh) <span class="required">*</span></label>
                                <input type="text" name="buying_price" id="purchaseBuyingPrice" class="form-control-purch money-input" value="0" required autocomplete="off">
                            </div>

                            <div>
                                <label class="form-label-purch">Selling Price (TSh) <span class="required">*</span></label>
                                <input type="text" name="selling_price" id="purchaseUnitPrice" class="form-control-purch money-input" value="0" required autocomplete="off">
                            </div>

                            <div>
                                <label class="form-label-purch">Supplier</label>
                                <input type="text" name="supplier" id="purchaseSupplier" class="form-control-purch" placeholder="Supplier">
                            </div>

                            <div>
                                <label class="form-label-purch">Expiry Date <span class="required">*</span></label>
                                <input type="date" name="expiry_date" id="purchaseExpiryDate" class="form-control-purch" required>
                            </div>

                            <div class="full-width">
                                <label class="form-label-purch">Batch Number</label>
                                <div class="batch-input-group-purch">
                                    <input type="text" name="batch_number" id="purchaseBatchInput" class="form-control-purch" 
                                           value="<?= 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)) ?>">
                                    <button type="button" class="btn-purch btn-generate-purch" onclick="generateBatch('med')">
                                        <i class="fas fa-sync-alt"></i> Generate
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="form-label-purch">Status</label>
                                <select name="status" id="purchaseStatus" class="form-control-purch">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions-purch">
                            <button type="submit" class="btn-purch btn-save-purch">
                                <i class="fas fa-plus-circle"></i> Add Medicine Batch
                            </button>
                            <button type="reset" class="btn-purch btn-cancel-purch">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- ADD EQUIPMENT FORM -->
            <?php if ($current_purchase['purchase_type'] === 'equipment' && $current_purchase['status'] === 'IN_PROGRESS'): ?>
                <div class="card-purch" style="margin-top:12px;border-color:#7C3AED;">
                    <div class="card-header-purch">
                        <h4 class="card-title-purch" style="font-size:0.85rem;">
                            <i class="fas fa-plus-circle purple"></i> Add Equipment Batch
                        </h4>
                    </div>

                    <form method="POST" id="addEquipmentForm">
                        <input type="hidden" name="action" value="add_equipment">
                        <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
                        <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">

                        <div class="form-grid-purch">
                            <div class="full-width">
                                <label class="form-label-purch">Equipment Name <span class="required">*</span></label>
                                <div class="autocomplete-container-purch">
                                    <input type="text" name="equipment_name" id="purchaseEquipmentName" class="form-control-purch" 
                                           placeholder="Type equipment name..." required autocomplete="off">
                                    <input type="hidden" name="equipment_id" id="purchaseEquipmentId" value="">
                                    <div class="autocomplete-list-purch" id="purchaseEquipmentAutocomplete"></div>
                                </div>
                            </div>

                            <div>
                                <label class="form-label-purch">Category <span class="required">*</span></label>
                                <div class="category-input-group-purch">
                                    <select name="category" id="purchaseEquipCategorySelect" class="form-control-purch" required>
                                        <option value="">Select</option>
                                        <?php foreach ($predefined_equip_categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                        <?php endforeach; ?>
                                        <option value="__other__">+ Other</option>
                                    </select>
                                    <input type="text" name="category_manual" id="purchaseEquipCategoryManual" class="form-control-purch" placeholder="Custom..." style="display:none;">
                                    <button type="button" class="btn-purch btn-toggle-purch" onclick="toggleCategory('equip')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="form-label-purch">Unit <span class="required">*</span></label>
                                <div class="unit-input-group-purch">
                                    <select name="unit" id="purchaseEquipUnit" class="form-control-purch" required>
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
                                    <input type="text" name="unit_manual" id="purchaseEquipUnitManual" class="form-control-purch" placeholder="Custom unit..." style="display:none;">
                                    <button type="button" class="btn-purch btn-toggle-purch" onclick="toggleUnit('equip')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="form-label-purch">Quantity <span class="required">*</span></label>
                                <input type="text" name="quantity" id="purchaseEquipQuantity" class="form-control-purch numeric-only" 
                                       placeholder="0" inputmode="numeric" pattern="[0-9]*" required autocomplete="off">
                            </div>

                            <div>
                                <label class="form-label-purch">Reorder Level</label>
                                <input type="text" name="reorder_level" id="purchaseEquipReorderLevel" class="form-control-purch numeric-only" 
                                       value="5" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                            </div>

                            <div>
                                <label class="form-label-purch">Buying Price (TSh) <span class="required">*</span></label>
                                <input type="text" name="buying_price" id="purchaseEquipBuyingPrice" class="form-control-purch money-input" value="0" required autocomplete="off">
                            </div>

                            <div>
                                <label class="form-label-purch">Selling Price (TSh) <span class="required">*</span></label>
                                <input type="text" name="selling_price" id="purchaseEquipSellingPrice" class="form-control-purch money-input" value="0" required autocomplete="off">
                            </div>

                            <div>
                                <label class="form-label-purch">Supplier</label>
                                <input type="text" name="supplier" id="purchaseEquipSupplier" class="form-control-purch" placeholder="Supplier">
                            </div>

                            <div>
                                <label class="form-label-purch">Expiry Date (Optional)</label>
                                <input type="date" name="expiry_date" id="purchaseEquipExpiryDate" class="form-control-purch">
                            </div>

                            <div class="full-width">
                                <label class="form-label-purch">Batch Number</label>
                                <div class="batch-input-group-purch">
                                    <input type="text" name="batch_number" id="purchaseEquipBatchInput" class="form-control-purch" 
                                           value="<?= 'EQP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)) ?>">
                                    <button type="button" class="btn-purch btn-generate-purch" onclick="generateBatch('equip')">
                                        <i class="fas fa-sync-alt"></i> Generate
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="form-label-purch">Status</label>
                                <select name="status" id="purchaseEquipStatus" class="form-control-purch">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions-purch">
                            <button type="submit" class="btn-purch btn-save-purch purple">
                                <i class="fas fa-plus-circle"></i> Add Equipment Batch
                            </button>
                            <button type="reset" class="btn-purch btn-cancel-purch">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- ITEMS TABLE -->
            <div style="margin-top:16px;">
                <div class="card-header-purch">
                    <h4 class="card-title-purch">
                        <i class="fas fa-list blue"></i> Items (Batches) 
                        <span class="result-count-purch">(<strong><?= count($purchase_items) ?></strong> items)</span>
                    </h4>
                </div>

                <?php if (count($purchase_items) > 0): ?>
                    <div class="table-wrapper-purch">
                        <table class="data-table-purch">
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
                                            <span class="unit-badge-purch">
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
                                            <span class="added-by-tag-purch">
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
                                                    <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                                                    <button type="submit" class="btn-delete-item-purch" title="Remove">
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
                    <div class="empty-state-purch">
                        <i class="fas fa-box-open"></i>
                        <p>No items added yet</p>
                        <p class="sub">Use the form above to add items.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="footer-purch">
        <p>
            <span class="footer-brand-purch">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Admin Purchases V10.1
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<!-- DELETE PURCHASE MODAL -->
<div class="modal-overlay-purch" id="deletePurchaseModal">
    <div class="modal-content-purch">
        <div class="modal-header-purch">
            <div class="modal-title-purch">
                <i class="fas fa-trash-alt"></i> Delete Purchase
            </div>
            <button class="modal-close-purch" onclick="closeDeletePurchaseModal()">&times;</button>
        </div>

        <div class="cancel-warning-icon-purch" style="color:#DC2626;">
            <i class="fas fa-trash-alt"></i>
        </div>

        <p style="text-align:center;color:var(--page-text-primary);font-weight:600;margin-bottom:8px;">
            Are you sure you want to <span style="color:#DC2626;">DELETE</span> this purchase?
        </p>
        <p style="text-align:center;color:var(--page-text-secondary);font-size:0.85rem;margin-bottom:16px;">
            <strong id="deleteInvoiceNumber"></strong>
        </p>

        <div style="background:#FEE2E2;border:2px solid #DC2626;border-radius:8px;padding:12px 14px;margin-bottom:14px;font-size:0.82rem;color:#991B1B;">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>WARNING - This cannot be undone!</strong>
            <ul style="margin-top:6px;padding-left:20px;line-height:1.5;">
                <li>Purchase record <strong>itafutwa kabisa</strong></li>
                <li>Items zote za purchase hii <strong>zitafutwa</strong></li>
                <li>Inventory stock <strong>HAIATHIRIKI</strong></li>
            </ul>
        </div>

        <form method="POST" id="deletePurchaseForm">
            <input type="hidden" name="action" value="delete_purchase">
            <input type="hidden" name="purchase_id" id="deletePurchaseId" value="">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">

            <div class="modal-actions-purch">
                <button type="submit" class="btn-confirm-cancel-purch" style="background:#DC2626;">
                    <i class="fas fa-trash-alt"></i> Yes, Delete Permanently
                </button>
                <button type="button" class="btn-close-modal-purch" onclick="closeDeletePurchaseModal()">
                    <i class="fas fa-times"></i> No, Go Back
                </button>
            </div>
        </form>
    </div>
</div>

<!-- CANCEL MODAL -->
<div class="modal-overlay-purch" id="cancelModal">
    <div class="modal-content-purch">
        <div class="modal-header-purch">
            <div class="modal-title-purch">
                <i class="fas fa-exclamation-triangle"></i> Cancel Purchase
            </div>
            <button class="modal-close-purch" onclick="closeCancelModal()">&times;</button>
        </div>

        <div class="cancel-warning-icon-purch">
            <i class="fas fa-exclamation-circle"></i>
        </div>

        <p style="text-align:center;color:var(--page-text-primary);font-weight:500;margin-bottom:8px;">
            Are you sure you want to cancel this purchase?
        </p>
        <p style="text-align:center;color:var(--page-text-secondary);font-size:0.85rem;margin-bottom:16px;">
            <strong id="cancelInvoiceNumber"></strong>
        </p>

        <div style="background:#FEE2E2;border:2px solid #DC2626;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:0.8rem;color:#991B1B;">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>WARNING:</strong> All items will be permanently deleted. Inventory will NOT be affected.
        </div>

        <form method="POST" id="cancelForm">
            <input type="hidden" name="action" value="cancel_purchase">
            <input type="hidden" name="purchase_id" id="cancelPurchaseId" value="">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">

            <div style="margin-bottom:8px;">
                <label class="form-label-purch">
                    Reason for Cancellation 
                    <span style="color:#64748B;font-weight:400;font-size:0.7rem;">(Optional - Admin)</span>
                </label>
                <textarea name="cancel_reason" id="cancelReason" class="cancel-reason-textarea-purch" 
                          placeholder="Explain why (optional - unaweza kuacha wazi)..." rows="3"></textarea>
                <div style="font-size:0.65rem;color:#64748B;margin-top:4px;">
                    <i class="fas fa-info-circle"></i> 
                    Unaweza kuacha wazi — mfumo utaweka "Cancelled by Admin" kiotomatiki.
                </div>
            </div>

            <div class="modal-actions-purch">
                <button type="submit" class="btn-confirm-cancel-purch">
                    <i class="fas fa-times-circle"></i> Yes, Cancel Purchase
                </button>
                <button type="button" class="btn-close-modal-purch" onclick="closeCancelModal()">
                    <i class="fas fa-times"></i> No, Go Back
                </button>
            </div>
        </form>
    </div>
</div>

<!-- PDF MODAL -->
<div class="modal-overlay-purch" id="pdfModal">
    <div class="modal-content-purch modal-pdf">
        <div class="modal-header-purch">
            <div class="modal-title-purch blue">
                <i class="fas fa-file-pdf" style="color:#DC2626;"></i> Purchase Invoice
            </div>
            <button class="modal-close-purch" onclick="closePDF()">&times;</button>
        </div>

        <div id="pdfInvoiceContent" style="background:white;padding:10px;max-height:70vh;overflow-y:auto;">
            <div style="text-align:center;padding:30px;color:#64748B;">
                <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i>
                <p>Loading invoice...</p>
            </div>
        </div>

        <div class="modal-actions-purch">
            <button class="btn-purch btn-print-invoice-purch" onclick="printPDFInvoice()">
                <i class="fas fa-print"></i> Print Invoice
            </button>
            <button class="btn-purch btn-close-modal-pdf-purch" onclick="closePDF()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<script>
    // ================================================================
    // ✅ V10.1: DISCOUNT STATE
    // ================================================================
    var subtotalBuying = <?= isset($subtotal_buying) ? (float)$subtotal_buying : 0 ?>;
    var currentDiscountType = '<?= isset($disc_type) ? $disc_type : 'amount' ?>';

    function setDiscountMode(mode) {
        currentDiscountType = mode;
        var typeInput = document.getElementById('discountTypeInput');
        if (typeInput) typeInput.value = mode;
        
        var btnAmount = document.getElementById('btnModeAmount');
        var btnPercent = document.getElementById('btnModePercent');
        var input = document.getElementById('discountValueInput');
        var label = document.getElementById('discountInputLabel');
        var prefix = document.getElementById('discountPrefix');
        var suffix = document.getElementById('discountSuffix');
        
        if (!input) return;
        
        if (mode === 'amount') {
            if (btnAmount) btnAmount.classList.add('active');
            if (btnPercent) btnPercent.classList.remove('active');
            input.classList.remove('percent-mode');
            input.classList.add('amount-mode');
            if (label) label.textContent = 'Discount Amount (TSh)';
            if (prefix) prefix.style.display = '';
            if (suffix) suffix.style.display = 'none';
            input.placeholder = '0';
        } else {
            if (btnAmount) btnAmount.classList.remove('active');
            if (btnPercent) btnPercent.classList.add('active');
            input.classList.remove('amount-mode');
            input.classList.add('percent-mode');
            if (label) label.textContent = 'Discount Percent (%)';
            if (prefix) prefix.style.display = 'none';
            if (suffix) suffix.style.display = '';
            input.placeholder = '0.00';
            input.value = '0';
        }
        
        updateDiscountPreview();
    }

    function updateDiscountPreview() {
        var input = document.getElementById('discountValueInput');
        if (!input) return;
        
        var rawVal = input.value.replace(/,/g, '').replace(/[^0-9.]/g, '');
        var value = parseFloat(rawVal) || 0;
        
        // ✅ Discount inapunguza BUYING COST
        var discountAmount = 0;
        if (currentDiscountType === 'percent') {
            value = Math.min(Math.max(value, 0), 100);
            discountAmount = subtotalBuying * (value / 100);
        } else {
            value = Math.max(value, 0);
            discountAmount = Math.min(value, subtotalBuying);
        }
        
        var finalBuying = Math.max(subtotalBuying - discountAmount, 0);
        
        var previewEl = document.getElementById('discountPreviewAmount');
        var summaryDiscount = document.getElementById('summaryDiscount');
        var summaryFinal = document.getElementById('summaryFinal');
        var summarySubtotal = document.getElementById('summarySubtotal');
        
        if (previewEl) previewEl.textContent = '-TSh ' + Math.round(discountAmount).toLocaleString();
        if (summaryDiscount) summaryDiscount.textContent = Math.round(discountAmount).toLocaleString();
        if (summaryFinal) summaryFinal.textContent = Math.round(finalBuying).toLocaleString();
        if (summarySubtotal) summarySubtotal.textContent = Math.round(subtotalBuying).toLocaleString();
    }

    function clearDiscount() {
        if (!confirm('Clear the discount?')) return;
        var input = document.getElementById('discountValueInput');
        if (input) input.value = '0';
        var form = document.getElementById('discountForm');
        if (form) form.submit();
    }

    // ================================================================
    // FOOTER TIME
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    // ================================================================
    // ZUIA SCROLL WHEEL KUBADILISHA NUMBER INPUTS
    // ================================================================
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

    // ================================================================
    // HELPERS
    // ================================================================
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

    // ================================================================
    // DELETE PURCHASE MODAL
    // ================================================================
    function openDeletePurchaseModal(purchaseId, invoiceNumber) {
        var modal = document.getElementById('deletePurchaseModal');
        if (!modal) return;

        document.getElementById('deletePurchaseId').value = purchaseId;
        document.getElementById('deleteInvoiceNumber').textContent = 'Invoice: ' + invoiceNumber;

        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeDeletePurchaseModal() {
        var modal = document.getElementById('deletePurchaseModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    }

    document.getElementById('deletePurchaseModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeDeletePurchaseModal();
    });

    document.getElementById('deletePurchaseForm')?.addEventListener('submit', function(e) {
        if (!confirm('⚠️ Are you ABSOLUTELY SURE? This will permanently delete the purchase and all its items. This cannot be undone!')) {
            e.preventDefault();
            return false;
        }
    });

    // ================================================================
    // CANCEL MODAL
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

    document.getElementById('cancelModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeCancelModal();
    });

    document.getElementById('cancelForm')?.addEventListener('submit', function(e) {
        if (!confirm('⚠️ Are you sure? All items will be permanently deleted!')) {
            e.preventDefault();
            return false;
        }
    });

    // ================================================================
    // PDF FUNCTIONS
    // ================================================================
    function openPDFView(purchaseId) {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfInvoiceContent');

        modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        content.innerHTML = '<div style="text-align:center;padding:30px;color:#64748B;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i><p>Loading...</p></div>';

        fetch('get_invoice.php?id=' + purchaseId)
            .then(function(r) { return r.text(); })
            .then(function(html) { content.innerHTML = html; })
            .catch(function(error) {
                content.innerHTML = '<div style="text-align:center;padding:30px;color:#DC2626;"><i class="fas fa-exclamation-circle" style="font-size:2rem;"></i><p>Error: ' + error.message + '</p></div>';
            });
    }

    function closePDF() {
        var modal = document.getElementById('pdfModal');
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    document.getElementById('pdfModal')?.addEventListener('click', function(e) {
        if (e.target === this) closePDF();
    });

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
            closeCancelModal();
            closeDeletePurchaseModal();
            closePDF();
        }
    });

    // ================================================================
    // MONEY FORMATTING
    // ================================================================
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
                    if (this.id === 'discountValueInput' && currentDiscountType === 'percent') {
                        updateDiscountPreview();
                        return;
                    }

                    var cursorPos = this.selectionStart;
                    var before = this.value.length;
                    var formatted = formatWithCommas(this.value);
                    if (formatted !== this.value) {
                        this.value = formatted;
                        var diff = formatted.length - before;
                        this.setSelectionRange(cursorPos + diff, cursorPos + diff);
                    }

                    if (this.id === 'discountValueInput') updateDiscountPreview();
                });

                input.addEventListener('focus', function() {
                    this.value = this.value.replace(/,/g, '');
                    this.select();
                });

                input.addEventListener('blur', function() {
                    if (this.value) {
                        if (this.id === 'discountValueInput' && currentDiscountType === 'percent') {
                            var v = parseFloat(this.value.replace(/[^0-9.]/g, '')) || 0;
                            this.value = v.toFixed(2);
                        } else {
                            this.value = formatWithCommas(this.value);
                        }
                    } else {
                        this.value = '0';
                    }
                });
            });
        }

        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initMoneyInputs);
        else initMoneyInputs();

        new MutationObserver(function() { setTimeout(initMoneyInputs, 100); }).observe(document.body, { childList: true, subtree: true });
    })();

    // ================================================================
    // AUTOCOMPLETE - MEDICINE
    // ================================================================
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
                html += '<div class="autocomplete-item-purch" ' +
                    'data-id="' + item.id + '" ' +
                    'data-name="' + escapeHtml(item.medication_name) + '" ' +
                    'data-category="' + escapeHtml(item.category || '') + '" ' +
                    'data-unit="' + escapeHtml(item.unit || 'pcs') + '" ' +
                    'data-cost="' + (item.unit_cost || 0) + '" ' +
                    'data-price="' + (item.selling_price || 0) + '" ' +
                    'data-reorder="' + (item.reorder_level || 10) + '" ' +
                    'data-supplier="' + escapeHtml(item.supplier || '') + '">' +
                    '<strong>' + escapeHtml(item.medication_name) + '</strong>' +
                    '<span class="item-detail-purch">' +
                        'Category: ' + escapeHtml(item.category || 'N/A') + 
                        ' | Unit: ' + escapeHtml(item.unit || 'pcs') + 
                        ' | Buy: TSh ' + Number(item.unit_cost || 0).toLocaleString() + 
                        ' | Sell: TSh ' + Number(item.selling_price || 0).toLocaleString() +
                    '</span>' +
                    '</div>';
            });

            autocomplete.innerHTML = html;
            autocomplete.classList.add('show');

            autocomplete.querySelectorAll('.autocomplete-item-purch').forEach(function(item) {
                item.addEventListener('click', function() {
                    input.value = this.dataset.name;
                    if (medicineIdInput) medicineIdInput.value = this.dataset.id;
                    autocomplete.classList.remove('show');

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

                    if (document.getElementById('purchaseBuyingPrice')) 
                        document.getElementById('purchaseBuyingPrice').value = Number(this.dataset.cost || 0).toLocaleString();
                    if (document.getElementById('purchaseUnitPrice')) 
                        document.getElementById('purchaseUnitPrice').value = Number(this.dataset.price || 0).toLocaleString();
                    if (document.getElementById('purchaseReorderLevel')) 
                        document.getElementById('purchaseReorderLevel').value = this.dataset.reorder || 10;
                    if (document.getElementById('purchaseSupplier')) 
                        document.getElementById('purchaseSupplier').value = this.dataset.supplier || '';

                    if (batchInfoDiv && batchesCountSpan) {
                        batchInfoDiv.style.display = 'block';
                        batchesCountSpan.textContent = '1+';
                    }

                    var qtyInput = document.getElementById('purchaseQuantity');
                    if (qtyInput) qtyInput.focus();
                });
            });
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.autocomplete-container-purch')) autocomplete.classList.remove('show');
        });
    })();

    // ================================================================
    // AUTOCOMPLETE - EQUIPMENT
    // ================================================================
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
                html += '<div class="autocomplete-item-purch" ' +
                    'data-id="' + item.id + '" ' +
                    'data-name="' + escapeHtml(item.equipment_name) + '" ' +
                    'data-category="' + escapeHtml(item.category || '') + '" ' +
                    'data-unit="' + escapeHtml(item.unit || 'pcs') + '" ' +
                    'data-cost="' + (item.unit_cost || 0) + '" ' +
                    'data-price="' + (item.selling_price || 0) + '" ' +
                    'data-reorder="' + (item.reorder_level || 5) + '" ' +
                    'data-supplier="' + escapeHtml(item.supplier || '') + '">' +
                    '<strong>' + escapeHtml(item.equipment_name) + '</strong>' +
                    '<span class="item-detail-purch">' +
                        'Category: ' + escapeHtml(item.category || 'N/A') + 
                        ' | Unit: ' + escapeHtml(item.unit || 'pcs') + 
                        ' | Buy: TSh ' + Number(item.unit_cost || 0).toLocaleString() + 
                        ' | Sell: TSh ' + Number(item.selling_price || 0).toLocaleString() +
                    '</span>' +
                    '</div>';
            });

            autocomplete.innerHTML = html;
            autocomplete.classList.add('show');

            autocomplete.querySelectorAll('.autocomplete-item-purch').forEach(function(item) {
                item.addEventListener('click', function() {
                    input.value = this.dataset.name;
                    if (equipmentIdInput) equipmentIdInput.value = this.dataset.id;
                    autocomplete.classList.remove('show');

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
            if (!e.target.closest('.autocomplete-container-purch')) autocomplete.classList.remove('show');
        });
    })();

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    console.log('%c💊 Braick - Admin Purchases V10.1', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ V10.1: DISCOUNT INAPUNGUZA BUYING COST (sio selling)', 'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c✅ V10.1: Amount (default) au Percent', 'font-size:13px; color:#34D399;');
    console.log('%c✅ V10.1: Inventory unit_cost = baada ya discount', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Cancel reason OPTIONAL kwa admin', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Kila IN_PROGRESS invoice ina button ya DELETE', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Kila purchase inaunda NEW BATCH', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>