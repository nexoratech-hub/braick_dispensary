<?php
// ================================================================
// FILE: frontend/pages/admin/inventory.php
// ADMIN - COMPLETE INVENTORY (Medicine & Equipment)
// ✅ EMBEDDED HEADER (same as shared admin_header.php)
// ✅ Uses SHARED admin_sidebar.php
// ✅ Branch filter inafanya kazi vizuri
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

// ================================================================
// BRANCH FILTER
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($selected_branch_id !== 'all') {
    if (!is_numeric($selected_branch_id)) {
        $selected_branch_id = 'all';
    } else {
        $selected_branch_id = (int)$selected_branch_id;
        try {
            require_once __DIR__ . '/../../../backend/config/database.php';
            $db_check = Database::getInstance()->getConnection();
            $stmt = $db_check->prepare("SELECT id FROM branches WHERE id = ? AND status = 'active'");
            $stmt->execute([$selected_branch_id]);
            if (!$stmt->fetch()) {
                $selected_branch_id = 'all';
            }
        } catch (Exception $e) {
            $selected_branch_id = 'all';
        }
    }
}

$filter_by_branch = false;
$filter_branch_id = 0;
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $filter_by_branch = true;
    $filter_branch_id = (int)$selected_branch_id;
}

$manage_branch = $filter_by_branch ? $filter_branch_id : $user_branch_id;

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
// UNIQUE INVOICE GENERATOR
// ================================================================
function generateUniqueInvoiceNumber($db, $prefix, $date) {
    $pattern = $prefix . '-' . $date . '-%';
    $stmt = $db->prepare("SELECT invoice_number FROM purchases WHERE invoice_number LIKE ?");
    $stmt->execute([$pattern]);
    $existing_invoices = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $used_numbers = [];
    foreach ($existing_invoices as $inv) {
        if (preg_match('/-(\d+)$/', $inv, $matches)) {
            $used_numbers[(int)$matches[1]] = true;
        }
    }
    
    for ($i = 1; $i <= 9999; $i++) {
        if (!isset($used_numbers[$i])) {
            $candidate = $prefix . '-' . $date . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("SELECT COUNT(*) as c FROM purchases WHERE invoice_number = ?");
            $stmt->execute([$candidate]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)['c'] == 0) {
                return $candidate;
            }
        }
    }
    
    return $prefix . '-' . $date . '-' . substr((string)microtime(true), -6);
}

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
    'pcs' => 'Pieces (pcs)',
    'tablets' => 'Tablets',
    'capsules' => 'Capsules',
    'ml' => 'Milliliters (ml)',
    'mg' => 'Milligrams (mg)',
    'g' => 'Grams (g)',
    'bottle' => 'Bottle',
    'box' => 'Box',
    'strip' => 'Strip',
    'vial' => 'Vial',
    'ampoule' => 'Ampoule',
    'sachet' => 'Sachet',
    'tube' => 'Tube',
    'pack' => 'Pack',
    'set' => 'Set',
    'roll' => 'Roll',
    'pair' => 'Pair',
    'kit' => 'Kit',
    'unit' => 'Unit',
    'dozen' => 'Dozen',
    'litre' => 'Litre (L)',
    'bag' => 'Bag',
    'carton' => 'Carton'
];

$message = '';
$message_type = '';

// ================================================================
// HANDLE POST ACTIONS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // CREATE NEW PURCHASE
    if ($action === 'create_new_purchase') {
        $purchase_type = $_POST['purchase_type'] ?? 'medicine';
        $target_branch = (int)($_POST['target_branch'] ?? $manage_branch);
        
        if ($target_branch <= 0) $target_branch = $user_branch_id;
        
        $date = date('Ymd');
        $prefix = $purchase_type === 'medicine' ? 'INV-MED' : 'INV-EQP';
        $invoice_number = generateUniqueInvoiceNumber($db, $prefix, $date);
        
        $max_attempts = 10;
        $inserted = false;
        $new_purchase_id = null;
        
        for ($attempt = 0; $attempt < $max_attempts && !$inserted; $attempt++) {
            try {
                $stmt = $db->prepare("INSERT INTO purchases (invoice_number, purchase_type, created_by, created_by_name, branch_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'IN_PROGRESS', NOW())");
                $stmt->execute([$invoice_number, $purchase_type, $user_id, $user_full_name, $target_branch]);
                $new_purchase_id = $db->lastInsertId();
                $inserted = true;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    usleep(100000);
                    $invoice_number = generateUniqueInvoiceNumber($db, $prefix, $date);
                } else {
                    break;
                }
            }
        }
        
        if ($inserted && $new_purchase_id) {
            $_SESSION['purchase_message'] = "✅ New " . ucfirst($purchase_type) . " purchase created! Invoice: <strong>$invoice_number</strong>";
            $_SESSION['purchase_message_type'] = 'success';
            header('Location: purchases.php?id=' . $new_purchase_id . '&type=' . $purchase_type);
            exit;
        } else {
            $_SESSION['inventory_message'] = "❌ Failed to create purchase. Please try again.";
            $_SESSION['inventory_message_type'] = 'error';
            header('Location: inventory.php?branch=' . $selected_branch_id);
            exit;
        }
    }
    
    // EDIT MEDICINE
    if ($action === 'edit_medicine') {
        $id = (int)($_POST['id'] ?? 0);
        $medication_name = trim($_POST['medication_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        if ($category === '__other__' && !empty($_POST['category_manual'])) $category = trim($_POST['category_manual']);
        if (empty($category)) $category = 'Other';
        
        $unit = trim($_POST['unit'] ?? 'pcs');
        if (empty($unit) && !empty($_POST['unit_manual'])) {
            $unit = trim($_POST['unit_manual']);
        }
        if (empty($unit)) $unit = 'pcs';
        
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reorder_level = (int)($_POST['reorder_level'] ?? 10);
        $unit_cost = getMoney($_POST['unit_cost'] ?? 0);
        $selling_price = getMoney($_POST['selling_price'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');
        $expiry_date = $_POST['expiry_date'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        $errors = [];
        if (empty($medication_name)) $errors[] = 'Medicine name is required';
        if ($quantity < 0) $errors[] = 'Quantity cannot be negative';
        if ($selling_price < 0) $errors[] = 'Selling price cannot be negative';
        if (!empty($expiry_date) && strtotime($expiry_date) < strtotime(date('Y-m-d'))) $errors[] = 'Expiry date cannot be in the past';
        
        if (empty($errors) && $id > 0) {
            try {
                $stmt = $db->prepare("UPDATE medications_inventory SET medication_name = ?, category = ?, unit = ?, quantity = ?, reorder_level = ?, unit_cost = ?, selling_price = ?, supplier = ?, expiry_date = ?, batch_number = ?, status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$medication_name, $category, $unit, $quantity, $reorder_level, $unit_cost, $selling_price, $supplier, $expiry_date, $batch_number, $status, $id]);
                $_SESSION['inventory_message'] = "✅ Medicine batch updated successfully!";
                $_SESSION['inventory_message_type'] = 'success';
                header('Location: inventory.php?tab=medicines&updated=1&branch=' . $selected_branch_id);
                exit;
            } catch (Exception $e) { $message = "❌ Error: " . $e->getMessage(); $message_type = 'error'; }
        } else { $message = implode('<br>', $errors); $message_type = 'error'; }
    }
    
    // EDIT EQUIPMENT
    if ($action === 'edit_equipment') {
        $id = (int)($_POST['id'] ?? 0);
        $equipment_name = trim($_POST['equipment_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        if ($category === '__other__' && !empty($_POST['category_manual'])) $category = trim($_POST['category_manual']);
        if (empty($category)) $category = 'Other';
        
        $unit = trim($_POST['unit'] ?? 'pcs');
        if (empty($unit) && !empty($_POST['unit_manual'])) {
            $unit = trim($_POST['unit_manual']);
        }
        if (empty($unit)) $unit = 'pcs';
        
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reorder_level = (int)($_POST['reorder_level'] ?? 5);
        $unit_cost = getMoney($_POST['unit_cost'] ?? 0);
        $selling_price = getMoney($_POST['selling_price'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');
        $expiry_date = $_POST['expiry_date'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        $errors = [];
        if (empty($equipment_name)) $errors[] = 'Equipment name is required';
        if ($quantity < 0) $errors[] = 'Quantity cannot be negative';
        if ($selling_price < 0) $errors[] = 'Selling price cannot be negative';
        if (!empty($expiry_date) && strtotime($expiry_date) < strtotime(date('Y-m-d'))) $errors[] = 'Expiry date cannot be in the past';
        
        if (empty($errors) && $id > 0) {
            try {
                $stmt = $db->prepare("UPDATE medical_equipment SET equipment_name = ?, category = ?, unit = ?, quantity = ?, reorder_level = ?, unit_cost = ?, selling_price = ?, supplier = ?, expiry_date = ?, batch_number = ?, status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$equipment_name, $category, $unit, $quantity, $reorder_level, $unit_cost, $selling_price, $supplier, $expiry_date, $batch_number, $status, $id]);
                $_SESSION['inventory_message'] = "✅ Equipment batch updated successfully!";
                $_SESSION['inventory_message_type'] = 'success';
                header('Location: inventory.php?tab=equipment&updated=1&branch=' . $selected_branch_id);
                exit;
            } catch (Exception $e) { $message = "❌ Error: " . $e->getMessage(); $message_type = 'error'; }
        } else { $message = implode('<br>', $errors); $message_type = 'error'; }
    }
    
    // DELETE MEDICINE
    if ($action === 'delete_medicine') {
        $id = (int)($_POST['id'] ?? 0);
        $confirmed = isset($_POST['confirmed']) ? (int)$_POST['confirmed'] : 0;
        if ($confirmed == 1 && $id > 0) {
            try {
                $stmt = $db->prepare("DELETE FROM medications_inventory WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['inventory_message'] = "✅ Medicine batch deleted successfully!";
                $_SESSION['inventory_message_type'] = 'success';
                header('Location: inventory.php?tab=medicines&deleted=1&branch=' . $selected_branch_id);
                exit;
            } catch (Exception $e) { $message = "❌ Error: " . $e->getMessage(); $message_type = 'error'; }
        }
    }
    
    // DELETE EQUIPMENT
    if ($action === 'delete_equipment') {
        $id = (int)($_POST['id'] ?? 0);
        $confirmed = isset($_POST['confirmed']) ? (int)$_POST['confirmed'] : 0;
        if ($confirmed == 1 && $id > 0) {
            try {
                $stmt = $db->prepare("DELETE FROM medical_equipment WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['inventory_message'] = "✅ Equipment batch deleted successfully!";
                $_SESSION['inventory_message_type'] = 'success';
                header('Location: inventory.php?tab=equipment&deleted=1&branch=' . $selected_branch_id);
                exit;
            } catch (Exception $e) { $message = "❌ Error: " . $e->getMessage(); $message_type = 'error'; }
        }
    }
    
    // GET EDIT MEDICINE DATA
    if ($action === 'get_edit_medicine_data') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM medications_inventory WHERE id = ?");
            $stmt->execute([$id]);
            $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($edit_data) {
                $category_val = $edit_data['category'] ?? '';
                $is_custom_category = !in_array($category_val, $predefined_med_categories) && !empty($category_val) && $category_val !== 'Other';
                $unit_val = $edit_data['unit'] ?? 'pcs';
                $is_custom_unit = !array_key_exists($unit_val, $predefined_units);
                ?>
                <form method="POST" action="" class="edit-form">
                    <input type="hidden" name="action" value="edit_medicine">
                    <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                    <div class="form-grid">
                        <div class="full-width form-row">
                            <label class="form-label">Medicine Name <span class="required">*</span></label>
                            <input type="text" name="medication_name" class="form-control" value="<?= htmlspecialchars($edit_data['medication_name']) ?>" required>
                        </div>
                        <div class="form-row">
                            <label class="form-label">Category <span class="required">*</span></label>
                            <div class="category-input-group">
                                <select name="category" id="editCategorySelect" class="form-control" required>
                                    <option value="">Select</option>
                                    <?php foreach ($predefined_med_categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category_val == $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                                    <?php endforeach; ?>
                                    <option value="__other__" <?= $is_custom_category ? 'selected' : '' ?>>+ Other</option>
                                </select>
                                <input type="text" name="category_manual" id="editCategoryManual" class="form-control" placeholder="Custom..." style="display:<?= $is_custom_category ? 'block' : 'none' ?>;" value="<?= $is_custom_category ? htmlspecialchars($category_val) : '' ?>">
                                <button type="button" class="btn-toggle" onclick="toggleCategoryEdit()"><i class="fas fa-edit"></i> Manual</button>
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label">Unit <span class="required">*</span></label>
                            <div class="unit-input-group">
                                <select name="unit" id="editUnitSelect" class="form-control" required>
                                    <option value="">Select Unit</option>
                                    <?php foreach ($predefined_units as $unit_key => $unit_label): ?>
                                        <option value="<?= htmlspecialchars($unit_key) ?>" <?= $unit_val == $unit_key ? 'selected' : '' ?>><?= htmlspecialchars($unit_label) ?></option>
                                    <?php endforeach; ?>
                                    <option value="__other__" <?= $is_custom_unit ? 'selected' : '' ?>>+ Other (Manual)</option>
                                </select>
                                <input type="text" name="unit_manual" id="editUnitManual" class="form-control" placeholder="Custom unit..." style="display:<?= $is_custom_unit ? 'block' : 'none' ?>;" value="<?= $is_custom_unit ? htmlspecialchars($unit_val) : '' ?>">
                                <button type="button" class="btn-toggle" onclick="toggleUnitEdit()"><i class="fas fa-edit"></i> Manual</button>
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label">Quantity <span class="required">*</span></label>
                            <input type="number" name="quantity" class="form-control" value="<?= $edit_data['quantity'] ?>" min="0" required>
                        </div>
                        <div class="form-row">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" name="reorder_level" class="form-control" value="<?= $edit_data['reorder_level'] ?>" min="0">
                        </div>
                        <div class="form-row">
                            <label class="form-label">Buying Price</label>
                            <input type="text" name="unit_cost" class="form-control money-input" value="<?= number_format($edit_data['unit_cost'] ?? 0, 0) ?>">
                        </div>
                        <div class="form-row">
                            <label class="form-label">Selling Price <span class="required">*</span></label>
                            <input type="text" name="selling_price" class="form-control money-input" value="<?= number_format($edit_data['selling_price'] ?? 0, 0) ?>" required>
                        </div>
                        <div class="form-row">
                            <label class="form-label">Supplier</label>
                            <input type="text" name="supplier" class="form-control" value="<?= htmlspecialchars($edit_data['supplier'] ?? '') ?>">
                        </div>
                        <div class="form-row">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control" value="<?= $edit_data['expiry_date'] && $edit_data['expiry_date'] !== '0000-00-00' ? $edit_data['expiry_date'] : '' ?>">
                        </div>
                        <div class="full-width form-row">
                            <label class="form-label">Batch Number</label>
                            <input type="text" name="batch_number" class="form-control" value="<?= htmlspecialchars($edit_data['batch_number'] ?? '') ?>">
                        </div>
                        <div class="form-row">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="active" <?= $edit_data['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $edit_data['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Update Batch</button>
                        <button type="button" class="btn-cancel" onclick="closeModal('editModal')"><i class="fas fa-times"></i> Cancel</button>
                    </div>
                </form>
                <?php exit;
            }
        }
        exit;
    }
    
    // GET EDIT EQUIPMENT DATA
    if ($action === 'get_edit_equipment_data') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM medical_equipment WHERE id = ?");
            $stmt->execute([$id]);
            $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($edit_data) {
                $category_val = $edit_data['category'] ?? '';
                $is_custom_category = !in_array($category_val, $predefined_equip_categories) && !empty($category_val) && $category_val !== 'Other';
                $unit_val = $edit_data['unit'] ?? 'pcs';
                $is_custom_unit = !array_key_exists($unit_val, $predefined_units);
                ?>
                <form method="POST" action="" class="edit-form">
                    <input type="hidden" name="action" value="edit_equipment">
                    <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                    <div class="form-grid">
                        <div class="full-width form-row">
                            <label class="form-label">Equipment Name <span class="required">*</span></label>
                            <input type="text" name="equipment_name" class="form-control" value="<?= htmlspecialchars($edit_data['equipment_name']) ?>" required>
                        </div>
                        <div class="form-row">
                            <label class="form-label">Category <span class="required">*</span></label>
                            <div class="category-input-group">
                                <select name="category" id="editEquipCategorySelect" class="form-control" required>
                                    <option value="">Select</option>
                                    <?php foreach ($predefined_equip_categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category_val == $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                                    <?php endforeach; ?>
                                    <option value="__other__" <?= $is_custom_category ? 'selected' : '' ?>>+ Other</option>
                                </select>
                                <input type="text" name="category_manual" id="editEquipCategoryManual" class="form-control" placeholder="Custom..." style="display:<?= $is_custom_category ? 'block' : 'none' ?>;" value="<?= $is_custom_category ? htmlspecialchars($category_val) : '' ?>">
                                <button type="button" class="btn-toggle" onclick="toggleEquipCategoryEdit()"><i class="fas fa-edit"></i> Manual</button>
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label">Unit <span class="required">*</span></label>
                            <div class="unit-input-group">
                                <select name="unit" id="editEquipUnitSelect" class="form-control" required>
                                    <option value="">Select Unit</option>
                                    <?php foreach ($predefined_units as $unit_key => $unit_label): ?>
                                        <option value="<?= htmlspecialchars($unit_key) ?>" <?= $unit_val == $unit_key ? 'selected' : '' ?>><?= htmlspecialchars($unit_label) ?></option>
                                    <?php endforeach; ?>
                                    <option value="__other__" <?= $is_custom_unit ? 'selected' : '' ?>>+ Other (Manual)</option>
                                </select>
                                <input type="text" name="unit_manual" id="editEquipUnitManual" class="form-control" placeholder="Custom unit..." style="display:<?= $is_custom_unit ? 'block' : 'none' ?>;" value="<?= $is_custom_unit ? htmlspecialchars($unit_val) : '' ?>">
                                <button type="button" class="btn-toggle" onclick="toggleEquipUnitEdit()"><i class="fas fa-edit"></i> Manual</button>
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label">Quantity <span class="required">*</span></label>
                            <input type="number" name="quantity" class="form-control" value="<?= $edit_data['quantity'] ?>" min="0" required>
                        </div>
                        <div class="form-row">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" name="reorder_level" class="form-control" value="<?= $edit_data['reorder_level'] ?>" min="0">
                        </div>
                        <div class="form-row">
                            <label class="form-label">Buying Price</label>
                            <input type="text" name="unit_cost" class="form-control money-input" value="<?= number_format($edit_data['unit_cost'] ?? 0, 0) ?>">
                        </div>
                        <div class="form-row">
                            <label class="form-label">Selling Price <span class="required">*</span></label>
                            <input type="text" name="selling_price" class="form-control money-input" value="<?= number_format($edit_data['selling_price'] ?? 0, 0) ?>" required>
                        </div>
                        <div class="form-row">
                            <label class="form-label">Supplier</label>
                            <input type="text" name="supplier" class="form-control" value="<?= htmlspecialchars($edit_data['supplier'] ?? '') ?>">
                        </div>
                        <div class="form-row">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control" value="<?= $edit_data['expiry_date'] && $edit_data['expiry_date'] !== '0000-00-00' ? $edit_data['expiry_date'] : '' ?>">
                        </div>
                        <div class="full-width form-row">
                            <label class="form-label">Batch Number</label>
                            <input type="text" name="batch_number" class="form-control" value="<?= htmlspecialchars($edit_data['batch_number'] ?? '') ?>">
                        </div>
                        <div class="form-row">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="active" <?= $edit_data['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $edit_data['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Update Batch</button>
                        <button type="button" class="btn-cancel" onclick="closeModal('editModal')"><i class="fas fa-times"></i> Cancel</button>
                    </div>
                </form>
                <?php exit;
            }
        }
        exit;
    }
}

if (isset($_SESSION['inventory_message'])) {
    $message = $_SESSION['inventory_message'];
    $message_type = $_SESSION['inventory_message_type'] ?? 'success';
    unset($_SESSION['inventory_message']);
    unset($_SESSION['inventory_message_type']);
}

// ================================================================
// HANDLE "MANAGE" PAGE
// ================================================================
$show_manage_purchases = false;
$manage_type = 'medicine';
$manage_branch_name = 'Branch';
$manage_purchases = [];

if (isset($_GET['manage']) && in_array($_GET['manage'], ['medicine', 'equipment'])) {
    $show_manage_purchases = true;
    $manage_type = $_GET['manage'];
    
    if (isset($_GET['target_branch']) && is_numeric($_GET['target_branch'])) {
        $manage_branch = (int)$_GET['target_branch'];
    } else {
        $manage_branch = $filter_by_branch ? $filter_branch_id : $user_branch_id;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT p.id, p.invoice_number, p.purchase_type, p.created_by, p.created_by_name,
                   p.total_items, p.total_quantity, p.total_buying_cost, p.total_selling_value,
                   p.created_at, u.full_name as creator_full_name, b.name as branch_name
            FROM purchases p
            LEFT JOIN users u ON p.created_by = u.id
            LEFT JOIN branches b ON p.branch_id = b.id
            WHERE p.status = 'IN_PROGRESS' 
            AND p.purchase_type = ? 
            AND p.branch_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$manage_type, $manage_branch]);
        $manage_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Manage query error: " . $e->getMessage());
    }
    
    try {
        $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
        $stmt->execute([$manage_branch]);
        $b_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($b_row) $manage_branch_name = $b_row['name'];
        else $manage_branch_name = 'Branch';
    } catch (Exception $e) { $manage_branch_name = 'Branch'; }
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'medicines';
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_type = isset($_GET['type']) ? $_GET['type'] : 'medicine';

$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$stock_filter = isset($_GET['stock']) ? trim($_GET['stock']) : '';
$expiry_filter = isset($_GET['expiry']) ? trim($_GET['expiry']) : '';

$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $branches = []; }

// ================================================================
// MEDICINES QUERY
// ================================================================
$med_query = "
    SELECT 
        MIN(m.id) as id, m.medication_name, m.category, m.unit, m.branch_id, m.added_by, m.added_by_name,
        u.full_name as added_by_full_name, b.name as branch_name,
        SUM(CASE WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE() OR m.expiry_date = '0000-00-00') THEN m.quantity ELSE 0 END) as total_quantity,
        MIN(m.reorder_level) as reorder_level, MIN(m.unit_cost) as unit_cost, MIN(m.selling_price) as selling_price,
        MIN(m.supplier) as supplier, MIN(m.expiry_date) as expiry_date,
        GROUP_CONCAT(m.id) as batch_ids, GROUP_CONCAT(m.batch_number SEPARATOR '|') as batch_numbers,
        GROUP_CONCAT(m.quantity SEPARATOR '|') as batch_quantities, GROUP_CONCAT(m.expiry_date SEPARATOR '|') as batch_expiries,
        GROUP_CONCAT(m.status SEPARATOR '|') as batch_statuses,
        MIN(DATEDIFF(CASE WHEN m.expiry_date = '0000-00-00' THEN NULL ELSE m.expiry_date END, CURDATE())) as days_remaining,
        CASE WHEN SUM(CASE WHEN m.status = 'active' AND (m.expiry_date IS NULL OR m.expiry_date >= CURDATE() OR m.expiry_date = '0000-00-00') THEN m.quantity ELSE 0 END) > 0 THEN 'active' ELSE 'inactive' END as computed_status
    FROM medications_inventory m
    LEFT JOIN users u ON m.added_by = u.id
    LEFT JOIN branches b ON m.branch_id = b.id
    WHERE 1=1
";
$med_params = [];

if ($filter_by_branch && $filter_branch_id > 0) { 
    $med_query .= " AND m.branch_id = ?"; 
    $med_params[] = $filter_branch_id; 
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
    $med_query .= " AND m.expiry_date IS NOT NULL AND m.expiry_date != '0000-00-00' AND m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"; 
}
if ($expiry_filter === 'expired') { 
    $med_query .= " AND m.expiry_date IS NOT NULL AND m.expiry_date != '0000-00-00' AND m.expiry_date < CURDATE()"; 
}

$med_query .= " GROUP BY m.medication_name, m.category, m.unit, m.branch_id ORDER BY m.medication_name ASC";

$stmt = $db->prepare($med_query);
$stmt->execute($med_params);
$medicines = $stmt->fetchAll();

// ================================================================
// EQUIPMENT QUERY
// ================================================================
$equip_query = "
    SELECT 
        MIN(e.id) as id, e.equipment_name, e.category, e.unit, e.branch_id, e.added_by, e.added_by_name,
        u.full_name as added_by_full_name, b.name as branch_name,
        SUM(CASE WHEN e.status = 'active' AND (e.expiry_date IS NULL OR e.expiry_date >= CURDATE() OR e.expiry_date = '0000-00-00') THEN e.quantity ELSE 0 END) as total_quantity,
        MIN(e.reorder_level) as reorder_level, MIN(e.unit_cost) as unit_cost, MIN(e.selling_price) as selling_price,
        MIN(e.supplier) as supplier, MIN(e.expiry_date) as expiry_date,
        GROUP_CONCAT(e.id) as batch_ids, GROUP_CONCAT(e.batch_number SEPARATOR '|') as batch_numbers,
        GROUP_CONCAT(e.quantity SEPARATOR '|') as batch_quantities, GROUP_CONCAT(e.expiry_date SEPARATOR '|') as batch_expiries,
        GROUP_CONCAT(e.status SEPARATOR '|') as batch_statuses,
        MIN(DATEDIFF(CASE WHEN e.expiry_date = '0000-00-00' THEN NULL ELSE e.expiry_date END, CURDATE())) as days_remaining,
        CASE WHEN SUM(CASE WHEN e.status = 'active' AND (e.expiry_date IS NULL OR e.expiry_date >= CURDATE() OR e.expiry_date = '0000-00-00') THEN e.quantity ELSE 0 END) > 0 THEN 'active' ELSE 'inactive' END as computed_status
    FROM medical_equipment e
    LEFT JOIN users u ON e.added_by = u.id
    LEFT JOIN branches b ON e.branch_id = b.id
    WHERE 1=1
";
$equip_params = [];

if ($filter_by_branch && $filter_branch_id > 0) { 
    $equip_query .= " AND e.branch_id = ?"; 
    $equip_params[] = $filter_branch_id; 
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
    $equip_query .= " AND e.expiry_date IS NOT NULL AND e.expiry_date != '0000-00-00' AND e.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"; 
}
if ($expiry_filter === 'expired') { 
    $equip_query .= " AND e.expiry_date IS NOT NULL AND e.expiry_date != '0000-00-00' AND e.expiry_date < CURDATE()"; 
}

$equip_query .= " GROUP BY e.equipment_name, e.category, e.unit, e.branch_id ORDER BY e.equipment_name ASC";

$stmt = $db->prepare($equip_query);
$stmt->execute($equip_params);
$equipment = $stmt->fetchAll();

// ================================================================
// STATISTICS
// ================================================================
$stats_branch_condition = "";
$stats_params = [];

if ($filter_by_branch && $filter_branch_id > 0) {
    $stats_branch_condition = " AND branch_id = ?";
    $stats_params[] = $filter_branch_id;
}

$sql = "SELECT COUNT(DISTINCT medication_name) as count FROM medications_inventory WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $total_medicines = $stmt->fetch()['count'] ?? 0;

$sql = "SELECT COALESCE(SUM(quantity), 0) as total FROM medications_inventory WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $total_med_quantity = $stmt->fetch()['total'] ?? 0;

$sql = "SELECT COUNT(DISTINCT medication_name) as count FROM medications_inventory WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') AND quantity > 0 $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $med_in_stock = $stmt->fetch()['count'] ?? 0;

$sql = "SELECT COUNT(DISTINCT medication_name) as count FROM medications_inventory WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') AND quantity = 0 $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $med_out_of_stock = $stmt->fetch()['count'] ?? 0;

$sql = "SELECT COUNT(DISTINCT medication_name) as count FROM medications_inventory WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') AND quantity > 0 AND quantity <= reorder_level $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $med_low_stock = $stmt->fetch()['count'] ?? 0;

$sql = "SELECT COUNT(DISTINCT medication_name) as count FROM medications_inventory WHERE expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'active' $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $med_expiring = $stmt->fetch()['count'] ?? 0;

$sql = "SELECT COUNT(DISTINCT medication_name) as count FROM medications_inventory WHERE expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date < CURDATE() $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $med_expired = $stmt->fetch()['count'] ?? 0;

$sql = "SELECT COUNT(DISTINCT medication_name) as count FROM medications_inventory WHERE status = 'inactive' $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $med_inactive = $stmt->fetch()['count'] ?? 0;

$sql = "SELECT COALESCE(SUM(quantity * selling_price), 0) as total_value FROM medications_inventory WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $med_value = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

$sql = "SELECT COUNT(DISTINCT equipment_name) as count FROM medical_equipment WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $total_equipment = $stmt->fetch()['count'] ?? 0;

$sql = "SELECT COALESCE(SUM(quantity), 0) as total FROM medical_equipment WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $total_equip_quantity = $stmt->fetch()['total'] ?? 0;

$sql = "SELECT COUNT(DISTINCT equipment_name) as count FROM medical_equipment WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') AND quantity > 0 $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $equip_in_stock = $stmt->fetch()['count'] ?? 0;

$sql = "SELECT COUNT(DISTINCT equipment_name) as count FROM medical_equipment WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') AND quantity = 0 $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $equip_out_of_stock = $stmt->fetch()['count'] ?? 0;

$sql = "SELECT COUNT(DISTINCT equipment_name) as count FROM medical_equipment WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') AND quantity > 0 AND quantity <= reorder_level $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $equip_low_stock = $stmt->fetch()['count'] ?? 0;

$sql = "SELECT COUNT(DISTINCT equipment_name) as count FROM medical_equipment WHERE expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'active' $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $equip_expiring = $stmt->fetch()['count'] ?? 0;

$sql = "SELECT COUNT(DISTINCT equipment_name) as count FROM medical_equipment WHERE expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date < CURDATE() $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $equip_expired = $stmt->fetch()['count'] ?? 0;

$sql = "SELECT COUNT(DISTINCT equipment_name) as count FROM medical_equipment WHERE status = 'inactive' $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $equip_inactive = $stmt->fetch()['count'] ?? 0;

$sql = "SELECT COALESCE(SUM(quantity * selling_price), 0) as total_value FROM medical_equipment WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') $stats_branch_condition";
$stmt = $db->prepare($sql); $stmt->execute($stats_params); $equip_value = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

$total_inventory_value = $med_value + $equip_value;

$display_branch_name = 'All Branches';
if ($filter_by_branch && $filter_branch_id > 0) {
    foreach ($branches as $b) {
        if ($b['id'] == $filter_branch_id) { 
            $display_branch_name = $b['name']; 
            break; 
        }
    }
}

$profile_pic_url = !empty($profile_pic) ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG';

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $unread_notifications = 0; }

// ================================================================
// ✅ INCLUDE SHARED SIDEBAR ONLY (NO HEADER - WE HAVE EMBEDDED HEADER)
// ================================================================
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7; --primary-dark: #0A4CA8; --primary-light: #E8F0FE;
            --success: #059669; --success-dark: #047857; --success-light: #D1FAE5;
            --warning: #D97706; --warning-light: #FEF3C7;
            --danger: #DC2626; --danger-light: #FEE2E2;
            --purple: #7C3AED; --purple-light: #EDE9FE;
            --teal: #0D9488; --teal-light: #CCFBF1;
            --bg-body: #F1F5F9; --bg-card: #FFFFFF; --bg-nav: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #1E293B; --text-secondary: #64748B; --text-muted: #94A3B8;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A; --bg-card: #1E293B; --bg-nav: #1E293B;
            --border-color: #334155;
            --text-primary: #F1F5F9; --text-secondary: #94A3B8; --text-muted: #64748B;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text-primary); }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ================================================================
           ✅ EMBEDDED HEADER - SAME AS SHARED admin_header.php
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
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 12px;
            border: 2px solid var(--border-color);
            flex: 1;
            max-width: 500px;
            height: 42px;
            transition: all 0.3s;
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
            height: 100%;
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 6px;
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
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
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
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--text-muted); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 8px;
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
        .main-content { margin-left: 270px; margin-top: 68px; padding: 28px 32px; min-height: calc(100vh - 68px); }
        
        /* BRANCH INFO */
        .branch-info-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px; padding: 14px 24px; margin-bottom: 20px;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 10px; color: white;
        }
        .branch-info-card .branch-title { font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .branch-info-card .filter-badge {
            background: rgba(255,255,255,0.25); padding: 2px 10px;
            border-radius: 12px; font-size: 0.65rem; font-weight: 700;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .branch-info-card .branch-stats { display: flex; gap: 12px; flex-wrap: wrap; }
        .branch-info-card .stat-item {
            display: flex; align-items: center; gap: 5px; font-size: 0.7rem;
            background: rgba(255,255,255,0.1); padding: 3px 10px; border-radius: 20px;
        }
        
        /* PAGE HEADER */
        .page-header-box {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px; padding: 18px 24px; margin-bottom: 20px;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 10px;
        }
        .page-header-box .page-title {
            color: white; font-size: 1.4rem; font-weight: 700;
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        }
        .page-title .role-badge-display {
            background: rgba(255,255,255,0.2); color: white;
            padding: 2px 10px; border-radius: 20px; font-size: 0.55rem;
            font-weight: 600; text-transform: uppercase;
        }
        .page-title .branch-name-display {
            background: rgba(255,255,255,0.15); padding: 2px 12px;
            border-radius: 20px; font-size: 0.7rem; font-weight: 500;
        }
        .page-title .btn-back-green {
            background: var(--success); color: white; border: none;
            padding: 5px 16px; border-radius: 20px; font-size: 0.7rem;
            font-weight: 600; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
        }
        
        .page-subtitle {
            color: rgba(255,255,255,0.85); font-size: 0.8rem;
            display: flex; align-items: center; gap: 6px;
            flex-wrap: wrap; margin-top: 2px;
        }
        
        .header-badge {
            background: rgba(255,255,255,0.12); color: white;
            padding: 2px 10px; border-radius: 20px; font-size: 0.6rem;
            font-weight: 500; display: inline-flex; align-items: center; gap: 4px;
        }
        
        .header-actions { display: flex; gap: 10px; flex-wrap: nowrap; align-items: center; }
        
        .btn-action {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; padding: 10px 24px; border-radius: 8px;
            font-weight: 600; font-size: 0.82rem; border: none;
            cursor: pointer; text-decoration: none; color: white;
            height: 48px; min-width: 180px; white-space: nowrap;
        }
        .btn-add-medicine { background: var(--success); }
        .btn-add-equipment { background: var(--purple); }
        .btn-purchase-history { background: var(--warning); }
        
        /* MESSAGE */
        .message-box {
            padding: 12px 18px; border-radius: 10px; margin-bottom: 16px;
            display: flex; align-items: center; gap: 10px;
            font-weight: 500; font-size: 0.9rem;
            border-left: 5px solid transparent;
        }
        .message-box.success { background: #D1FAE5; color: #065F46; border-left: 5px solid #059669; }
        .message-box.error { background: #FEE2E2; color: #991B1B; border-left: 5px solid #DC2626; }
        
        /* STATS */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px; }
        .stat-card {
            border-radius: 12px; padding: 16px 18px; text-decoration: none;
            display: block; color: white; min-height: 85px;
        }
        .stat-card .stat-number { font-size: 1.6rem; font-weight: 700; line-height: 1.2; }
        .stat-card .stat-label { font-size: 0.6rem; color: rgba(255,255,255,0.9); font-weight: 500; text-transform: uppercase; margin-top: 2px; }
        .stat-card .stat-icon { font-size: 1.3rem; opacity: 0.7; float: right; margin-top: -5px; }
        .stat-card .stat-sub { font-size: 0.55rem; color: rgba(255,255,255,0.75); margin-top: 2px; }
        .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
        .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
        .stat-card.indigo { background: linear-gradient(135deg, #4F46E5, #4338CA); }
        
        /* CARD */
        .card { background: var(--bg-card); border-radius: 12px; padding: 14px 18px; border: 2px solid var(--border-color); margin-bottom: 20px; }
        
        /* FILTERS */
        .filter-group { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 10px; }
        .filter-btn {
            padding: 3px 12px; border-radius: 14px; font-size: 0.65rem;
            font-weight: 600; border: 2px solid var(--border-color);
            background: transparent; color: var(--text-secondary);
            cursor: pointer; text-decoration: none;
        }
        .filter-btn:hover { border-color: var(--primary); color: var(--primary); }
        .filter-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
        
        /* TABLE */
        .table-header-bar {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 12px; margin-bottom: 12px;
            padding-bottom: 10px; border-bottom: 2px solid var(--border-color);
        }
        .table-header-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; flex: 1; min-width: 0; }
        .table-header-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        
        .table-search-box { position: relative; min-width: 280px; flex: 1; max-width: 400px; }
        .table-search-box i {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: rgba(255,255,255,0.9); font-size: 0.85rem; pointer-events: none;
        }
        .table-search-box input {
            width: 100%; padding: 10px 14px 10px 40px;
            border: 2px solid var(--primary); border-radius: 10px; font-size: 0.85rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white; outline: none; font-weight: 500; height: 42px;
        }
        .table-search-box input::placeholder { color: rgba(255,255,255,0.85); }
        
        .scroll-btn-header {
            width: 38px; height: 38px; border-radius: 8px;
            border: 2px solid var(--border-color); background: var(--bg-card);
            color: var(--text-primary); cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .scroll-btn-header:hover { background: var(--primary); border-color: var(--primary); color: white; }
        .scroll-btn-header:disabled { opacity: 0.35; cursor: not-allowed; }
        
        .search-results-info {
            font-size: 0.7rem; color: var(--primary); padding: 6px 12px;
            background: var(--primary-light); border-radius: 8px;
            display: none; font-weight: 600; border: 1px solid var(--primary);
        }
        
        .card-title { font-size: 0.9rem; font-weight: 600; color: var(--text-primary); }
        .result-count { font-size: 0.75rem; color: var(--text-secondary); }
        .result-count strong { color: var(--primary); }
        
        .table-scroll-container { overflow-x: auto; overflow-y: auto; max-height: 450px; }
        
        .data-table {
            width: 100%; min-width: 1400px;
            border-collapse: separate; border-spacing: 0; font-size: 0.72rem;
        }
        .data-table thead th {
            position: sticky; top: 0; z-index: 10;
            background: var(--primary); color: white;
            padding: 6px 10px; font-size: 0.6rem;
            text-transform: uppercase; font-weight: 700;
            white-space: nowrap; text-align: left;
        }
        .data-table tbody tr:nth-child(even) { background: var(--primary-light); }
        .data-table tbody tr:hover td { background: var(--success-light); }
        .data-table td {
            padding: 5px 8px; border-bottom: 1px solid var(--border-color);
            color: var(--text-primary); white-space: nowrap;
        }
        
        .col-sno { width: 30px; text-align: center; }
        .col-name { min-width: 160px; }
        .col-category { min-width: 100px; }
        .col-branch { min-width: 90px; }
        .col-qty { min-width: 60px; text-align: center; }
        .col-reorder { min-width: 60px; text-align: center; }
        .col-stock { min-width: 90px; }
        .col-price { min-width: 100px; }
        .col-expiry { min-width: 85px; }
        .col-days { min-width: 60px; text-align: center; }
        .col-batch { min-width: 100px; }
        .col-status { min-width: 70px; text-align: center; }
        .col-active { min-width: 60px; text-align: center; }
        .col-added-by { min-width: 110px; }
        .col-actions { min-width: 80px; text-align: center; }
        
        .no-results-row td { text-align: center; padding: 30px 20px !important; color: var(--text-secondary); }
        
        .status-badge {
            padding: 1px 6px; border-radius: 8px; font-size: 0.55rem;
            font-weight: 600; display: inline-flex; align-items: center; gap: 2px;
        }
        .status-badge.active { background: var(--success-light); color: var(--success); }
        .status-badge.inactive { background: var(--danger-light); color: var(--danger); }
        
        .stock-badge {
            padding: 1px 6px; border-radius: 6px; font-size: 0.6rem;
            font-weight: 600; display: inline-flex; align-items: center; gap: 2px;
        }
        .stock-badge.ok { background: var(--success-light); color: var(--success); }
        .stock-badge.low { background: var(--warning-light); color: var(--warning); }
        .stock-badge.out { background: var(--danger-light); color: var(--danger); }
        
        .expiry-badge {
            padding: 1px 6px; border-radius: 6px; font-size: 0.55rem;
            font-weight: 600; display: inline-flex; align-items: center; gap: 2px;
        }
        .expiry-badge.valid { background: var(--success-light); color: var(--success); }
        .expiry-badge.expiring { background: var(--warning-light); color: var(--warning); }
        .expiry-badge.expired { background: var(--danger-light); color: var(--danger); }
        .expiry-badge.no-expiry { background: #E2E8F0; color: var(--text-muted); }
        
        .days-remaining {
            font-size: 0.6rem; font-weight: 600; padding: 1px 5px;
            border-radius: 6px; display: inline-flex; align-items: center; gap: 2px;
        }
        .days-remaining.good { background: var(--success-light); color: var(--success); }
        .days-remaining.warning { background: var(--warning-light); color: var(--warning); }
        .days-remaining.danger { background: var(--danger-light); color: var(--danger); }
        .days-remaining.forever { background: #E2E8F0; color: var(--text-muted); }
        
        .batch-number {
            font-family: monospace; font-size: 0.6rem; font-weight: 600;
            padding: 1px 6px; border-radius: 3px;
            background: var(--primary-light); color: var(--primary);
        }
        
        .added-by-tag {
            display: inline-flex; align-items: center; gap: 3px;
            padding: 1px 8px; border-radius: 10px; font-size: 0.6rem;
            font-weight: 600; background: var(--purple-light); color: var(--purple);
        }
        
        .action-group { display: flex; flex-direction: column; gap: 3px; align-items: center; }
        .action-btn {
            padding: 2px 8px; border-radius: 4px; font-size: 0.55rem;
            font-weight: 600; border: none; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            gap: 3px; color: white; height: 22px; width: 60px;
        }
        .action-btn.view { background: var(--purple); }
        .action-btn.edit { background: var(--warning); }
        .action-btn.delete { background: var(--danger); }
        
        .action-btn-sm {
            padding: 1px 6px; border-radius: 3px; font-size: 0.5rem;
            font-weight: 600; border: none; cursor: pointer;
            color: white; height: 20px; width: 50px;
        }
        .action-btn-sm.edit { background: var(--warning); }
        .action-btn-sm.delete { background: var(--danger); }
        
        /* TABS */
        .tabs-container {
            display: flex; gap: 4px; background: var(--bg-card);
            border-radius: 12px; padding: 4px;
            border: 2px solid var(--border-color); margin-bottom: 20px;
        }
        .tab-btn {
            padding: 10px 24px; border-radius: 10px; font-weight: 600;
            font-size: 0.85rem; border: none; cursor: pointer;
            background: transparent; color: var(--text-secondary);
            display: inline-flex; align-items: center; gap: 8px;
            flex: 1; justify-content: center;
        }
        .tab-btn:hover { background: var(--primary-light); color: var(--primary); }
        .tab-btn.active { background: var(--primary); color: white; }
        .tab-btn .badge { background: rgba(255,255,255,0.2); color: white; padding: 1px 8px; border-radius: 10px; font-size: 0.65rem; }
        .tab-btn:not(.active) .badge { background: var(--border-color); color: var(--text-secondary); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* MODAL */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6); z-index: 2000;
            justify-content: center; align-items: center; padding: 20px;
        }
        .modal-overlay.show { display: flex; }
        
        .modal-content {
            background: var(--bg-card); border-radius: 14px;
            padding: 20px 24px; max-width: 850px; width: 95%;
            max-height: 90vh; overflow-y: auto;
            border: 2px solid var(--border-color);
        }
        
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            padding-bottom: 10px; border-bottom: 2px solid var(--border-color);
            margin-bottom: 14px;
        }
        .modal-title { font-size: 1rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
        .modal-close { background: none; border: none; font-size: 1.3rem; cursor: pointer; color: var(--text-secondary); text-decoration: none; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-grid .full-width { grid-column: 1 / -1; }
        .form-label { font-size: 0.8rem; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; display: block; }
        .form-label .required { color: var(--danger); }
        .form-control {
            width: 100%; padding: 8px 12px; border: 2px solid var(--border-color);
            border-radius: 6px; font-size: 0.9rem; outline: none;
            background: var(--bg-card); color: var(--text-primary); height: 42px;
        }
        .form-actions { display: flex; gap: 8px; margin-top: 16px; padding-top: 12px; border-top: 2px solid var(--border-color); }
        .btn-save {
            background: var(--primary); color: white; padding: 10px 28px;
            border-radius: 8px; font-weight: 600; font-size: 0.9rem;
            border: none; cursor: pointer; height: 44px;
        }
        .btn-cancel {
            background: transparent; color: var(--text-secondary);
            border: 2px solid var(--border-color); padding: 10px 24px;
            border-radius: 8px; font-weight: 600; font-size: 0.9rem;
            cursor: pointer; text-decoration: none; height: 44px;
            display: inline-flex; align-items: center; gap: 6px;
        }
        
        .category-input-group,
        .unit-input-group,
        .batch-input-group {
            display: flex; gap: 6px; align-items: center;
        }
        .category-input-group .form-control,
        .unit-input-group .form-control,
        .batch-input-group .form-control { flex: 1; }
        
        .btn-toggle {
            background: var(--primary); color: white; border: none;
            border-radius: 8px; padding: 8px 12px;
            font-size: 0.7rem; font-weight: 600;
            cursor: pointer; white-space: nowrap; height: 42px;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-toggle:hover { background: var(--primary-dark); }
        
        /* MANAGE PURCHASES PAGE */
        .manage-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px; padding: 24px 28px; margin-bottom: 24px;
            color: white;
        }
        
        .manage-header .manage-title {
            font-size: 1.4rem; font-weight: 700;
            display: flex; align-items: center; gap: 10px; margin-bottom: 6px;
            flex-wrap: wrap;
        }
        
        .manage-header .manage-subtitle { 
            font-size: 0.85rem; opacity: 0.9; 
        }
        
        .btn-back-header {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 22px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            border: 2px solid rgba(255,255,255,0.3);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            white-space: nowrap;
            height: 48px;
            backdrop-filter: blur(4px);
        }
        
        .btn-back-header:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
            transform: translateX(-3px);
        }
        
        .purchase-list-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 16px; margin-bottom: 24px;
        }
        
        .purchase-item-card {
            background: var(--bg-card); border: 2px solid var(--border-color);
            border-radius: 12px; padding: 18px 20px;
            transition: all 0.3s ease; position: relative;
        }
        .purchase-item-card:hover {
            border-color: var(--primary); transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(11, 94, 215, 0.12);
        }
        
        .purchase-item-card .invoice-number {
            font-size: 1rem; font-weight: 700; color: var(--primary);
            display: flex; align-items: center; gap: 6px; margin-bottom: 8px;
        }
        
        .purchase-item-card .purchase-meta {
            font-size: 0.75rem; color: var(--text-secondary);
            display: flex; flex-direction: column; gap: 4px; margin-bottom: 14px;
        }
        
        .purchase-item-card .purchase-meta span {
            display: flex; align-items: center; gap: 6px;
        }
        
        .purchase-item-card .purchase-stats {
            display: flex; gap: 10px; margin-bottom: 14px; flex-wrap: wrap;
        }
        
        .purchase-item-card .stat-pill {
            background: var(--primary-light); color: var(--primary);
            padding: 4px 12px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 5px;
        }
        
        .btn-join-purchase {
            background: var(--primary); color: white; border: none;
            padding: 10px 20px; border-radius: 8px; font-weight: 600;
            font-size: 0.8rem; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; width: 100%;
        }
        .btn-join-purchase:hover { background: var(--primary-dark); }
        
        .create-new-box {
            background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
            border: 2px dashed var(--success);
            border-radius: 16px; padding: 24px 28px;
            text-align: center; margin-bottom: 24px;
        }
        
        .create-new-box .create-icon {
            width: 70px; height: 70px; border-radius: 50%;
            background: linear-gradient(135deg, var(--success), #047857);
            color: white; display: flex; align-items: center;
            justify-content: center; font-size: 2rem;
            margin: 0 auto 14px;
        }
        
        .create-new-box .create-title {
            font-size: 1.1rem; font-weight: 700; color: #065F46;
            margin-bottom: 6px;
        }
        
        .create-new-box .create-desc {
            font-size: 0.85rem; color: #047857; margin-bottom: 18px;
        }
        
        .btn-create-new {
            background: var(--success); color: white; border: none;
            padding: 12px 32px; border-radius: 10px; font-weight: 700;
            font-size: 0.9rem; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            gap: 10px; min-width: 240px;
        }
        .btn-create-new:hover { background: var(--success-dark); transform: translateY(-2px); }
        
        .empty-purchases {
            text-align: center; padding: 40px 20px; color: var(--text-secondary);
        }
        .empty-purchases i {
            font-size: 3rem; color: var(--border-color);
            display: block; margin-bottom: 12px;
        }
        
        .footer {
            padding: 10px 0; border-top: 1px solid var(--border-color);
            margin-top: 16px; text-align: center;
            font-size: 0.6rem; color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 14px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .page-header-box { flex-direction: column; align-items: stretch; }
            .header-actions { flex-direction: column; width: 100%; }
            .btn-action { width: 100%; min-width: unset; }
            .form-grid { grid-template-columns: 1fr; }
            .table-search-box { min-width: 100%; max-width: 100%; }
            .tabs-container { flex-direction: column; }
            .purchase-list-grid { grid-template-columns: 1fr; }
            .btn-back-header { width: 100%; justify-content: center; }
            .datetime { display: none; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- ✅ EMBEDDED HEADER - SAME AS SHARED admin_header.php -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div style="display:flex;align-items:center;gap:16px;flex:1;">
        <button id="sidebarToggle" class="lg:hidden icon-btn" style="display:none;">
            <i class="fas fa-bars" style="font-size:1.1rem;"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search" style="color:#94A3B8;margin-left:12px;"></i>
            <input type="text" id="globalSearchInput" placeholder="Search inventory...">
            <button id="globalSearchBtn" class="search-btn">
                <i class="fas fa-search"></i> Search
            </button>
        </div>
    </div>
    
    <div style="display:flex;align-items:center;gap:12px;">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime">
            <i class="fas fa-clock"></i>
            <span id="currentDateTime"><?= date('d M Y • h:i:s A') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn" onclick="window.location.href='notifications.php'">
            <i class="fas fa-bell" style="font-size:1.1rem;"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<main class="main-content">

<?php if ($show_manage_purchases): ?>
    <!-- ================================================================ -->
    <!-- MANAGE PURCHASES PAGE -->
    <!-- ================================================================ -->
    
    <div class="manage-header">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
            <div style="flex:1;min-width:0;">
                <div class="manage-title">
                    <i class="fas <?= $manage_type === 'medicine' ? 'fa-pills' : 'fa-tools' ?>"></i>
                    <?= $manage_type === 'medicine' ? 'Medicine' : 'Equipment' ?> Purchases
                    <span style="background:rgba(255,255,255,0.2);padding:3px 12px;border-radius:20px;font-size:0.7rem;">
                        🏥 <?= htmlspecialchars($manage_branch_name ?? $user_branch_name) ?>
                    </span>
                </div>
                <div class="manage-subtitle">
                    <?php if (count($manage_purchases) > 0): ?>
                        There are <strong><?= count($manage_purchases) ?></strong> purchase(s) in progress. You can JOIN one or CREATE NEW.
                    <?php else: ?>
                        No purchases in progress. Click "Create New" to start a fresh <?= $manage_type ?> purchase.
                    <?php endif; ?>
                </div>
            </div>
            
            <a href="inventory.php?tab=<?= $manage_type === 'medicine' ? 'medicines' : 'equipment' ?>&branch=<?= $selected_branch_id ?>" 
               class="btn-back-header">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>
    
    <!-- CREATE NEW BOX -->
    <div class="create-new-box">
        <div class="create-icon">
            <i class="fas fa-plus-circle"></i>
        </div>
        <div class="create-title">Start a New <?= ucfirst($manage_type) ?> Purchase</div>
        <div class="create-desc">
            <?php if (count($manage_purchases) > 0): ?>
                Want to start fresh instead of joining an existing purchase? Click below.
            <?php else: ?>
                No purchases in progress. Click below to start a new one.
            <?php endif; ?>
        </div>
        <form method="POST" action="inventory.php" style="display:inline;">
            <input type="hidden" name="action" value="create_new_purchase">
            <input type="hidden" name="purchase_type" value="<?= htmlspecialchars($manage_type) ?>">
            <input type="hidden" name="target_branch" value="<?= (int)$manage_branch ?>">
            <button type="submit" class="btn-create-new">
                <i class="fas fa-plus-circle"></i> Create New <?= ucfirst($manage_type) ?> Purchase
            </button>
        </form>
    </div>
    
    <!-- EXISTING PURCHASES LIST -->
    <?php if (count($manage_purchases) > 0): ?>
        <div class="card" style="padding:20px 24px;">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-list" style="color:var(--primary);"></i>
                Existing <?= ucfirst($manage_type) ?> Purchases In Progress
                <span style="background:var(--primary-light);color:var(--primary);padding:2px 12px;border-radius:12px;font-size:0.7rem;">
                    <?= count($manage_purchases) ?>
                </span>
            </h3>
            
            <div class="purchase-list-grid">
                <?php foreach ($manage_purchases as $mp): ?>
                    <div class="purchase-item-card">
                        <div class="invoice-number">
                            <i class="fas fa-file-invoice"></i>
                            <?= htmlspecialchars($mp['invoice_number']) ?>
                        </div>
                        
                        <div class="purchase-meta">
                            <span>
                                <i class="fas fa-user"></i>
                                <strong><?= htmlspecialchars($mp['creator_full_name'] ?? $mp['created_by_name'] ?? 'Unknown') ?></strong>
                            </span>
                            <span>
                                <i class="fas fa-clock"></i>
                                Started: <?= date('d/m/Y H:i', strtotime($mp['created_at'])) ?>
                            </span>
                            <span>
                                <i class="fas fa-store-alt"></i>
                                Branch: <strong><?= htmlspecialchars($mp['branch_name'] ?? 'N/A') ?></strong>
                            </span>
                        </div>
                        
                        <div class="purchase-stats">
                            <span class="stat-pill">
                                <i class="fas fa-boxes"></i>
                                <?= number_format($mp['total_items'] ?? 0) ?> items
                            </span>
                            <span class="stat-pill">
                                <i class="fas fa-cubes"></i>
                                <?= number_format($mp['total_quantity'] ?? 0) ?> qty
                            </span>
                        </div>
                        
                        <a href="purchases.php?id=<?= $mp['id'] ?>&type=<?= htmlspecialchars($manage_type) ?>&branch=<?= $selected_branch_id ?>" class="btn-join-purchase">
                            <i class="fas fa-sign-in-alt"></i> Join This Purchase
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="empty-purchases">
                <i class="fas <?= $manage_type === 'medicine' ? 'fa-pills' : 'fa-tools' ?>"></i>
                <p style="font-size:0.95rem;font-weight:600;color:var(--text-primary);margin-bottom:6px;">
                    No <?= $manage_type ?> purchases in progress
                </p>
                <p style="font-size:0.8rem;">Click "Create New" above to start your first purchase.</p>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- ================================================================ -->
    <!-- NORMAL INVENTORY PAGE -->
    <!-- ================================================================ -->
    
    <div class="branch-info-card">
        <div class="branch-title">
            <i class="fas fa-store-alt"></i>
            <?php if ($filter_by_branch): ?>
                🏥 <?= htmlspecialchars($display_branch_name) ?>
                <span class="filter-badge">
                    <i class="fas fa-filter"></i> FILTERED
                </span>
            <?php else: ?>
                🌐 All Branches
            <?php endif; ?>
        </div>
        <div class="branch-stats">
            <span class="stat-item"><i class="fas fa-pills"></i> <strong><?= $total_medicines ?></strong> Medicines</span>
            <span class="stat-item"><i class="fas fa-tools"></i> <strong><?= $total_equipment ?></strong> Equipment</span>
            <span class="stat-item"><i class="fas fa-coins"></i> <strong>TSh <?= formatMoneyShort($total_inventory_value) ?></strong></span>
        </div>
    </div>

    <div class="page-header-box">
        <div>
            <h1 class="page-title">
                <i class="fas fa-warehouse"></i> Inventory
                <span class="role-badge-display">ADMIN</span>
                <span class="branch-name-display">
                    <i class="fas fa-store-alt"></i> 
                    <?= $filter_by_branch ? htmlspecialchars($display_branch_name) : 'All Branches' ?>
                </span>
                <a href="dashboard.php" class="btn-back-green"><i class="fas fa-arrow-left"></i> Back</a>
            </h1>
            <p class="page-subtitle">
                <strong><?= $total_medicines + $total_equipment ?></strong> total entries
                <span class="header-badge"><i class="fas fa-pills"></i> <?= $med_in_stock + $equip_in_stock ?> In Stock</span>
                <span class="header-badge"><i class="fas fa-coins"></i> TSh <?= formatMoneyShort($total_inventory_value) ?></span>
            </p>
        </div>
        <div class="header-actions">
            <a href="inventory.php?manage=medicine&target_branch=<?= $filter_by_branch ? $filter_branch_id : $user_branch_id ?>&branch=<?= $selected_branch_id ?>" class="btn-action btn-add-medicine">
                <i class="fas fa-plus-circle"></i> Add Medicine
            </a>
            
            <a href="inventory.php?manage=equipment&target_branch=<?= $filter_by_branch ? $filter_branch_id : $user_branch_id ?>&branch=<?= $selected_branch_id ?>" class="btn-action btn-add-equipment">
                <i class="fas fa-plus-circle"></i> Add Equipment
            </a>
            
            <a href="purchase_history.php?branch=<?= $selected_branch_id ?>" class="btn-action btn-purchase-history">
                <i class="fas fa-history"></i> Purchase History
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>" id="messageBox">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <span><?= $message ?></span>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tabs-container">
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
        <div class="stats-grid">
            <a href="inventory.php?tab=medicines&branch=<?= $selected_branch_id ?>" class="stat-card blue">
                <span class="stat-icon"><i class="fas fa-pills"></i></span>
                <div class="stat-number"><?= $total_medicines ?></div>
                <div class="stat-label">Total Medicines</div>
                <div class="stat-sub">💊 <?= formatMoneyShort($med_value) ?></div>
            </a>
            <a href="inventory.php?tab=medicines&stock=low&branch=<?= $selected_branch_id ?>" class="stat-card orange">
                <span class="stat-icon"><i class="fas fa-exclamation-triangle"></i></span>
                <div class="stat-number"><?= $med_low_stock ?></div>
                <div class="stat-label">Low Stock</div>
                <div class="stat-sub">Below reorder level</div>
            </a>
            <a href="inventory.php?tab=medicines&stock=out&branch=<?= $selected_branch_id ?>" class="stat-card red">
                <span class="stat-icon"><i class="fas fa-times-circle"></i></span>
                <div class="stat-number"><?= $med_out_of_stock ?></div>
                <div class="stat-label">Out of Stock</div>
                <div class="stat-sub">Quantity = 0</div>
            </a>
            <a href="inventory.php?tab=medicines&expiry=expiring&branch=<?= $selected_branch_id ?>" class="stat-card teal">
                <span class="stat-icon"><i class="fas fa-clock"></i></span>
                <div class="stat-number"><?= $med_expiring ?></div>
                <div class="stat-label">Expiring Soon</div>
                <div class="stat-sub">Within 30 days</div>
            </a>
            <a href="inventory.php?tab=medicines&expiry=expired&branch=<?= $selected_branch_id ?>" class="stat-card red">
                <span class="stat-icon"><i class="fas fa-skull"></i></span>
                <div class="stat-number"><?= $med_expired ?></div>
                <div class="stat-label">Has Expired</div>
                <div class="stat-sub">Some batches</div>
            </a>
            <a href="inventory.php?tab=medicines&status=active&branch=<?= $selected_branch_id ?>" class="stat-card green">
                <span class="stat-icon"><i class="fas fa-check-circle"></i></span>
                <div class="stat-number"><?= $med_in_stock ?></div>
                <div class="stat-label">In Stock</div>
                <div class="stat-sub">Available</div>
            </a>
            <a href="inventory.php?tab=medicines&status=inactive&branch=<?= $selected_branch_id ?>" class="stat-card purple">
                <span class="stat-icon"><i class="fas fa-archive"></i></span>
                <div class="stat-number"><?= $med_inactive ?></div>
                <div class="stat-label">Inactive</div>
                <div class="stat-sub">No active batches</div>
            </a>
            <a href="inventory.php?tab=medicines&branch=<?= $selected_branch_id ?>" class="stat-card indigo">
                <span class="stat-icon"><i class="fas fa-coins"></i></span>
                <div class="stat-number">TSh <?= formatMoneyShort($med_value) ?></div>
                <div class="stat-label">Total Value</div>
                <div class="stat-sub">Inventory worth</div>
            </a>
        </div>

        <div class="card">
            <div class="filter-group">
                <a href="inventory.php?tab=medicines&branch=<?= $selected_branch_id ?>" class="filter-btn <?= empty($status_filter) && empty($stock_filter) && empty($expiry_filter) ? 'active' : '' ?>">All</a>
                <a href="inventory.php?tab=medicines&status=active&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $status_filter === 'active' ? 'active' : '' ?>">Active</a>
                <a href="inventory.php?tab=medicines&status=inactive&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $status_filter === 'inactive' ? 'active' : '' ?>">Inactive</a>
                <a href="inventory.php?tab=medicines&stock=low&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $stock_filter === 'low' ? 'active' : '' ?>">Low Stock</a>
                <a href="inventory.php?tab=medicines&stock=out&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $stock_filter === 'out' ? 'active' : '' ?>">Out of Stock</a>
                <a href="inventory.php?tab=medicines&expiry=expiring&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $expiry_filter === 'expiring' ? 'active' : '' ?>">Expiring Soon</a>
                <a href="inventory.php?tab=medicines&expiry=expired&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $expiry_filter === 'expired' ? 'active' : '' ?>">Has Expired</a>
            </div>
        </div>

        <div class="card">
            <div class="table-header-bar">
                <div class="table-header-left">
                    <div class="table-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="medSearchInput" placeholder="🔍 Auto-search medicine..." autocomplete="off">
                    </div>
                    <h3 class="card-title" style="margin:0;">
                        <i class="fas fa-list" style="color:var(--primary);"></i> 
                        <span class="result-count" id="medCountDisplay">(<strong><?= count($medicines) ?></strong> entries)</span>
                    </h3>
                    <span class="search-results-info" id="medSearchInfo"><strong id="medSearchCount">0</strong> match</span>
                </div>
                <div class="table-header-right">
                    <button class="scroll-btn-header" id="medScrollBtnLeft" onclick="scrollMedTable('left')"><i class="fas fa-chevron-left"></i></button>
                    <button class="scroll-btn-header" id="medScrollBtnRight" onclick="scrollMedTable('right')"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            
            <?php if (count($medicines) > 0): ?>
                <div class="table-scroll-container" id="medTableWrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="col-sno">#</th>
                                <th class="col-name">Name</th>
                                <th class="col-category">Category</th>
                                <th class="col-branch">Branch</th>
                                <th class="col-qty">Qty</th>
                                <th class="col-reorder">Reorder</th>
                                <th class="col-stock">Stock</th>
                                <th class="col-price">Price</th>
                                <th class="col-expiry">Expiry</th>
                                <th class="col-days">Days</th>
                                <th class="col-batch">Batch</th>
                                <th class="col-status">Status</th>
                                <th class="col-active">Active</th>
                                <th class="col-added-by">Added By</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="medTableBody">
                            <?php $counter = 1; ?>
                            <?php foreach ($medicines as $item): ?>
                                <?php
                                    $active_qty = $item['total_quantity'] ?? 0;
                                    $branch_name = $item['branch_name'] ?? 'Unknown';
                                    $batch_ids = $item['batch_ids'] ?? '';
                                    $first_batch_id = $batch_ids ? explode(',', $batch_ids)[0] : 0;
                                    $category_display = !empty($item['category']) ? $item['category'] : 'N/A';
                                    
                                    $stock_status = 'ok'; $stock_label = 'In Stock';
                                    if ($active_qty <= 0) { $stock_status = 'out'; $stock_label = 'Out of Stock'; }
                                    elseif ($active_qty <= $item['reorder_level']) { $stock_status = 'low'; $stock_label = 'Low Stock'; }
                                    
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
                                    
                                    $search_data = strtolower(($item['medication_name'] ?? '') . ' ' . $category_display . ' ' . $branch_name . ' ' . $added_by_display . ' ' . ($first_batch ?? '') . ' ' . $stock_label);
                                    
                                    $view_data_json = htmlspecialchars(json_encode([
                                        'id' => $item['id'], 'name' => $item['medication_name'], 'category' => $category_display,
                                        'unit' => $item['unit'] ?? 'pcs', 'reorder_level' => $item['reorder_level'],
                                        'selling_price' => $item['selling_price'] ?? 0, 'supplier' => $item['supplier'] ?? 'N/A',
                                        'status' => $display_status, 'active_qty' => $active_qty,
                                        'branch_id' => $item['branch_id'], 'branch_name' => $branch_name,
                                        'batch_ids' => $batch_ids, 'batch_numbers' => $batch_numbers,
                                        'batch_quantities' => $item['batch_quantities'] ?? '',
                                        'batch_expiries' => $item['batch_expiries'] ?? '',
                                        'batch_statuses' => $item['batch_statuses'] ?? ''
                                    ]), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="med-row" data-search="<?= htmlspecialchars($search_data) ?>">
                                    <td class="col-sno"><?= $counter++ ?></td>
                                    <td class="col-name">
                                        <strong><?= htmlspecialchars($item['medication_name']) ?></strong>
                                        <?php if ($batch_count > 1): ?>
                                            <span style="font-size:0.5rem;background:var(--primary-light);color:var(--primary);padding:0 6px;border-radius:8px;"><?= $batch_count ?> batches</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-category"><?= htmlspecialchars($category_display) ?></td>
                                    <td class="col-branch"><span style="font-weight:600;color:var(--primary);font-size:0.65rem;">🏥 <?= htmlspecialchars($branch_name) ?></span></td>
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
                                            <span class="expiry-badge <?= $expiry_status ?>"><?= date('d/m/Y', strtotime($expiry_date)) ?></span>
                                        <?php else: ?>
                                            <span class="expiry-badge no-expiry"><i class="fas fa-infinity"></i> No Expiry</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-days">
                                        <?php if (!empty($expiry_date) && $expiry_date !== '0000-00-00' && $days !== '-'): ?>
                                            <span class="days-remaining <?= $days_class ?>">
                                                <?php if ($days < 0): ?>EXP
                                                <?php elseif ($days <= 30): ?><?= $days ?>d
                                                <?php else: ?><?= $days ?>d<?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="days-remaining forever">∞</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-batch">
                                        <?php if (!empty($first_batch)): ?>
                                            <span class="batch-number"><?= htmlspecialchars($first_batch) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);font-size:0.6rem;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-status"><span class="status-badge <?= $display_status ?>"><?= ucfirst($display_status) ?></span></td>
                                    <td class="col-active"><span class="stock-badge <?= $active_qty > 0 ? 'ok' : 'out' ?>"><?= $active_qty > 0 ? 'YES' : 'NO' ?></span></td>
                                    <td class="col-added-by"><span class="added-by-tag"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($added_by_display) ?></span></td>
                                    <td class="col-actions">
                                        <div class="action-group">
                                            <button class="action-btn view" onclick='openViewModal(<?= $view_data_json ?>, "medicine")'><i class="fas fa-eye"></i> View</button>
                                            <button class="action-btn edit" onclick="openEditMedicineModal(<?= $first_batch_id ?>, '<?= htmlspecialchars($item['medication_name']) ?>')"><i class="fas fa-edit"></i> Edit</button>
                                            <button class="action-btn delete" onclick="deleteMedicine(<?= $first_batch_id ?>, '<?= addslashes($item['medication_name']) ?>')"><i class="fas fa-trash"></i> Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="no-results-row" id="medNoResults" style="display:none;"><td colspan="15"><i class="fas fa-search-minus"></i><p>No medicines match</p></td></tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:30px;color:var(--text-secondary);">
                    <i class="fas fa-pills" style="font-size:2rem;color:var(--border-color);display:block;margin-bottom:8px;"></i>
                    <p>No medicines found in <?= $filter_by_branch ? htmlspecialchars($display_branch_name) : 'this branch' ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- EQUIPMENT TAB -->
    <div id="tab-equipment" class="tab-content <?= $active_tab === 'equipment' ? 'active' : '' ?>">
        <div class="stats-grid">
            <a href="inventory.php?tab=equipment&branch=<?= $selected_branch_id ?>" class="stat-card blue">
                <span class="stat-icon"><i class="fas fa-tools"></i></span>
                <div class="stat-number"><?= $total_equipment ?></div>
                <div class="stat-label">Total Equipment</div>
                <div class="stat-sub">🔧 <?= formatMoneyShort($equip_value) ?></div>
            </a>
            <a href="inventory.php?tab=equipment&stock=low&branch=<?= $selected_branch_id ?>" class="stat-card orange">
                <span class="stat-icon"><i class="fas fa-exclamation-triangle"></i></span>
                <div class="stat-number"><?= $equip_low_stock ?></div>
                <div class="stat-label">Low Stock</div>
                <div class="stat-sub">Below reorder</div>
            </a>
            <a href="inventory.php?tab=equipment&stock=out&branch=<?= $selected_branch_id ?>" class="stat-card red">
                <span class="stat-icon"><i class="fas fa-times-circle"></i></span>
                <div class="stat-number"><?= $equip_out_of_stock ?></div>
                <div class="stat-label">Out of Stock</div>
                <div class="stat-sub">Qty = 0</div>
            </a>
            <a href="inventory.php?tab=equipment&expiry=expiring&branch=<?= $selected_branch_id ?>" class="stat-card teal">
                <span class="stat-icon"><i class="fas fa-clock"></i></span>
                <div class="stat-number"><?= $equip_expiring ?></div>
                <div class="stat-label">Expiring Soon</div>
                <div class="stat-sub">Within 30 days</div>
            </a>
            <a href="inventory.php?tab=equipment&expiry=expired&branch=<?= $selected_branch_id ?>" class="stat-card red">
                <span class="stat-icon"><i class="fas fa-skull"></i></span>
                <div class="stat-number"><?= $equip_expired ?></div>
                <div class="stat-label">Has Expired</div>
                <div class="stat-sub">Some batches</div>
            </a>
            <a href="inventory.php?tab=equipment&status=active&branch=<?= $selected_branch_id ?>" class="stat-card green">
                <span class="stat-icon"><i class="fas fa-check-circle"></i></span>
                <div class="stat-number"><?= $equip_in_stock ?></div>
                <div class="stat-label">In Stock</div>
                <div class="stat-sub">Available</div>
            </a>
            <a href="inventory.php?tab=equipment&status=inactive&branch=<?= $selected_branch_id ?>" class="stat-card purple">
                <span class="stat-icon"><i class="fas fa-archive"></i></span>
                <div class="stat-number"><?= $equip_inactive ?></div>
                <div class="stat-label">Inactive</div>
                <div class="stat-sub">No active batches</div>
            </a>
            <a href="inventory.php?tab=equipment&branch=<?= $selected_branch_id ?>" class="stat-card indigo">
                <span class="stat-icon"><i class="fas fa-coins"></i></span>
                <div class="stat-number">TSh <?= formatMoneyShort($equip_value) ?></div>
                <div class="stat-label">Total Value</div>
                <div class="stat-sub">Inventory worth</div>
            </a>
        </div>

        <div class="card">
            <div class="filter-group">
                <a href="inventory.php?tab=equipment&branch=<?= $selected_branch_id ?>" class="filter-btn <?= empty($status_filter) && empty($stock_filter) && empty($expiry_filter) ? 'active' : '' ?>">All</a>
                <a href="inventory.php?tab=equipment&status=active&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $status_filter === 'active' ? 'active' : '' ?>">Active</a>
                <a href="inventory.php?tab=equipment&status=inactive&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $status_filter === 'inactive' ? 'active' : '' ?>">Inactive</a>
                <a href="inventory.php?tab=equipment&stock=low&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $stock_filter === 'low' ? 'active' : '' ?>">Low Stock</a>
                <a href="inventory.php?tab=equipment&stock=out&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $stock_filter === 'out' ? 'active' : '' ?>">Out of Stock</a>
                <a href="inventory.php?tab=equipment&expiry=expiring&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $expiry_filter === 'expiring' ? 'active' : '' ?>">Expiring Soon</a>
                <a href="inventory.php?tab=equipment&expiry=expired&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $expiry_filter === 'expired' ? 'active' : '' ?>">Has Expired</a>
            </div>
        </div>

        <div class="card">
            <div class="table-header-bar">
                <div class="table-header-left">
                    <div class="table-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="equipSearchInput" placeholder="🔍 Auto-search equipment..." autocomplete="off">
                    </div>
                    <h3 class="card-title" style="margin:0;">
                        <i class="fas fa-list" style="color:var(--purple);"></i> 
                        <span class="result-count" id="equipCountDisplay">(<strong><?= count($equipment) ?></strong> entries)</span>
                    </h3>
                    <span class="search-results-info" id="equipSearchInfo"><strong id="equipSearchCount">0</strong> match</span>
                </div>
                <div class="table-header-right">
                    <button class="scroll-btn-header" id="equipScrollBtnLeft" onclick="scrollEquipTable('left')"><i class="fas fa-chevron-left"></i></button>
                    <button class="scroll-btn-header" id="equipScrollBtnRight" onclick="scrollEquipTable('right')"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            
            <?php if (count($equipment) > 0): ?>
                <div class="table-scroll-container" id="equipTableWrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="col-sno">#</th>
                                <th class="col-name">Name</th>
                                <th class="col-category">Category</th>
                                <th class="col-branch">Branch</th>
                                <th class="col-qty">Qty</th>
                                <th class="col-reorder">Reorder</th>
                                <th class="col-stock">Stock</th>
                                <th class="col-price">Price</th>
                                <th class="col-expiry">Expiry</th>
                                <th class="col-days">Days</th>
                                <th class="col-batch">Batch</th>
                                <th class="col-status">Status</th>
                                <th class="col-active">Active</th>
                                <th class="col-added-by">Added By</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="equipTableBody">
                            <?php $counter = 1; ?>
                            <?php foreach ($equipment as $item): ?>
                                <?php
                                    $active_qty = $item['total_quantity'] ?? 0;
                                    $branch_name = $item['branch_name'] ?? 'Unknown';
                                    $batch_ids = $item['batch_ids'] ?? '';
                                    $first_batch_id = $batch_ids ? explode(',', $batch_ids)[0] : 0;
                                    $category_display = !empty($item['category']) ? $item['category'] : 'N/A';
                                    
                                    $stock_status = 'ok'; $stock_label = 'In Stock';
                                    if ($active_qty <= 0) { $stock_status = 'out'; $stock_label = 'Out of Stock'; }
                                    elseif ($active_qty <= $item['reorder_level']) { $stock_status = 'low'; $stock_label = 'Low Stock'; }
                                    
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
                                    
                                    $search_data = strtolower(($item['equipment_name'] ?? '') . ' ' . $category_display . ' ' . $branch_name . ' ' . $added_by_display . ' ' . ($first_batch ?? '') . ' ' . $stock_label);
                                    
                                    $view_data_json = htmlspecialchars(json_encode([
                                        'id' => $item['id'], 'name' => $item['equipment_name'], 'category' => $category_display,
                                        'unit' => $item['unit'] ?? 'pcs', 'reorder_level' => $item['reorder_level'],
                                        'selling_price' => $item['selling_price'] ?? 0, 'supplier' => $item['supplier'] ?? 'N/A',
                                        'status' => $display_status, 'active_qty' => $active_qty,
                                        'branch_id' => $item['branch_id'], 'branch_name' => $branch_name,
                                        'batch_ids' => $batch_ids, 'batch_numbers' => $batch_numbers,
                                        'batch_quantities' => $item['batch_quantities'] ?? '',
                                        'batch_expiries' => $item['batch_expiries'] ?? '',
                                        'batch_statuses' => $item['batch_statuses'] ?? ''
                                    ]), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="equip-row" data-search="<?= htmlspecialchars($search_data) ?>">
                                    <td class="col-sno"><?= $counter++ ?></td>
                                    <td class="col-name">
                                        <strong><?= htmlspecialchars($item['equipment_name']) ?></strong>
                                        <?php if ($batch_count > 1): ?>
                                            <span style="font-size:0.5rem;background:var(--primary-light);color:var(--primary);padding:0 6px;border-radius:8px;"><?= $batch_count ?> batches</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-category"><?= htmlspecialchars($category_display) ?></td>
                                    <td class="col-branch"><span style="font-weight:600;color:var(--primary);font-size:0.65rem;">🏥 <?= htmlspecialchars($branch_name) ?></span></td>
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
                                            <span class="expiry-badge <?= $expiry_status ?>"><?= date('d/m/Y', strtotime($expiry_date)) ?></span>
                                        <?php else: ?>
                                            <span class="expiry-badge no-expiry"><i class="fas fa-infinity"></i> No Expiry</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-days">
                                        <?php if (!empty($expiry_date) && $expiry_date !== '0000-00-00' && $days !== '-'): ?>
                                            <span class="days-remaining <?= $days_class ?>">
                                                <?php if ($days < 0): ?>EXP
                                                <?php elseif ($days <= 30): ?><?= $days ?>d
                                                <?php else: ?><?= $days ?>d<?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="days-remaining forever">∞</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-batch">
                                        <?php if (!empty($first_batch)): ?>
                                            <span class="batch-number"><?= htmlspecialchars($first_batch) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);font-size:0.6rem;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-status"><span class="status-badge <?= $display_status ?>"><?= ucfirst($display_status) ?></span></td>
                                    <td class="col-active"><span class="stock-badge <?= $active_qty > 0 ? 'ok' : 'out' ?>"><?= $active_qty > 0 ? 'YES' : 'NO' ?></span></td>
                                    <td class="col-added-by"><span class="added-by-tag"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($added_by_display) ?></span></td>
                                    <td class="col-actions">
                                        <div class="action-group">
                                            <button class="action-btn view" onclick='openViewModal(<?= $view_data_json ?>, "equipment")'><i class="fas fa-eye"></i> View</button>
                                            <button class="action-btn edit" onclick="openEditEquipmentModal(<?= $first_batch_id ?>, '<?= htmlspecialchars($item['equipment_name']) ?>')"><i class="fas fa-edit"></i> Edit</button>
                                            <button class="action-btn delete" onclick="deleteEquipment(<?= $first_batch_id ?>, '<?= addslashes($item['equipment_name']) ?>')"><i class="fas fa-trash"></i> Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="no-results-row" id="equipNoResults" style="display:none;"><td colspan="15"><i class="fas fa-search-minus"></i><p>No equipment match</p></td></tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:30px;color:var(--text-secondary);">
                    <i class="fas fa-tools" style="font-size:2rem;color:var(--border-color);display:block;margin-bottom:8px;"></i>
                    <p>No equipment found in <?= $filter_by_branch ? htmlspecialchars($display_branch_name) : 'this branch' ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span>|</span> Admin Inventory
            <span>|</span>
            <strong><?= $total_medicines + $total_equipment ?></strong> entries · TSh <strong><?= formatMoney($total_inventory_value) ?></strong>
            <span>|</span> &copy; <?= date('Y') ?>
        </p>
    </footer>

<?php endif; ?>

</main>

<!-- VIEW MODAL -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-eye"></i> <span id="viewModalName"></span></div>
            <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
        </div>
        <div id="viewModalBody"></div>
        <div class="form-actions">
            <button class="btn-cancel" onclick="closeModal('viewModal')"><i class="fas fa-times"></i> Close</button>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-edit"></i> Edit - <span id="editModalName"></span></div>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <div id="editModalBody"></div>
    </div>
</div>

<script>
// ================================================================
// DARK MODE
// ================================================================
(function() {
    var toggle = document.getElementById('darkModeToggle');
    var icon = document.getElementById('darkIcon');
    var text = document.getElementById('darkText');
    var html = document.documentElement;
    
    if (localStorage.getItem('darkMode') === 'true') {
        html.setAttribute('data-theme', 'dark');
        if (icon) icon.className = 'fas fa-sun';
        if (text) text.textContent = 'Light';
    }
    
    toggle?.addEventListener('click', function() {
        if (html.getAttribute('data-theme') === 'dark') {
            html.removeAttribute('data-theme');
            if (icon) icon.className = 'fas fa-moon';
            if (text) text.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            html.setAttribute('data-theme', 'dark');
            if (icon) icon.className = 'fas fa-sun';
            if (text) text.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });
})();

// ================================================================
// CLOCK
// ================================================================
function updateClock() {
    var n = new Date();
    var d = n.toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric', year:'numeric' });
    var tm = n.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true });
    var el = document.getElementById('currentDateTime');
    if (el) el.textContent = d + ' • ' + tm;
}
updateClock();
setInterval(updateClock, 1000);

// ================================================================
// DELETE
// ================================================================
function deleteMedicine(id, name) {
    if (confirm('Delete this batch of "' + name + '"?\nThis cannot be undone!')) {
        var f = document.createElement('form');
        f.method = 'POST'; f.action = window.location.href;
        f.innerHTML = '<input type="hidden" name="action" value="delete_medicine"><input type="hidden" name="id" value="' + id + '"><input type="hidden" name="confirmed" value="1">';
        document.body.appendChild(f); f.submit();
    }
}

function deleteEquipment(id, name) {
    if (confirm('Delete this batch of "' + name + '"?\nThis cannot be undone!')) {
        var f = document.createElement('form');
        f.method = 'POST'; f.action = window.location.href;
        f.innerHTML = '<input type="hidden" name="action" value="delete_equipment"><input type="hidden" name="id" value="' + id + '"><input type="hidden" name="confirmed" value="1">';
        document.body.appendChild(f); f.submit();
    }
}

// ================================================================
// BRANCH SWITCHER
// ================================================================
function switchBranch(id) {
    var url = new URL(window.location.href);
    url.searchParams.set('branch', id);
    url.searchParams.delete('tab');
    url.searchParams.delete('status');
    url.searchParams.delete('stock');
    url.searchParams.delete('expiry');
    url.searchParams.delete('category');
    url.searchParams.delete('manage');
    window.location.href = url.toString();
}

// ================================================================
// TAB SWITCHING
// ================================================================
function switchTab(tab) {
    var url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    url.searchParams.delete('manage');
    window.location.href = url.toString();
}

// ================================================================
// SCROLL
// ================================================================
function scrollMedTable(dir) {
    var w = document.getElementById('medTableWrap');
    if (w) w.scrollBy({ left: dir === 'left' ? -400 : 400, behavior: 'smooth' });
}
function scrollEquipTable(dir) {
    var w = document.getElementById('equipTableWrap');
    if (w) w.scrollBy({ left: dir === 'left' ? -400 : 400, behavior: 'smooth' });
}
function updateMedScrollBtns() {
    var w = document.getElementById('medTableWrap');
    var l = document.getElementById('medScrollBtnLeft');
    var r = document.getElementById('medScrollBtnRight');
    if (!w || !l || !r) return;
    var sl = w.scrollLeft, ms = w.scrollWidth - w.clientWidth;
    l.disabled = sl <= 5; r.disabled = sl >= ms - 5 || ms <= 0;
}
function updateEquipScrollBtns() {
    var w = document.getElementById('equipTableWrap');
    var l = document.getElementById('equipScrollBtnLeft');
    var r = document.getElementById('equipScrollBtnRight');
    if (!w || !l || !r) return;
    var sl = w.scrollLeft, ms = w.scrollWidth - w.clientWidth;
    l.disabled = sl <= 5; r.disabled = sl >= ms - 5 || ms <= 0;
}

// ================================================================
// AUTO SEARCH
// ================================================================
document.getElementById('medSearchInput')?.addEventListener('input', function() {
    var q = this.value.toLowerCase().trim();
    var rows = document.querySelectorAll('.med-row');
    var total = rows.length, v = 0;
    rows.forEach(function(r) {
        var s = r.getAttribute('data-search') || '';
        if (q === '' || s.includes(q)) { r.style.display = ''; v++; } else { r.style.display = 'none'; }
    });
    var cd = document.getElementById('medCountDisplay');
    if (cd) cd.innerHTML = q === '' ? '(<strong>' + total + '</strong> entries)' : '(<strong>' + v + '</strong> of ' + total + ')';
    var si = document.getElementById('medSearchInfo');
    var sc = document.getElementById('medSearchCount');
    if (si && sc) { si.style.display = q === '' ? 'none' : 'inline-flex'; sc.textContent = v; }
    document.getElementById('medNoResults').style.display = (v === 0 && q !== '') ? '' : 'none';
});

document.getElementById('equipSearchInput')?.addEventListener('input', function() {
    var q = this.value.toLowerCase().trim();
    var rows = document.querySelectorAll('.equip-row');
    var total = rows.length, v = 0;
    rows.forEach(function(r) {
        var s = r.getAttribute('data-search') || '';
        if (q === '' || s.includes(q)) { r.style.display = ''; v++; } else { r.style.display = 'none'; }
    });
    var cd = document.getElementById('equipCountDisplay');
    if (cd) cd.innerHTML = q === '' ? '(<strong>' + total + '</strong> entries)' : '(<strong>' + v + '</strong> of ' + total + ')';
    var si = document.getElementById('equipSearchInfo');
    var sc = document.getElementById('equipSearchCount');
    if (si && sc) { si.style.display = q === '' ? 'none' : 'inline-flex'; sc.textContent = v; }
    document.getElementById('equipNoResults').style.display = (v === 0 && q !== '') ? '' : 'none';
});

document.addEventListener('DOMContentLoaded', function() {
    var m = document.getElementById('medTableWrap');
    var e = document.getElementById('equipTableWrap');
    if (m) { m.addEventListener('scroll', updateMedScrollBtns); setTimeout(updateMedScrollBtns, 200); }
    if (e) { e.addEventListener('scroll', updateEquipScrollBtns); setTimeout(updateEquipScrollBtns, 200); }
});

// ================================================================
// MODAL
// ================================================================
function closeModal(id) {
    var m = document.getElementById(id);
    if (m) { m.classList.remove('show'); document.body.style.overflow = ''; }
}
document.querySelectorAll('.modal-overlay').forEach(function(m) {
    m.addEventListener('click', function(e) {
        if (e.target === this) { this.classList.remove('show'); document.body.style.overflow = ''; }
    });
});

function openViewModal(data, type) {
    var m = document.getElementById('viewModal');
    if (!m) return;
    document.getElementById('viewModalName').textContent = data.name || 'Unknown';
    
    var batchesHtml = '';
    var bIds = data.batch_ids ? data.batch_ids.split(',') : [];
    var bNums = data.batch_numbers ? data.batch_numbers.split('|') : [];
    var bQtys = data.batch_quantities ? data.batch_quantities.split('|') : [];
    var bExps = data.batch_expiries ? data.batch_expiries.split('|') : [];
    var bStats = data.batch_statuses ? data.batch_statuses.split('|') : [];
    
    for (var i = 0; i < bIds.length; i++) {
        var bId = bIds[i] || 0, bNum = bNums[i] || 'N/A', q = bQtys[i] || 0, exp = bExps[i] || '', st = bStats[i] || 'active';
        var expDisp = 'No Expiry', expCls = 'no-expiry', dL = '∞', dCls = 'forever', stLbl = 'Active', stCls = 'active';
        
        if (exp && exp !== '0000-00-00') {
            var eD = new Date(exp), today = new Date();
            var diff = Math.ceil((eD - today) / (1000 * 60 * 60 * 24));
            dL = diff;
            if (diff < 0) { expCls = 'expired'; dCls = 'danger'; stLbl = 'Expired'; stCls = 'inactive'; }
            else if (diff <= 30) { expCls = 'expiring'; dCls = 'warning'; stLbl = 'Expiring Soon'; }
            else { expCls = 'valid'; dCls = 'good'; stLbl = 'Valid'; }
            expDisp = new Date(exp).toLocaleDateString('en-GB');
        }
        if (st === 'inactive') { stLbl = 'Inactive'; stCls = 'inactive'; }
        
        batchesHtml += '<tr style="border-bottom:1px solid var(--border-color);">'
            + '<td style="padding:5px;"><span class="batch-number">' + bNum + '</span></td>'
            + '<td style="text-align:center;font-weight:600;">' + q + '</td>'
            + '<td><span class="expiry-badge ' + expCls + '">' + expDisp + '</span></td>'
            + '<td style="text-align:center;"><span class="days-remaining ' + dCls + '">' + (dL === '∞' ? '∞' : (dL < 0 ? 'EXP' : dL + 'd')) + '</span></td>'
            + '<td style="text-align:center;"><span class="status-badge ' + stCls + '">' + stLbl + '</span></td>'
            + '<td style="text-align:center;">'
                + '<button class="action-btn-sm edit" onclick="closeModal(\'viewModal\');openEdit' + (type === 'medicine' ? 'Medicine' : 'Equipment') + 'Modal(' + bId + ', \'' + (data.name || '').replace(/'/g, "\\'") + '\')"><i class="fas fa-edit"></i></button> '
                + '<button class="action-btn-sm delete" onclick="closeModal(\'viewModal\');' + (type === 'medicine' ? 'deleteMedicine' : 'deleteEquipment') + '(' + bId + ', \'' + (data.name || '').replace(/'/g, "\\'") + '\')"><i class="fas fa-trash"></i></button>'
            + '</td></tr>';
    }
    
    if (bIds.length === 0) {
        batchesHtml = '<tr><td colspan="6" style="text-align:center;padding:10px;color:var(--text-secondary);">No batches</td></tr>';
    }
    
    var total = data.active_qty || 0;
    var stockBadge = 'ok', stockText = 'In Stock';
    if (total <= 0) { stockBadge = 'out'; stockText = 'Out of Stock'; }
    else if (total <= (data.reorder_level || 0)) { stockBadge = 'low'; stockText = 'Low Stock'; }
    
    var html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">'
        + '<div style="grid-column:1/-1;padding:8px;background:var(--bg-body);border-radius:6px;"><div style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Name</div><div style="font-size:0.85rem;font-weight:600;">' + (data.name || 'N/A') + '</div></div>'
        + '<div style="padding:8px;background:var(--bg-body);border-radius:6px;"><div style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Category</div><div style="font-size:0.85rem;font-weight:600;">' + (data.category || 'N/A') + '</div></div>'
        + '<div style="padding:8px;background:var(--bg-body);border-radius:6px;"><div style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Unit</div><div style="font-size:0.85rem;font-weight:600;">' + (data.unit || 'pcs') + '</div></div>'
        + '<div style="padding:8px;background:var(--bg-body);border-radius:6px;"><div style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Total Qty</div><div style="font-size:0.85rem;font-weight:600;">' + total + ' <span class="stock-badge ' + stockBadge + '">' + stockText + '</span></div></div>'
        + '<div style="padding:8px;background:var(--bg-body);border-radius:6px;"><div style="font-size:0.55rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Selling Price</div><div style="font-size:0.85rem;font-weight:600;">' + (data.selling_price > 0 ? 'TSh ' + Number(data.selling_price).toLocaleString() : 'FREE') + '</div></div>'
        + '</div>'
        + '<div style="font-size:0.75rem;font-weight:600;margin-bottom:6px;"><i class="fas fa-layer-group"></i> Batches (' + bIds.length + ')</div>'
        + '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:0.7rem;">'
        + '<thead><tr style="background:var(--primary);color:white;">'
        + '<th style="padding:5px;font-size:0.55rem;text-transform:uppercase;text-align:left;">Batch</th>'
        + '<th style="padding:5px;font-size:0.55rem;text-transform:uppercase;text-align:center;">Qty</th>'
        + '<th style="padding:5px;font-size:0.55rem;text-transform:uppercase;text-align:left;">Expiry</th>'
        + '<th style="padding:5px;font-size:0.55rem;text-transform:uppercase;text-align:center;">Days</th>'
        + '<th style="padding:5px;font-size:0.55rem;text-transform:uppercase;text-align:center;">Status</th>'
        + '<th style="padding:5px;font-size:0.55rem;text-transform:uppercase;text-align:center;">Actions</th>'
        + '</tr></thead><tbody>' + batchesHtml + '</tbody></table></div>';
    
    document.getElementById('viewModalBody').innerHTML = html;
    m.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function openEditMedicineModal(id, name) {
    var m = document.getElementById('editModal');
    if (!m) return;
    document.getElementById('editModalName').textContent = name;
    document.getElementById('editModalBody').innerHTML = '<div style="text-align:center;padding:30px;"><i class="fas fa-spinner fa-spin" style="font-size:1.8rem;color:var(--primary);"></i></div>';
    m.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    var fd = new FormData();
    fd.append('action', 'get_edit_medicine_data');
    fd.append('id', id);
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.text(); })
        .then(function(h) { document.getElementById('editModalBody').innerHTML = h; });
}

function openEditEquipmentModal(id, name) {
    var m = document.getElementById('editModal');
    if (!m) return;
    document.getElementById('editModalName').textContent = name;
    document.getElementById('editModalBody').innerHTML = '<div style="text-align:center;padding:30px;"><i class="fas fa-spinner fa-spin" style="font-size:1.8rem;color:var(--primary);"></i></div>';
    m.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    var fd = new FormData();
    fd.append('action', 'get_edit_equipment_data');
    fd.append('id', id);
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.text(); })
        .then(function(h) { document.getElementById('editModalBody').innerHTML = h; });
}

function toggleCategoryEdit() {
    var select = document.getElementById('editCategorySelect');
    var manual = document.getElementById('editCategoryManual');
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

function toggleEquipCategoryEdit() {
    var select = document.getElementById('editEquipCategorySelect');
    var manual = document.getElementById('editEquipCategoryManual');
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

function toggleUnitEdit() {
    var select = document.getElementById('editUnitSelect');
    var manual = document.getElementById('editUnitManual');
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

function toggleEquipUnitEdit() {
    var select = document.getElementById('editEquipUnitSelect');
    var manual = document.getElementById('editEquipUnitManual');
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

setTimeout(function() {
    var m = document.getElementById('messageBox');
    if (m) m.style.display = 'none';
}, 5000);

console.log('%c📦 Admin Inventory - ✅ EMBEDDED HEADER (Same as shared)', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Embedded header HTML in file', 'font-size:13px;color:#34D399;');
console.log('%c✅ Uses SHARED admin_sidebar.php', 'font-size:13px;color:#34D399;');
console.log('%c✅ Header style matches shared admin_header.php', 'font-size:13px;color:#34D399;');
</script>

</body>
</html>