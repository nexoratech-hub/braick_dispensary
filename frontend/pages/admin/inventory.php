<?php
// ================================================================
// FILE: frontend/pages/admin/inventory.php
// ADMIN - COMPLETE INVENTORY (Medicine & Equipment)
// WITH PURCHASE INTEGRATION - Shows list of IN_PROGRESS purchases
// FIXED: Equipment without expiry date now display correctly
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
// GET CATEGORIES
// ================================================================
$med_categories = [];
$stmt = $db->query("SELECT DISTINCT category FROM medications_inventory WHERE category IS NOT NULL AND category != '' AND category != '__other__' ORDER BY category");
$med_categories = $stmt->fetchAll();

$equip_categories = [];
$stmt = $db->query("SELECT DISTINCT category FROM medical_equipment WHERE category IS NOT NULL AND category != '' AND category != '__other__' ORDER BY category");
$equip_categories = $stmt->fetchAll();

// ================================================================
// PROCESS POST REQUESTS
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // UPDATE MEDICINE BATCH
    // ================================================================
    if ($action === 'edit_medicine') {
        $id = (int)($_POST['id'] ?? 0);
        $medication_name = trim($_POST['medication_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        
        // FIX: If category is '__other__', use manual category
        if ($category === '__other__' && !empty($_POST['category_manual'])) {
            $category = trim($_POST['category_manual']);
        }
        if (empty($category)) {
            $category = 'Other';
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
        if (empty($category)) { $errors[] = 'Category is required'; }
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
                    WHERE id = ?
                ");
                $stmt->execute([
                    $medication_name, $category, $unit, $quantity, $reorder_level,
                    $unit_cost, $selling_price, $supplier, $expiry_date, $batch_number,
                    $status, $id
                ]);
                
                $_SESSION['inventory_message'] = "✅ Medicine batch updated successfully!";
                $_SESSION['inventory_message_type'] = 'success';
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
    // UPDATE EQUIPMENT BATCH
    // ================================================================
    if ($action === 'edit_equipment') {
        $id = (int)($_POST['id'] ?? 0);
        $equipment_name = trim($_POST['equipment_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        
        // FIX: If category is '__other__', use manual category
        if ($category === '__other__' && !empty($_POST['category_manual'])) {
            $category = trim($_POST['category_manual']);
        }
        if (empty($category)) {
            $category = 'Other';
        }
        
        $unit = trim($_POST['unit'] ?? 'pcs');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reorder_level = (int)($_POST['reorder_level'] ?? 5);
        $unit_cost = getMoney($_POST['unit_cost'] ?? 0);
        $selling_price = getMoney($_POST['selling_price'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');
        $expiry_date = $_POST['expiry_date'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        $errors = [];
        if (empty($equipment_name)) { $errors[] = 'Equipment name is required'; }
        if (empty($category)) { $errors[] = 'Category is required'; }
        if ($quantity < 0) { $errors[] = 'Quantity cannot be negative'; }
        if ($selling_price < 0) { $errors[] = 'Selling price cannot be negative'; }
        if (!empty($expiry_date) && strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
            $errors[] = 'Expiry date cannot be in the past';
        }
        
        if (empty($errors) && $id > 0) {
            try {
                $stmt = $db->prepare("
                    UPDATE medical_equipment SET
                        equipment_name = ?,
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
                    WHERE id = ?
                ");
                $stmt->execute([
                    $equipment_name, $category, $unit, $quantity, $reorder_level,
                    $unit_cost, $selling_price, $supplier, $expiry_date, $batch_number,
                    $status, $id
                ]);
                
                $_SESSION['inventory_message'] = "✅ Equipment batch updated successfully!";
                $_SESSION['inventory_message_type'] = 'success';
                header('Location: inventory.php?tab=equipment&updated=1&branch=' . $selected_branch_id);
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
        $confirmed = isset($_POST['confirmed']) ? (int)$_POST['confirmed'] : 0;
        
        if ($confirmed == 1 && $id > 0) {
            try {
                $stmt = $db->prepare("SELECT id FROM medications_inventory WHERE id = ?");
                $stmt->execute([$id]);
                $exists = $stmt->fetch();
                
                if ($exists) {
                    $stmt = $db->prepare("DELETE FROM medications_inventory WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $_SESSION['inventory_message'] = "✅ Medicine batch deleted successfully!";
                    $_SESSION['inventory_message_type'] = 'success';
                    header('Location: inventory.php?tab=medicines&deleted=1&branch=' . $selected_branch_id);
                    exit;
                } else {
                    $message = "❌ Batch not found.";
                    $message_type = 'error';
                }
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "❌ Deletion not confirmed";
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // DELETE EQUIPMENT BATCH
    // ================================================================
    if ($action === 'delete_equipment') {
        $id = (int)($_POST['id'] ?? 0);
        $confirmed = isset($_POST['confirmed']) ? (int)$_POST['confirmed'] : 0;
        
        if ($confirmed == 1 && $id > 0) {
            try {
                $stmt = $db->prepare("SELECT id FROM medical_equipment WHERE id = ?");
                $stmt->execute([$id]);
                $exists = $stmt->fetch();
                
                if ($exists) {
                    $stmt = $db->prepare("DELETE FROM medical_equipment WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $_SESSION['inventory_message'] = "✅ Equipment batch deleted successfully!";
                    $_SESSION['inventory_message_type'] = 'success';
                    header('Location: inventory.php?tab=equipment&deleted=1&branch=' . $selected_branch_id);
                    exit;
                } else {
                    $message = "❌ Batch not found.";
                    $message_type = 'error';
                }
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "❌ Deletion not confirmed";
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // GET EDIT DATA FOR AJAX - MEDICINE
    // ================================================================
    if ($action === 'get_edit_medicine_data') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM medications_inventory WHERE id = ?");
            $stmt->execute([$id]);
            $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($edit_data) {
                $branches = [];
                $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
                $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $category_val = $edit_data['category'] ?? '';
                $is_custom_category = !in_array($category_val, $predefined_med_categories) && !empty($category_val) && $category_val !== 'Other';
                ?>
                <form method="POST" action="" class="edit-form">
                    <input type="hidden" name="action" value="edit_medicine">
                    <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                    
                    <div class="form-grid">
                        <div class="full-width form-row">
                            <label class="form-label">Medicine Name <span class="required">*</span></label>
                            <input type="text" name="medication_name" class="form-control" 
                                   value="<?= htmlspecialchars($edit_data['medication_name']) ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Category <span class="required">*</span></label>
                            <div class="category-input-group">
                                <select name="category" id="editCategorySelect" class="form-control" required>
                                    <option value="">Select</option>
                                    <?php foreach ($predefined_med_categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category_val == $cat ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="__other__" <?= $is_custom_category ? 'selected' : '' ?>>
                                        + Other
                                    </option>
                                </select>
                                <input type="text" name="category_manual" id="editCategoryManual" class="form-control" 
                                       placeholder="Custom category..." 
                                       style="display:<?= $is_custom_category ? 'block' : 'none' ?>;"
                                       value="<?= $is_custom_category ? htmlspecialchars($category_val) : '' ?>">
                                <button type="button" class="btn-toggle" onclick="toggleCategoryEdit()">
                                    <i class="fas fa-edit"></i> Manual
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Unit</label>
                            <select name="unit" class="form-control">
                                <option value="pcs" <?= $edit_data['unit'] == 'pcs' ? 'selected' : '' ?>>Pieces (pcs)</option>
                                <option value="tablets" <?= $edit_data['unit'] == 'tablets' ? 'selected' : '' ?>>Tablets</option>
                                <option value="capsules" <?= $edit_data['unit'] == 'capsules' ? 'selected' : '' ?>>Capsules</option>
                                <option value="ml" <?= $edit_data['unit'] == 'ml' ? 'selected' : '' ?>>Milliliters (ml)</option>
                                <option value="mg" <?= $edit_data['unit'] == 'mg' ? 'selected' : '' ?>>Milligrams (mg)</option>
                                <option value="g" <?= $edit_data['unit'] == 'g' ? 'selected' : '' ?>>Grams (g)</option>
                                <option value="bottle" <?= $edit_data['unit'] == 'bottle' ? 'selected' : '' ?>>Bottle</option>
                                <option value="box" <?= $edit_data['unit'] == 'box' ? 'selected' : '' ?>>Box</option>
                                <option value="strip" <?= $edit_data['unit'] == 'strip' ? 'selected' : '' ?>>Strip</option>
                                <option value="vial" <?= $edit_data['unit'] == 'vial' ? 'selected' : '' ?>>Vial</option>
                                <option value="sachet" <?= $edit_data['unit'] == 'sachet' ? 'selected' : '' ?>>Sachet</option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Quantity <span class="required">*</span></label>
                            <input type="number" name="quantity" class="form-control" value="<?= $edit_data['quantity'] ?>" min="0" required>
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Reorder Level <span class="required">*</span></label>
                            <input type="number" name="reorder_level" class="form-control" value="<?= $edit_data['reorder_level'] ?>" min="0" required>
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Buying Price (TSh)</label>
                            <input type="text" name="unit_cost" class="form-control money-input" value="<?= number_format($edit_data['unit_cost'] ?? 0, 0) ?>">
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Selling Price (TSh) <span class="required">*</span></label>
                            <input type="text" name="selling_price" class="form-control money-input" value="<?= number_format($edit_data['selling_price'] ?? 0, 0) ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Supplier</label>
                            <input type="text" name="supplier" class="form-control" value="<?= htmlspecialchars($edit_data['supplier'] ?? '') ?>">
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control" 
                                   value="<?= $edit_data['expiry_date'] && $edit_data['expiry_date'] !== '0000-00-00' ? $edit_data['expiry_date'] : '' ?>">
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
                <?php
                exit;
            }
        }
        exit;
    }
    
    // ================================================================
    // GET EDIT DATA FOR AJAX - EQUIPMENT
    // ================================================================
    if ($action === 'get_edit_equipment_data') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM medical_equipment WHERE id = ?");
            $stmt->execute([$id]);
            $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($edit_data) {
                $branches = [];
                $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
                $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $category_val = $edit_data['category'] ?? '';
                $is_custom_category = !in_array($category_val, $predefined_equip_categories) && !empty($category_val) && $category_val !== 'Other';
                ?>
                <form method="POST" action="" class="edit-form">
                    <input type="hidden" name="action" value="edit_equipment">
                    <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                    
                    <div class="form-grid">
                        <div class="full-width form-row">
                            <label class="form-label">Equipment Name <span class="required">*</span></label>
                            <input type="text" name="equipment_name" class="form-control" 
                                   value="<?= htmlspecialchars($edit_data['equipment_name']) ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Category <span class="required">*</span></label>
                            <div class="category-input-group">
                                <select name="category" id="editEquipCategorySelect" class="form-control" required>
                                    <option value="">Select</option>
                                    <?php foreach ($predefined_equip_categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category_val == $cat ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="__other__" <?= $is_custom_category ? 'selected' : '' ?>>
                                        + Other
                                    </option>
                                </select>
                                <input type="text" name="category_manual" id="editEquipCategoryManual" class="form-control" 
                                       placeholder="Custom category..." 
                                       style="display:<?= $is_custom_category ? 'block' : 'none' ?>;"
                                       value="<?= $is_custom_category ? htmlspecialchars($category_val) : '' ?>">
                                <button type="button" class="btn-toggle" onclick="toggleEquipCategoryEdit()">
                                    <i class="fas fa-edit"></i> Manual
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Unit</label>
                            <select name="unit" class="form-control">
                                <option value="pcs" <?= $edit_data['unit'] == 'pcs' ? 'selected' : '' ?>>Pieces (pcs)</option>
                                <option value="set" <?= $edit_data['unit'] == 'set' ? 'selected' : '' ?>>Set</option>
                                <option value="box" <?= $edit_data['unit'] == 'box' ? 'selected' : '' ?>>Box</option>
                                <option value="pack" <?= $edit_data['unit'] == 'pack' ? 'selected' : '' ?>>Pack</option>
                                <option value="each" <?= $edit_data['unit'] == 'each' ? 'selected' : '' ?>>Each</option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Quantity <span class="required">*</span></label>
                            <input type="number" name="quantity" class="form-control" value="<?= $edit_data['quantity'] ?>" min="0" required>
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Reorder Level <span class="required">*</span></label>
                            <input type="number" name="reorder_level" class="form-control" value="<?= $edit_data['reorder_level'] ?>" min="0" required>
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Buying Price (TSh)</label>
                            <input type="text" name="unit_cost" class="form-control money-input" value="<?= number_format($edit_data['unit_cost'] ?? 0, 0) ?>">
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Selling Price (TSh) <span class="required">*</span></label>
                            <input type="text" name="selling_price" class="form-control money-input" value="<?= number_format($edit_data['selling_price'] ?? 0, 0) ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Supplier</label>
                            <input type="text" name="supplier" class="form-control" value="<?= htmlspecialchars($edit_data['supplier'] ?? '') ?>">
                        </div>
                        
                        <div class="form-row">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control" 
                                   value="<?= $edit_data['expiry_date'] && $edit_data['expiry_date'] !== '0000-00-00' ? $edit_data['expiry_date'] : '' ?>">
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
                <?php
                exit;
            }
        }
        exit;
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
        b.name as branch_name,
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
    LEFT JOIN branches b ON m.branch_id = b.id
    WHERE 1=1
";

$med_params = [];

// Branch filter
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $med_query .= " AND m.branch_id = ?";
    $med_params[] = (int)$selected_branch_id;
}

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
        b.name as branch_name,
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
    LEFT JOIN branches b ON e.branch_id = b.id
    WHERE 1=1
";

$equip_params = [];

// Branch filter
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $equip_query .= " AND e.branch_id = ?";
    $equip_params[] = (int)$selected_branch_id;
}

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
$stats_branch_condition = "";
$stats_params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stats_branch_condition = " AND branch_id = ?";
    $stats_params[] = (int)$selected_branch_id;
}

// Total Medicines - Count unique medication names
$sql = "
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$total_medicines = $stmt->fetch()['count'] ?? 0;

// Total Quantity - SUM all quantities
$sql = "
    SELECT COALESCE(SUM(quantity), 0) as total 
    FROM medications_inventory 
    WHERE status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$total_med_quantity = $stmt->fetch()['total'] ?? 0;

// In Stock - Count medicines with quantity > 0
$sql = "
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    AND quantity > 0
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$med_in_stock = $stmt->fetch()['count'] ?? 0;

// Out of Stock - Count medicines with quantity = 0
$sql = "
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE status = 'active'
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    AND quantity = 0
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$med_out_of_stock = $stmt->fetch()['count'] ?? 0;

// Low Stock - Count medicines with quantity > 0 and quantity <= reorder_level
$sql = "
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    AND quantity > 0 
    AND quantity <= reorder_level
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$med_low_stock = $stmt->fetch()['count'] ?? 0;

// Expiring Soon - Count medicines expiring within 30 days
$sql = "
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE expiry_date IS NOT NULL 
    AND expiry_date != '0000-00-00'
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND status = 'active'
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$med_expiring = $stmt->fetch()['count'] ?? 0;

// Expired - Count medicines with expiry_date < CURDATE
$sql = "
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE expiry_date IS NOT NULL 
    AND expiry_date != '0000-00-00'
    AND expiry_date < CURDATE()
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$med_expired = $stmt->fetch()['count'] ?? 0;

// Inactive - Count medicines with status = 'inactive'
$sql = "
    SELECT COUNT(DISTINCT medication_name) as count 
    FROM medications_inventory 
    WHERE status = 'inactive'
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$med_inactive = $stmt->fetch()['count'] ?? 0;

// Total Value - SUM(quantity * selling_price)
$sql = "
    SELECT COALESCE(SUM(quantity * selling_price), 0) as total_value 
    FROM medications_inventory 
    WHERE status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$med_value = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

// ================================================================
// GET STATISTICS - EQUIPMENT - FIXED
// ================================================================

// Total Equipment - Count unique equipment names
$sql = "
    SELECT COUNT(DISTINCT equipment_name) as count 
    FROM medical_equipment 
    WHERE status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$total_equipment = $stmt->fetch()['count'] ?? 0;

// Total Quantity - SUM all quantities
$sql = "
    SELECT COALESCE(SUM(quantity), 0) as total 
    FROM medical_equipment 
    WHERE status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$total_equip_quantity = $stmt->fetch()['total'] ?? 0;

// In Stock - Count equipment with quantity > 0
$sql = "
    SELECT COUNT(DISTINCT equipment_name) as count 
    FROM medical_equipment 
    WHERE status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    AND quantity > 0
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$equip_in_stock = $stmt->fetch()['count'] ?? 0;

// Out of Stock - Count equipment with quantity = 0
$sql = "
    SELECT COUNT(DISTINCT equipment_name) as count 
    FROM medical_equipment 
    WHERE status = 'active'
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    AND quantity = 0
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$equip_out_of_stock = $stmt->fetch()['count'] ?? 0;

// Low Stock - Count equipment with quantity > 0 and quantity <= reorder_level
$sql = "
    SELECT COUNT(DISTINCT equipment_name) as count 
    FROM medical_equipment 
    WHERE status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    AND quantity > 0 
    AND quantity <= reorder_level
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$equip_low_stock = $stmt->fetch()['count'] ?? 0;

// Expiring Soon - Count equipment expiring within 30 days
$sql = "
    SELECT COUNT(DISTINCT equipment_name) as count 
    FROM medical_equipment 
    WHERE expiry_date IS NOT NULL 
    AND expiry_date != '0000-00-00'
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND status = 'active'
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$equip_expiring = $stmt->fetch()['count'] ?? 0;

// Expired - Count equipment with expiry_date < CURDATE
$sql = "
    SELECT COUNT(DISTINCT equipment_name) as count 
    FROM medical_equipment 
    WHERE expiry_date IS NOT NULL 
    AND expiry_date != '0000-00-00'
    AND expiry_date < CURDATE()
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$equip_expired = $stmt->fetch()['count'] ?? 0;

// Inactive - Count equipment with status = 'inactive'
$sql = "
    SELECT COUNT(DISTINCT equipment_name) as count 
    FROM medical_equipment 
    WHERE status = 'inactive'
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$equip_inactive = $stmt->fetch()['count'] ?? 0;

// Total Value - SUM(quantity * selling_price)
$sql = "
    SELECT COALESCE(SUM(quantity * selling_price), 0) as total_value 
    FROM medical_equipment 
    WHERE status = 'active' 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
    $stats_branch_condition
";
$stmt = $db->prepare($sql);
$stmt->execute($stats_params);
$equip_value = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

$total_inventory_value = $med_value + $equip_value;

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
            WHERE id = ?
        ");
        $stmt->execute([$view_id]);
        $name_row = $stmt->fetch();
        if ($name_row) {
            $view_name = $name_row['medication_name'];
            $stmt = $db->prepare("
                SELECT m.*, u.full_name as added_by_full_name, b.name as branch_name
                FROM medications_inventory m
                LEFT JOIN users u ON m.added_by = u.id
                LEFT JOIN branches b ON m.branch_id = b.id
                WHERE m.medication_name = ?
                ORDER BY m.id ASC
            ");
            $stmt->execute([$view_name]);
            $view_batches = $stmt->fetchAll();
            $view_data = $view_batches[0] ?? null;
        }
    } else {
        $stmt = $db->prepare("
            SELECT equipment_name, added_by, added_by_name 
            FROM medical_equipment 
            WHERE id = ?
        ");
        $stmt->execute([$view_id]);
        $name_row = $stmt->fetch();
        if ($name_row) {
            $view_name = $name_row['equipment_name'];
            $stmt = $db->prepare("
                SELECT e.*, u.full_name as added_by_full_name, b.name as branch_name
                FROM medical_equipment e
                LEFT JOIN users u ON e.added_by = u.id
                LEFT JOIN branches b ON e.branch_id = b.id
                WHERE e.equipment_name = ?
                ORDER BY e.id ASC
            ");
            $stmt->execute([$view_name]);
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
    $stmt->execute([$branch_id_for_query]);
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
    $stmt->execute([$branch_id_for_query]);
    $all_equipment_names = $stmt->fetchAll();
} catch (Exception $e) {
    $all_equipment_names = [];
}

// ================================================================
// BUILD ADD BUTTON URLs - GO TO LIST PAGE (NOT DIRECT JOIN)
// ================================================================
$base_purchase_url = 'purchases.php';

// Medicine Add URL - ALWAYS GO TO LIST
$med_add_url = $base_purchase_url . '?action=list_medicine&type=medicine&branch=' . $selected_branch_id;

// Equipment Add URL - ALWAYS GO TO LIST
$equip_add_url = $base_purchase_url . '?action=list_equipment&type=equipment&branch=' . $selected_branch_id;

// Purchase History URL
$history_url = 'purchase_history.php?branch=' . $selected_branch_id;

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
    <title>Inventory - Braick Dispensary</title>
    
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
        /* BRANCH INFO CARD */
        /* ================================================================ */
        .branch-info-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius-lg);
            padding: 14px 24px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            color: white;
        }
        
        .branch-info-card .branch-title {
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .branch-info-card .branch-title i { font-size: 1.1rem; opacity: 0.9; }
        
        .branch-info-card .branch-stats {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .branch-info-card .branch-stats .stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.7rem;
            opacity: 0.9;
            background: rgba(255,255,255,0.1);
            padding: 3px 10px;
            border-radius: 20px;
        }
        
        .branch-info-card .branch-stats .stat-item strong { color: white; font-weight: 700; }
        
        /* ================================================================ */
        /* PAGE HEADER */
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
            content: ''; position: absolute; top: -60%; right: -10%;
            width: 350px; height: 350px;
            background: rgba(255,255,255,0.05); border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header-box .page-title {
            color: white; font-size: 1.4rem; font-weight: 700;
            display: flex; align-items: center; gap: 8px;
            flex-wrap: wrap; position: relative; z-index: 1;
        }
        
        .page-header-box .page-title .role-badge-display {
            background: rgba(255,255,255,0.2); color: white;
            padding: 2px 10px; border-radius: 20px; font-size: 0.55rem;
            font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header-box .page-title .branch-name-display {
            background: rgba(255,255,255,0.15);
            padding: 2px 12px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 500; color: white;
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
            color: rgba(255,255,255,0.85); font-size: 0.8rem;
            display: flex; align-items: center; gap: 6px;
            flex-wrap: wrap; position: relative; z-index: 1;
            margin-top: 2px;
        }
        
        .page-header-box .page-subtitle strong { color: white; font-weight: 600; }
        
        .page-header-box .header-badge {
            background: rgba(255,255,255,0.12); color: white;
            padding: 2px 10px; border-radius: 20px; font-size: 0.6rem;
            font-weight: 500; backdrop-filter: blur(4px);
            display: inline-flex; align-items: center; gap: 4px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header-box .header-badge i { opacity: 0.8; }
        .page-header-box .header-badge.medicines {
            background: rgba(52, 211, 153, 0.2); border-color: rgba(52, 211, 153, 0.3);
            color: #6EE7B7;
        }
        .page-header-box .header-badge.value {
            background: rgba(251, 191, 36, 0.2); border-color: rgba(251, 191, 36, 0.3);
            color: #FBBF24;
        }
        .page-header-box .header-badge.stock {
            background: rgba(251, 146, 60, 0.2); border-color: rgba(251, 146, 60, 0.3);
            color: #FDBA74;
        }
        .page-header-box .header-badge.expiry {
            background: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.3);
            color: #F87171;
        }
        
        /* ================================================================ */
        /* HEADER ACTIONS - BUTTONS IN ONE ROW - SAME SIZE */
        /* ================================================================ */
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: nowrap;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.82rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: white;
            height: 48px;
            min-width: 180px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .btn-action i { font-size: 0.9rem; }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.25); }
        
        .btn-add-medicine {
            background: var(--success);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }
        .btn-add-medicine:hover { background: var(--success-dark); box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35); }
        
        .btn-add-equipment {
            background: var(--purple);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
        }
        .btn-add-equipment:hover { background: #6D28D9; box-shadow: 0 6px 20px rgba(124, 58, 237, 0.35); }
        
        .btn-purchase-history {
            background: var(--warning);
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
        }
        .btn-purchase-history:hover { background: #B45309; box-shadow: 0 6px 20px rgba(217, 119, 6, 0.35); }
        
        /* ================================================================ */
        /* MESSAGE BOX */
        /* ================================================================ */
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
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.15);
        }
        .message-box.success i { color: #059669; font-size: 1.2rem; }
        
        .message-box.error {
            background: #FEE2E2;
            color: #991B1B;
            border-color: #DC2626;
            border-left: 5px solid #DC2626;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.15);
        }
        .message-box.error i { color: #DC2626; font-size: 1.2rem; }
        
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
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-10px); }
        }
        .message-box.fade-out { animation: fadeOut 0.5s ease forwards; }
        
        /* ================================================================ */
        /* STATS CARDS */
        /* ================================================================ */
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 14px; margin-bottom: 20px;
        }
        
        .stat-card {
            border-radius: 12px; padding: 16px 18px;
            transition: all 0.3s ease; cursor: pointer;
            text-decoration: none; display: block;
            color: white; box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            min-height: 85px; position: relative; overflow: hidden;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            pointer-events: none;
            transition: all 0.5s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .stat-card:hover::after {
            transform: scale(1.3);
            right: -15%;
        }
        
        .stat-card .stat-number { font-size: 1.6rem; font-weight: 700; line-height: 1.2; }
        .stat-card .stat-label { font-size: 0.6rem; color: rgba(255,255,255,0.9); font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 2px; }
        .stat-card .stat-icon { font-size: 1.3rem; opacity: 0.7; float: right; margin-top: -5px; position: relative; z-index: 1; }
        .stat-card .stat-sub { font-size: 0.55rem; color: rgba(255,255,255,0.75); margin-top: 2px; position: relative; z-index: 1; }
        
        .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
        .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
        .stat-card.indigo { background: linear-gradient(135deg, #4F46E5, #4338CA); }
        .stat-card.pink { background: linear-gradient(135deg, #DB2777, #BE185D); }
        
        /* ================================================================ */
        /* CARD */
        /* ================================================================ */
        .card {
            background: var(--bg-card); border-radius: 12px;
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        .card:hover { border-color: var(--primary); box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06); }
        
        .card-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 10px;
            flex-wrap: wrap; gap: 6px;
        }
        
        .card-title { font-size: 0.9rem; font-weight: 600; color: var(--text-primary); }
        .card-title .title-blue { color: var(--primary); }
        .card-title .title-purple { color: var(--purple); }
        .result-count { font-size: 0.75rem; color: var(--text-secondary); }
        .result-count strong { color: var(--primary); }
        
        .filter-group {
            display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 10px;
        }
        
        .filter-btn {
            padding: 3px 12px; border-radius: 14px; font-size: 0.65rem;
            font-weight: 600; border: 2px solid var(--border-color);
            background: transparent; color: var(--text-secondary);
            cursor: pointer; transition: all 0.3s ease;
            text-decoration: none;
        }
        .filter-btn:hover { border-color: var(--primary); color: var(--primary); }
        .filter-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
        .filter-btn.clear-filter { border-color: var(--danger); color: var(--danger); }
        .filter-btn.clear-filter:hover { background: var(--danger); color: white; }
        
        .search-form {
            display: flex; gap: 6px; flex-wrap: wrap; align-items: center;
        }
        
        .search-form input[type="text"], .search-form select {
            padding: 6px 10px; border: 2px solid var(--border-color);
            border-radius: 6px; font-size: 0.75rem;
            background: var(--bg-card); color: var(--text-primary);
            outline: none; transition: all 0.3s ease;
            flex: 1; min-width: 80px; height: 36px;
        }
        .search-form input:focus, .search-form select:focus {
            border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .btn-search {
            padding: 6px 16px; border-radius: 6px; font-weight: 600;
            font-size: 0.75rem; border: none; background: var(--primary);
            color: white; cursor: pointer; transition: all 0.3s ease;
            height: 36px;
        }
        .btn-search:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
        
        .btn-reset {
            padding: 6px 14px; border-radius: 6px; font-weight: 600;
            font-size: 0.75rem; border: 2px solid var(--border-color);
            background: transparent; color: var(--text-secondary);
            cursor: pointer; transition: all 0.3s ease;
            text-decoration: none; height: 36px;
        }
        .btn-reset:hover { border-color: var(--danger); color: var(--danger); }
        
        .table-wrapper { position: relative; }
        .table-scroll-container {
            overflow-x: auto; overflow-y: auto;
            max-height: 450px; scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        .table-scroll-container::-webkit-scrollbar { height: 6px; width: 5px; }
        .table-scroll-container::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
        .table-scroll-container::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        
        .scroll-arrows { display: flex; gap: 5px; align-items: center; }
        .scroll-arrow-btn {
            width: 28px; height: 28px; border-radius: 50%;
            border: 2px solid var(--border-color); background: var(--bg-card);
            color: var(--text-secondary); cursor: pointer;
            transition: all 0.3s ease; display: flex;
            align-items: center; justify-content: center; font-size: 0.7rem;
        }
        .scroll-arrow-btn:hover {
            border-color: var(--primary); color: var(--primary);
            background: var(--primary-light); transform: scale(1.05);
        }
        
        .data-table {
            width: 100%; min-width: 1400px;
            border-collapse: separate; border-spacing: 0;
            font-size: 0.72rem;
        }
        .data-table thead th {
            position: sticky; top: 0; z-index: 10;
            background: var(--primary); color: white;
            padding: 6px 10px; font-size: 0.6rem;
            text-transform: uppercase; letter-spacing: 0.05em;
            font-weight: 700; white-space: nowrap; text-align: left;
        }
        .data-table thead th:first-child { border-radius: 6px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 6px 0 0; }
        .data-table tbody tr:nth-child(even) { background: var(--primary-light); }
        .data-table tbody tr:hover td { background: var(--success-light); }
        [data-theme="dark"] .data-table tbody tr:nth-child(even) { background: #1E293B; }
        [data-theme="dark"] .data-table tbody tr:hover td { background: #1A3A2A; }
        
        .data-table td {
            padding: 5px 8px; border-bottom: 1px solid var(--border-color);
            color: var(--text-primary); vertical-align: middle;
            white-space: nowrap;
        }
        
        .col-sno { width: 30px; text-align: center; }
        .col-name { min-width: 160px; }
        .col-category { min-width: 100px; }
        .col-branch { min-width: 90px; }
        .col-qty { min-width: 60px; text-align: center; }
        .col-reorder { min-width: 60px; text-align: center; }
        .col-stock { min-width: 90px; }
        .col-price { min-width: 100px; font-family: 'Courier New', monospace; }
        .col-expiry { min-width: 85px; }
        .col-days { min-width: 60px; text-align: center; }
        .col-batch { min-width: 100px; }
        .col-status { min-width: 70px; text-align: center; }
        .col-active { min-width: 60px; text-align: center; }
        .col-added-by { min-width: 110px; }
        .col-actions { min-width: 80px; text-align: center; }
        
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
        .stock-badge.low { background: var(--warning-light); color: var(--warning); animation: pulse 1.5s infinite; }
        .stock-badge.out { background: var(--danger-light); color: var(--danger); animation: pulse 1s infinite; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        .expiry-badge {
            padding: 1px 6px; border-radius: 6px; font-size: 0.55rem;
            font-weight: 600; display: inline-flex; align-items: center; gap: 2px;
        }
        .expiry-badge.valid { background: var(--success-light); color: var(--success); }
        .expiry-badge.expiring { background: var(--warning-light); color: var(--warning); animation: pulse 1.5s infinite; }
        .expiry-badge.expired { background: var(--danger-light); color: var(--danger); animation: pulse 1s infinite; }
        .expiry-badge.no-expiry { background: #E2E8F0; color: var(--text-muted); }
        
        .days-remaining {
            font-size: 0.6rem; font-weight: 600; padding: 1px 5px;
            border-radius: 6px; display: inline-flex; align-items: center; gap: 2px;
        }
        .days-remaining.good { background: var(--success-light); color: var(--success); }
        .days-remaining.warning { background: var(--warning-light); color: var(--warning); animation: pulse 1.5s infinite; }
        .days-remaining.danger { background: var(--danger-light); color: var(--danger); animation: pulse 1s infinite; }
        .days-remaining.forever { background: #E2E8F0; color: var(--text-muted); }
        
        .batch-number {
            font-family: monospace; font-size: 0.6rem; font-weight: 600;
            padding: 1px 6px; border-radius: 3px;
            background: var(--primary-light); color: var(--primary);
        }
        
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
        
        /* ================================================================ */
        /* ACTION BUTTONS */
        /* ================================================================ */
        .action-group {
            display: flex;
            flex-direction: column;
            gap: 3px;
            align-items: center;
        }
        
        .action-btn {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.55rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
            color: white;
            height: 22px;
            width: 60px;
            white-space: nowrap;
        }
        .action-btn.view { background: var(--purple); }
        .action-btn.view:hover { background: #6D28D9; transform: scale(1.03); }
        .action-btn.edit { background: var(--warning); }
        .action-btn.edit:hover { background: #B45309; transform: scale(1.03); }
        .action-btn.delete { background: var(--danger); }
        .action-btn.delete:hover { background: #991B1B; transform: scale(1.03); }
        .action-btn i { font-size: 0.5rem; }
        
        .action-btn-sm {
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 0.5rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 2px;
            color: white;
            height: 20px;
            width: 50px;
            white-space: nowrap;
        }
        .action-btn-sm.edit { background: var(--warning); }
        .action-btn-sm.edit:hover { background: #B45309; transform: scale(1.03); }
        .action-btn-sm.delete { background: var(--danger); }
        .action-btn-sm.delete:hover { background: #991B1B; transform: scale(1.03); }
        .action-btn-sm i { font-size: 0.45rem; }
        
        /* ================================================================ */
        /* MODAL */
        /* ================================================================ */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 1000;
            justify-content: center; align-items: center;
            animation: fadeIn 0.3s ease;
        }
        .modal-overlay.show { display: flex; }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: var(--bg-card); border-radius: 14px;
            padding: 20px 24px; max-width: 850px; width: 95%;
            max-height: 90vh; overflow-y: auto;
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            padding-bottom: 10px; border-bottom: 2px solid var(--border-color);
            margin-bottom: 14px;
        }
        
        .modal-title { font-size: 1rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
        .modal-title i { color: var(--primary); }
        .modal-title .branch-name-in-title { font-size: 0.75rem; font-weight: 600; color: var(--primary); margin-left: 8px; }
        
        .modal-close {
            background: none; border: none; font-size: 1.3rem;
            cursor: pointer; color: var(--text-secondary);
            transition: all 0.3s ease; text-decoration: none;
        }
        .modal-close:hover { color: var(--danger); transform: rotate(90deg); }
        
        .form-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .form-grid .full-width { grid-column: 1 / -1; }
        
        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: block;
        }
        .form-label .required { color: var(--danger); margin-left: 2px; }
        
        .form-control {
            width: 100%; padding: 8px 12px;
            border: 2px solid var(--border-color); border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            outline: none; background: var(--bg-card);
            color: var(--text-primary); height: 42px;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        .form-control::placeholder { font-size: 0.85rem; color: var(--text-muted); }
        
        .help-text { font-size: 0.6rem; color: var(--text-muted); margin-top: 3px; }
        
        .form-actions {
            display: flex; gap: 8px; margin-top: 16px;
            padding-top: 12px; border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .btn-save {
            background: var(--primary); color: white;
            padding: 10px 28px; border-radius: 8px;
            font-weight: 600; font-size: 0.9rem; border: none;
            cursor: pointer; transition: all 0.3s ease;
            display: inline-flex; align-items: center; gap: 8px;
            height: 44px;
        }
        .btn-save:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
        
        .btn-cancel {
            background: transparent; color: var(--text-secondary);
            border: 2px solid var(--border-color); padding: 10px 24px;
            border-radius: 8px; font-weight: 600; font-size: 0.9rem;
            cursor: pointer; transition: all 0.3s ease;
            text-decoration: none; height: 44px;
        }
        .btn-cancel:hover { border-color: var(--danger); color: var(--danger); }
        
        .btn-generate {
            background: var(--primary); color: white; border: none;
            border-radius: 6px; padding: 8px 14px; font-size: 0.8rem;
            font-weight: 600; cursor: pointer; transition: all 0.3s ease;
            white-space: nowrap; height: 42px;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-generate:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
        
        .btn-toggle {
            background: var(--primary); color: white; border: none;
            border-radius: 6px; padding: 6px 12px; font-size: 0.75rem;
            font-weight: 600; cursor: pointer; white-space: nowrap;
            height: 42px; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-toggle:hover { background: var(--primary-dark); }
        
        .category-input-group {
            display: flex; gap: 6px; align-items: center;
        }
        .category-input-group .form-control { flex: 1; }
        
        .batch-input-group {
            display: flex; gap: 6px; align-items: center;
        }
        .batch-input-group .form-control { flex: 1; }
        
        .autocomplete-container {
            position: relative; width: 100%;
        }
        .autocomplete-list {
            position: absolute; top: 100%; left: 0; right: 0;
            background: var(--bg-card); border: 2px solid var(--border-color);
            border-top: none; border-radius: 0 0 6px 6px;
            z-index: 100; max-height: 180px; overflow-y: auto;
            display: none; box-shadow: var(--shadow-md);
        }
        .autocomplete-list.show { display: block; }
        .autocomplete-item {
            padding: 8px 14px; cursor: pointer; border-bottom: 1px solid var(--border-color);
            font-size: 0.82rem; transition: all 0.2s ease;
            color: var(--text-primary);
        }
        .autocomplete-item:hover { background: var(--primary-light); color: var(--primary); }
        .autocomplete-item .item-detail { font-size: 0.65rem; color: var(--text-muted); display: block; }
        .autocomplete-item.active {
            background: var(--primary);
            color: white;
        }
        .autocomplete-item.active .item-detail {
            color: rgba(255,255,255,0.7);
        }
        
        .view-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 8px; margin-bottom: 12px;
        }
        .view-item {
            padding: 6px 10px; background: var(--bg-body);
            border-radius: 4px; border: 1px solid var(--border-color);
        }
        .view-item .label {
            font-size: 0.5rem; text-transform: uppercase;
            color: var(--text-secondary); font-weight: 600;
            letter-spacing: 0.05em;
        }
        .view-item .value { font-size: 0.8rem; font-weight: 600; color: var(--text-primary); margin-top: 2px; }
        .view-item.full-width { grid-column: 1 / -1; }
        
        .batches-table-wrap { overflow-x: auto; margin-top: 8px; }
        .batches-table {
            width: 100%; border-collapse: collapse; font-size: 0.7rem;
        }
        .batches-table thead th {
            background: var(--primary); color: white;
            padding: 4px 8px; font-size: 0.55rem;
            text-transform: uppercase; font-weight: 700; text-align: left;
        }
        .batches-table tbody td { padding: 4px 8px; border-bottom: 1px solid var(--border-color); }
        .batches-table tbody tr:nth-child(even) { background: var(--primary-light); }
        .batches-table tbody tr:hover td { background: var(--success-light); }
        
        .empty-state { text-align: center; padding: 30px 20px; color: var(--text-secondary); }
        .empty-state i { font-size: 2rem; color: var(--border-color); display: block; margin-bottom: 8px; }
        
        .footer {
            padding: 10px 0; border-top: 1px solid var(--border-color);
            margin-top: 16px; text-align: center;
            font-size: 0.6rem; color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================ */
        /* TABS */
        /* ================================================================ */
        .tabs-container {
            display: flex;
            gap: 4px;
            background: var(--bg-card);
            border-radius: 12px;
            padding: 4px;
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
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
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 14px; }
            .embedded-header { left: 0; }
            .embedded-header .search-wrapper { max-width: 300px; }
            .header-actions { flex-wrap: wrap; gap: 8px; }
            .btn-action { min-width: 140px; padding: 8px 14px; font-size: 0.75rem; height: 42px; }
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 10px 12px; min-height: 60px; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .search-form { flex-direction: column; align-items: stretch; }
            .search-form input, .search-form select { min-width: 100%; }
            .card { padding: 10px 12px; }
            .page-header-box { flex-direction: column; align-items: stretch !important; padding: 14px 16px; }
            .page-header-box .page-title { font-size: 1.1rem; }
            .embedded-header .datetime { display: none; }
            .header-actions { flex-direction: column; align-items: stretch; width: 100%; gap: 6px; }
            .btn-action { width: 100%; min-width: unset; justify-content: center; padding: 10px 16px; height: 44px; font-size: 0.8rem; }
            .form-grid { grid-template-columns: 1fr; }
            .form-grid .full-width { grid-column: 1; }
            .form-actions { flex-direction: column; }
            .form-actions .btn-save, .form-actions .btn-cancel { width: 100%; justify-content: center; }
            .branch-info-card { flex-direction: column; align-items: flex-start !important; }
            .branch-info-card .branch-stats { margin-top: 4px; }
            .col-actions { min-width: 70px; }
            .action-btn { width: 48px; font-size: 0.5rem; height: 20px; }
            .col-added-by { min-width: 80px; }
            .tabs-container { flex-direction: column; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
            .stat-card { padding: 8px 10px; min-height: 50px; }
            .stat-card .stat-number { font-size: 0.9rem; }
            .data-table { min-width: 750px; font-size: 0.6rem; }
            .data-table th, .data-table td { padding: 3px 5px; }
            .modal-content { padding: 10px; }
            .col-actions { min-width: 60px; }
            .action-btn { width: 40px; font-size: 0.45rem; height: 18px; padding: 1px 4px; }
            .action-btn i { font-size: 0.4rem; }
            .col-added-by { min-width: 60px; }
            .btn-action { height: 40px; font-size: 0.7rem; padding: 6px 10px; }
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
            <input type="text" id="searchInput" placeholder="Search inventory...">
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

    <!-- BRANCH INFO -->
    <div class="branch-info-card animate-fade-in-up">
        <div class="branch-title">
            <i class="fas fa-store-alt"></i>
            <?php if ($selected_branch_id === 'all'): ?>
                🌐 All Branches
            <?php else: ?>
                <?= htmlspecialchars($display_branch_name) ?>
            <?php endif; ?>
        </div>
        <div class="branch-stats">
            <span class="stat-item"><i class="fas fa-pills"></i> <strong><?= $total_medicines ?></strong> Medicines</span>
            <span class="stat-item"><i class="fas fa-tools"></i> <strong><?= $total_equipment ?></strong> Equipment</span>
            <span class="stat-item"><i class="fas fa-coins"></i> <strong>TSh <?= formatMoneyShort($total_inventory_value) ?></strong></span>
            <span class="stat-item"><i class="fas fa-check-circle"></i> <strong><?= $med_in_stock + $equip_in_stock ?></strong> in stock</span>
            <?php if (($med_low_stock + $equip_low_stock) > 0): ?>
                <span class="stat-item" style="background:rgba(245,158,11,0.3);"><i class="fas fa-exclamation-triangle"></i> <strong><?= $med_low_stock + $equip_low_stock ?></strong> low</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- PAGE HEADER -->
    <div class="page-header-box animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-warehouse"></i>
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
                <strong><?= $total_medicines + $total_equipment ?></strong> total entries
                <span class="header-badge medicines">
                    <i class="fas fa-pills"></i> <?= $med_in_stock + $equip_in_stock ?> In Stock
                </span>
                <span class="header-badge value">
                    <i class="fas fa-coins"></i> TSh <?= formatMoneyShort($total_inventory_value) ?>
                </span>
                <span class="header-badge stock">
                    <i class="fas fa-exclamation-triangle"></i> <?= $med_low_stock + $equip_low_stock ?> Low Stock
                </span>
            </p>
        </div>
        <div class="header-actions">
            <a href="<?= $med_add_url ?>" class="btn-action btn-add-medicine">
                <i class="fas fa-plus-circle"></i> Add Medicine
            </a>
            <a href="<?= $equip_add_url ?>" class="btn-action btn-add-equipment">
                <i class="fas fa-plus-circle"></i> Add Equipment
            </a>
            <a href="<?= $history_url ?>" class="btn-action btn-purchase-history">
                <i class="fas fa-history"></i> Purchase History
            </a>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>" id="messageBox">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <span><?= $message ?></span>
            <button class="message-close" onclick="closeMessage()">&times;</button>
        </div>
    <?php endif; ?>

    <!-- TABS -->
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

    <!-- ================================================================ -->
    <!-- TAB CONTENT: MEDICINES -->
    <!-- ================================================================ -->
    <div id="tab-medicines" class="tab-content <?= $active_tab === 'medicines' ? 'active' : '' ?>">
        
        <div class="stats-grid animate-fade-in-up">
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
                <div class="stat-label">Has Expired Batches</div>
                <div class="stat-sub">Some batches expired</div>
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

        <!-- Filters -->
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
                <?php if (!empty($stock_filter) || !empty($expiry_filter) || !empty($status_filter) || !empty($category_filter)): ?>
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

        <!-- Medicine Table -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list title-blue"></i> Medicine List
                    <span class="result-count">(<strong><?= count($medicines) ?></strong> entries)</span>
                </h3>
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
                                    <th class="col-added-by">Added By</th>
                                    <th class="col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $counter = 1; ?>
                                <?php foreach ($medicines as $item): ?>
                                    <?php
                                        $active_qty = $item['total_quantity'] ?? 0;
                                        $branch_name = $item['branch_name'] ?? 'Unknown';
                                        $batch_ids = $item['batch_ids'] ?? '';
                                        $first_batch_id = $batch_ids ? explode(',', $batch_ids)[0] : 0;
                                        
                                        $category_display = !empty($item['category']) ? $item['category'] : 'N/A';
                                        
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
                                        
                                        $display_status = $item['computed_status'] ?? 'active';
                                        $price_display = ($item['selling_price'] ?? 0) > 0 ? number_format($item['selling_price'], 0) : 'FREE';
                                        
                                        $added_by_display = !empty($item['added_by_full_name']) ? $item['added_by_full_name'] : ($item['added_by_name'] ?? 'System');
                                        
                                        $view_data_json = htmlspecialchars(json_encode([
                                            'id' => $item['id'],
                                            'name' => $item['medication_name'],
                                            'category' => $category_display,
                                            'unit' => $item['unit'] ?? 'pcs',
                                            'reorder_level' => $item['reorder_level'],
                                            'selling_price' => $item['selling_price'] ?? 0,
                                            'supplier' => $item['supplier'] ?? 'N/A',
                                            'status' => $display_status,
                                            'active_qty' => $active_qty,
                                            'branch_id' => $item['branch_id'],
                                            'branch_name' => $branch_name,
                                            'batch_ids' => $batch_ids,
                                            'batch_numbers' => $batch_numbers,
                                            'batch_quantities' => $item['batch_quantities'] ?? '',
                                            'batch_expiries' => $item['batch_expiries'] ?? '',
                                            'batch_statuses' => $item['batch_statuses'] ?? ''
                                        ]), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td class="col-sno"><?= $counter++ ?></td>
                                        <td class="col-name">
                                            <strong><?= htmlspecialchars($item['medication_name']) ?></strong>
                                            <?php if ($batch_count > 1): ?>
                                                <span style="font-size:0.5rem;margin-left:3px;background:var(--primary-light);color:var(--primary);padding:0px 6px;border-radius:8px;display:inline-block;">
                                                    <?= $batch_count ?> batches
                                                </span>
                                            <?php endif; ?>
                                            <div style="font-size:0.6rem;color:var(--text-secondary);"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></div>
                                        </td>
                                        <td class="col-category"><?= htmlspecialchars($category_display) ?></td>
                                        <td class="col-branch">
                                            <span style="font-weight:600;color:var(--primary);font-size:0.65rem;">🏥 <?= htmlspecialchars($branch_name) ?></span>
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
                                                    <span style="font-size:0.55rem;color:var(--text-secondary);">+<?= $batch_count - 1 ?> more</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color:var(--text-secondary);font-size:0.6rem;">N/A</span>
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
                                        <td class="col-added-by">
                                            <span class="added-by-tag">
                                                <i class="fas fa-user-circle"></i>
                                                <?= htmlspecialchars($added_by_display) ?>
                                            </span>
                                        </td>
                                        <td class="col-actions">
                                            <div class="action-group">
                                                <button type="button" class="action-btn view" 
                                                        onclick='openViewModal(<?= $view_data_json ?>, "medicine")'
                                                        title="View Batches">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button type="button" class="action-btn edit" 
                                                        onclick="openEditMedicineModal(<?= $first_batch_id ?>, '<?= htmlspecialchars($item['medication_name']) ?>')"
                                                        title="Edit Batch">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button type="button" class="action-btn delete" 
                                                        onclick="deleteMedicine(<?= $first_batch_id ?>, '<?= addslashes($item['medication_name']) ?>')"
                                                        title="Delete Batch">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
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
                    <p style="color:var(--text-secondary);font-size:0.8rem;">Click "Add Medicine" to view or create a purchase</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TAB CONTENT: EQUIPMENT -->
    <!-- ================================================================ -->
    <div id="tab-equipment" class="tab-content <?= $active_tab === 'equipment' ? 'active' : '' ?>">
        
        <div class="stats-grid animate-fade-in-up">
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
                <div class="stat-sub">Below reorder level</div>
            </a>
            <a href="inventory.php?tab=equipment&stock=out&branch=<?= $selected_branch_id ?>" class="stat-card red">
                <span class="stat-icon"><i class="fas fa-times-circle"></i></span>
                <div class="stat-number"><?= $equip_out_of_stock ?></div>
                <div class="stat-label">Out of Stock</div>
                <div class="stat-sub">Quantity = 0</div>
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
                <div class="stat-label">Has Expired Batches</div>
                <div class="stat-sub">Some batches expired</div>
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

        <!-- Filters -->
        <div class="card animate-fade-in-up">
            <div class="filter-group">
                <a href="inventory.php?tab=equipment&branch=<?= $selected_branch_id ?>" class="filter-btn <?= empty($status_filter) && empty($stock_filter) && empty($expiry_filter) ? 'active' : '' ?>">All</a>
                <a href="inventory.php?tab=equipment&status=active&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $status_filter === 'active' ? 'active' : '' ?>">Active</a>
                <a href="inventory.php?tab=equipment&status=inactive&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $status_filter === 'inactive' ? 'active' : '' ?>">Inactive</a>
                <a href="inventory.php?tab=equipment&stock=low&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $stock_filter === 'low' ? 'active' : '' ?>">Low Stock</a>
                <a href="inventory.php?tab=equipment&stock=out&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $stock_filter === 'out' ? 'active' : '' ?>">Out of Stock</a>
                <a href="inventory.php?tab=equipment&expiry=expiring&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $expiry_filter === 'expiring' ? 'active' : '' ?>">Expiring Soon</a>
                <a href="inventory.php?tab=equipment&expiry=expired&branch=<?= $selected_branch_id ?>" class="filter-btn <?= $expiry_filter === 'expired' ? 'active' : '' ?>" style="border-color:#7F1D1D;color:#7F1D1D;">
                    <i class="fas fa-skull"></i> Has Expired
                </a>
                <?php if (!empty($stock_filter) || !empty($expiry_filter) || !empty($status_filter) || !empty($category_filter)): ?>
                    <a href="inventory.php?tab=equipment&branch=<?= $selected_branch_id ?>" class="filter-btn clear-filter">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
            
            <form method="GET" class="search-form">
                <input type="hidden" name="tab" value="equipment">
                <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
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
                <a href="inventory.php?tab=equipment&branch=<?= $selected_branch_id ?>" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </form>
        </div>

        <!-- Equipment Table -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list title-purple"></i> Equipment List
                    <span class="result-count">(<strong><?= count($equipment) ?></strong> entries)</span>
                </h3>
                <div class="scroll-arrows">
                    <button class="scroll-arrow-btn" onclick="scrollTable('left')" title="Scroll Left">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="scroll-arrow-btn" onclick="scrollTable('right')" title="Scroll Right">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <?php if (count($equipment) > 0): ?>
                <div class="table-wrapper">
                    <div class="table-scroll-container" id="tableScrollContainer2">
                        <table class="data-table" id="equipmentTable">
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
                                    <th class="col-added-by">Added By</th>
                                    <th class="col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $counter = 1; ?>
                                <?php foreach ($equipment as $item): ?>
                                    <?php
                                        $active_qty = $item['total_quantity'] ?? 0;
                                        $branch_name = $item['branch_name'] ?? 'Unknown';
                                        $batch_ids = $item['batch_ids'] ?? '';
                                        $first_batch_id = $batch_ids ? explode(',', $batch_ids)[0] : 0;
                                        
                                        $category_display = !empty($item['category']) ? $item['category'] : 'N/A';
                                        
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
                                        
                                        $display_status = $item['computed_status'] ?? 'active';
                                        $price_display = ($item['selling_price'] ?? 0) > 0 ? number_format($item['selling_price'], 0) : 'FREE';
                                        
                                        $added_by_display = !empty($item['added_by_full_name']) ? $item['added_by_full_name'] : ($item['added_by_name'] ?? 'System');
                                        
                                        $view_data_json = htmlspecialchars(json_encode([
                                            'id' => $item['id'],
                                            'name' => $item['equipment_name'],
                                            'category' => $category_display,
                                            'unit' => $item['unit'] ?? 'pcs',
                                            'reorder_level' => $item['reorder_level'],
                                            'selling_price' => $item['selling_price'] ?? 0,
                                            'supplier' => $item['supplier'] ?? 'N/A',
                                            'status' => $display_status,
                                            'active_qty' => $active_qty,
                                            'branch_id' => $item['branch_id'],
                                            'branch_name' => $branch_name,
                                            'batch_ids' => $batch_ids,
                                            'batch_numbers' => $batch_numbers,
                                            'batch_quantities' => $item['batch_quantities'] ?? '',
                                            'batch_expiries' => $item['batch_expiries'] ?? '',
                                            'batch_statuses' => $item['batch_statuses'] ?? ''
                                        ]), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td class="col-sno"><?= $counter++ ?></td>
                                        <td class="col-name">
                                            <strong><?= htmlspecialchars($item['equipment_name']) ?></strong>
                                            <?php if ($batch_count > 1): ?>
                                                <span style="font-size:0.5rem;margin-left:3px;background:var(--primary-light);color:var(--primary);padding:0px 6px;border-radius:8px;display:inline-block;">
                                                    <?= $batch_count ?> batches
                                                </span>
                                            <?php endif; ?>
                                            <div style="font-size:0.6rem;color:var(--text-secondary);"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></div>
                                        </td>
                                        <td class="col-category"><?= htmlspecialchars($category_display) ?></td>
                                        <td class="col-branch">
                                            <span style="font-weight:600;color:var(--primary);font-size:0.65rem;">🏥 <?= htmlspecialchars($branch_name) ?></span>
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
                                                    <span style="font-size:0.55rem;color:var(--text-secondary);">+<?= $batch_count - 1 ?> more</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color:var(--text-secondary);font-size:0.6rem;">N/A</span>
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
                                        <td class="col-added-by">
                                            <span class="added-by-tag">
                                                <i class="fas fa-user-circle"></i>
                                                <?= htmlspecialchars($added_by_display) ?>
                                            </span>
                                        </td>
                                        <td class="col-actions">
                                            <div class="action-group">
                                                <button type="button" class="action-btn view" 
                                                        onclick='openViewModal(<?= $view_data_json ?>, "equipment")'
                                                        title="View Batches">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button type="button" class="action-btn edit" 
                                                        onclick="openEditEquipmentModal(<?= $first_batch_id ?>, '<?= htmlspecialchars($item['equipment_name']) ?>')"
                                                        title="Edit Batch">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button type="button" class="action-btn delete" 
                                                        onclick="deleteEquipment(<?= $first_batch_id ?>, '<?= addslashes($item['equipment_name']) ?>')"
                                                        title="Delete Batch">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
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
                    <i class="fas fa-tools"></i>
                    <p>No equipment found</p>
                    <p style="color:var(--text-secondary);font-size:0.8rem;">Click "Add Equipment" to view or create a purchase</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-400 mx-2">|</span>
            Admin Inventory
            <span class="text-gray-400 mx-2">|</span>
            <strong><?= $total_medicines + $total_equipment ?></strong> entries · 
            <strong><?= number_format($total_med_quantity + $total_equip_quantity) ?></strong> units · 
            TSh <strong><?= formatMoney($total_inventory_value) ?></strong>
            <span class="text-gray-400 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- VIEW MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-eye"></i> Details - <span id="viewModalName"></span>
                <span class="branch-name-in-title" id="viewModalBranch"></span>
            </div>
            <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
        </div>
        <div id="viewModalBody"></div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('viewModal')">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- EDIT MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-edit"></i> Edit Batch - <span id="editModalName"></span>
            </div>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <div id="editModalBody">
            <div style="text-align:center;padding:30px;">
                <i class="fas fa-spinner fa-spin" style="font-size:1.8rem;color:var(--primary);"></i>
                <p style="margin-top:8px;color:var(--text-secondary);font-size:0.85rem;">Loading edit form...</p>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
// ================================================================
// DELETE FUNCTIONS
// ================================================================
function deleteMedicine(id, name) {
    if (confirm('Are you sure you want to delete this medicine batch of "' + name + '"?\nThis action cannot be undone!')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;
        
        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_medicine';
        form.appendChild(actionInput);
        
        var idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        
        var confirmedInput = document.createElement('input');
        confirmedInput.type = 'hidden';
        confirmedInput.name = 'confirmed';
        confirmedInput.value = '1';
        form.appendChild(confirmedInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteEquipment(id, name) {
    if (confirm('Are you sure you want to delete this equipment batch of "' + name + '"?\nThis action cannot be undone!')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;
        
        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_equipment';
        form.appendChild(actionInput);
        
        var idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        
        var confirmedInput = document.createElement('input');
        confirmedInput.type = 'hidden';
        confirmedInput.name = 'confirmed';
        confirmedInput.value = '1';
        form.appendChild(confirmedInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

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
        var tab = document.querySelector('.tab-btn.active')?.dataset.tab || 'medicines';
        window.location.href = 'inventory.php?tab=' + tab + '&search=' + encodeURIComponent(query) + '&branch=' + branch;
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
// AUTO-SEARCH - Medicines
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

// ================================================================
// AUTO-SEARCH - Equipment
// ================================================================
(function() {
    var equipmentData = <?= json_encode($all_equipment_names) ?>;
    var input = document.getElementById('equipmentNameInput');
    var autocomplete = document.getElementById('equipmentAutocomplete');
    
    if (!input || !autocomplete) return;
    
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
                <div class="autocomplete-item" data-name="${escapeHtml(item.equipment_name)}">
                    <strong>${escapeHtml(item.equipment_name)}</strong>
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
// CLOSE MESSAGE - AUTO DISAPPEAR AFTER 5 SECONDS
// ================================================================
function closeMessage() {
    var messageBox = document.getElementById('messageBox');
    if (messageBox) {
        messageBox.classList.add('fade-out');
        setTimeout(function() {
            messageBox.style.display = 'none';
        }, 500);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var messageBox = document.getElementById('messageBox');
    if (messageBox) {
        setTimeout(function() {
            closeMessage();
        }, 5000);
    }
});

// ================================================================
// MODAL FUNCTIONS
// ================================================================
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
function toggleCategoryEdit() {
    var select = document.getElementById('editCategorySelect');
    var manual = document.getElementById('editCategoryManual');
    if (!select || !manual) return;
    
    if (manual.style.display !== 'none' && manual.style.display !== '') {
        manual.style.display = 'none';
        select.style.display = 'block';
        select.value = '';
        manual.required = false;
        select.required = true;
    } else {
        manual.style.display = 'block';
        select.style.display = 'none';
        var currentVal = manual.value || '';
        manual.value = currentVal;
        manual.required = true;
        select.required = false;
        manual.focus();
    }
}

function toggleEquipCategoryEdit() {
    var select = document.getElementById('editEquipCategorySelect');
    var manual = document.getElementById('editEquipCategoryManual');
    if (!select || !manual) return;
    
    if (manual.style.display !== 'none' && manual.style.display !== '') {
        manual.style.display = 'none';
        select.style.display = 'block';
        select.value = '';
        manual.required = false;
        select.required = true;
    } else {
        manual.style.display = 'block';
        select.style.display = 'none';
        var currentVal = manual.value || '';
        manual.value = currentVal;
        manual.required = true;
        select.required = false;
        manual.focus();
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
    var prefix = type === 'med' ? 'BATCH' : 'EQP';
    var batch = prefix + '-' + dateStr + '-' + random;
    var inputId = type === 'med' ? 'medBatchInput' : 'equipBatchInput';
    var input = document.getElementById(inputId);
    if (input) input.value = batch;
}

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
// VIEW MODAL
// ================================================================
function openViewModal(data, type) {
    var modal = document.getElementById('viewModal');
    if (!modal) return;
    
    document.getElementById('viewModalName').textContent = data.name || 'Unknown';
    document.getElementById('viewModalBranch').textContent = data.branch_name ? '🏥 ' + data.branch_name : '';
    
    var batchesHtml = '';
    var batchIds = data.batch_ids ? data.batch_ids.split(',') : [];
    var batchNumbers = data.batch_numbers ? data.batch_numbers.split('|') : [];
    var batchQuantities = data.batch_quantities ? data.batch_quantities.split('|') : [];
    var batchExpiries = data.batch_expiries ? data.batch_expiries.split('|') : [];
    var batchStatuses = data.batch_statuses ? data.batch_statuses.split('|') : [];
    
    if (batchIds.length > 0) {
        for (var i = 0; i < batchIds.length; i++) {
            var batchId = batchIds[i] || 0;
            var batchNum = batchNumbers[i] || 'N/A';
            var qty = batchQuantities[i] || 0;
            var expiry = batchExpiries[i] || '';
            var status = batchStatuses[i] || 'active';
            
            var expiryDisplay = 'No Expiry';
            var expiryClass = 'no-expiry';
            var daysLeft = '∞';
            var daysClass = 'forever';
            var statusLabel = 'Active';
            var statusClass = 'active';
            
            if (expiry && expiry !== '0000-00-00') {
                var expiryDate = new Date(expiry);
                var today = new Date();
                var diffTime = expiryDate - today;
                var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                daysLeft = diffDays;
                
                if (diffDays < 0) {
                    expiryClass = 'expired';
                    daysClass = 'danger';
                    statusLabel = 'Expired';
                    statusClass = 'inactive';
                } else if (diffDays <= 30) {
                    expiryClass = 'expiring';
                    daysClass = 'warning';
                    statusLabel = 'Expiring Soon';
                    statusClass = 'active';
                } else {
                    expiryClass = 'valid';
                    daysClass = 'good';
                    statusLabel = 'Valid';
                    statusClass = 'active';
                }
                expiryDisplay = expiry;
            }
            
            if (status === 'inactive') {
                statusLabel = 'Inactive';
                statusClass = 'inactive';
            }
            
            var expiryDateFormatted = expiryDisplay !== 'No Expiry' ? new Date(expiryDisplay).toLocaleDateString('en-GB') : 'No Expiry';
            
            batchesHtml += `
                <tr>
                    <td><span class="batch-number">${escapeHtml(batchNum)}</span></td>
                    <td style="text-align:center;font-weight:600;">${qty}</td>
                    <td>
                        <span class="expiry-badge ${expiryClass}">
                            ${expiryDisplay !== 'No Expiry' ? expiryDateFormatted : '<i class="fas fa-infinity"></i> No Expiry'}
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <span class="days-remaining ${daysClass}">
                            ${daysLeft === '∞' ? '<i class="fas fa-infinity"></i> ∞' : (daysLeft < 0 ? '<i class="fas fa-skull"></i> EXP' : '<i class="fas fa-clock"></i> ' + daysLeft + 'd')}
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <span class="status-badge ${statusClass}">${statusLabel}</span>
                    </td>
                    <td style="text-align:center;white-space:nowrap;">
                        <button class="action-btn-sm edit" 
                                onclick="closeModal('viewModal');openEdit${type === 'medicine' ? 'Medicine' : 'Equipment'}Modal(${batchId}, '${escapeHtml(data.name)}')" 
                                title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn-sm delete" 
                                onclick="closeModal('viewModal');${type === 'medicine' ? 'deleteMedicine' : 'deleteEquipment'}(${batchId}, '${escapeHtml(data.name)}')" 
                                title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }
    } else {
        batchesHtml = `<tr><td colspan="6" style="text-align:center;color:var(--text-secondary);padding:10px;">No batches found</td></tr>`;
    }
    
    var totalQty = data.active_qty || 0;
    var statusBadge = totalQty > 0 ? 'active' : 'inactive';
    var statusText = totalQty > 0 ? 'ACTIVE' : 'INACTIVE';
    var stockBadge = '';
    var stockText = '';
    if (totalQty <= 0) {
        stockBadge = 'out';
        stockText = 'Out of Stock';
    } else if (totalQty <= data.reorder_level) {
        stockBadge = 'low';
        stockText = 'Low Stock';
    } else {
        stockBadge = 'ok';
        stockText = 'In Stock';
    }
    
    var html = `
        <div class="view-grid">
            <div class="view-item full-width">
                <div class="label">Name</div>
                <div class="value">${escapeHtml(data.name || 'N/A')}</div>
            </div>
            <div class="view-item">
                <div class="label">Category</div>
                <div class="value">${escapeHtml(data.category || 'N/A')}</div>
            </div>
            <div class="view-item">
                <div class="label">Unit</div>
                <div class="value">${escapeHtml(data.unit || 'pcs')}</div>
            </div>
            <div class="view-item">
                <div class="label">Total Quantity</div>
                <div class="value">
                    <strong>${totalQty}</strong>
                    <span class="stock-badge ${stockBadge}">${stockText}</span>
                </div>
            </div>
            <div class="view-item">
                <div class="label">Reorder Level</div>
                <div class="value">${data.reorder_level || 0}</div>
            </div>
            <div class="view-item">
                <div class="label">Selling Price</div>
                <div class="value">${data.selling_price > 0 ? 'TSh ' + Number(data.selling_price).toLocaleString() : 'FREE'}</div>
            </div>
            <div class="view-item">
                <div class="label">Supplier</div>
                <div class="value">${escapeHtml(data.supplier || 'N/A')}</div>
            </div>
            <div class="view-item">
                <div class="label">Status</div>
                <div class="value">
                    <span class="status-badge ${statusBadge}">${statusText}</span>
                </div>
            </div>
        </div>
        
        <div style="margin-top:12px;">
            <div style="font-size:0.75rem;font-weight:600;margin-bottom:6px;color:var(--text-primary);">
                <i class="fas fa-layer-group"></i> Batches (${batchIds.length})
                <span style="font-size:0.6rem;font-weight:400;color:var(--text-secondary);margin-left:6px;">(Edit, Delete available per batch)</span>
            </div>
            <div class="batches-table-wrap">
                <table class="batches-table">
                    <thead>
                        <tr>
                            <th style="width:25%;">Batch</th>
                            <th style="width:12%;text-align:center;">Quantity</th>
                            <th style="width:20%;">Expiry</th>
                            <th style="width:12%;text-align:center;">Days</th>
                            <th style="width:13%;text-align:center;">Status</th>
                            <th style="width:18%;text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${batchesHtml}
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    document.getElementById('viewModalBody').innerHTML = html;
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

// ================================================================
// EDIT MEDICINE MODAL
// ================================================================
function openEditMedicineModal(id, name) {
    var modal = document.getElementById('editModal');
    if (!modal) return;
    
    document.getElementById('editModalName').textContent = name || 'Unknown';
    document.getElementById('editModalBody').innerHTML = `
        <div style="text-align:center;padding:30px;">
            <i class="fas fa-spinner fa-spin" style="font-size:1.8rem;color:var(--primary);"></i>
            <p style="margin-top:8px;color:var(--text-secondary);font-size:0.85rem;">Loading edit form for ${escapeHtml(name)}...</p>
        </div>
    `;
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    var formData = new FormData();
    formData.append('action', 'get_edit_medicine_data');
    formData.append('id', id);
    formData.append('branch', '<?= $selected_branch_id ?>');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(function(response) {
        return response.text();
    })
    .then(function(html) {
        document.getElementById('editModalBody').innerHTML = html;
        setTimeout(function() {
            var event = new CustomEvent('modalOpened');
            document.dispatchEvent(event);
        }, 100);
    })
    .catch(function(error) {
        document.getElementById('editModalBody').innerHTML = `
            <div style="text-align:center;padding:30px;color:var(--danger);">
                <i class="fas fa-exclamation-circle" style="font-size:1.8rem;"></i>
                <p style="margin-top:8px;">Error loading edit form: ${error.message}</p>
                <button type="button" class="btn-cancel" style="margin-top:10px;" onclick="closeModal('editModal')">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        `;
    });
}

// ================================================================
// EDIT EQUIPMENT MODAL
// ================================================================
function openEditEquipmentModal(id, name) {
    var modal = document.getElementById('editModal');
    if (!modal) return;
    
    document.getElementById('editModalName').textContent = name || 'Unknown';
    document.getElementById('editModalBody').innerHTML = `
        <div style="text-align:center;padding:30px;">
            <i class="fas fa-spinner fa-spin" style="font-size:1.8rem;color:var(--primary);"></i>
            <p style="margin-top:8px;color:var(--text-secondary);font-size:0.85rem;">Loading edit form for ${escapeHtml(name)}...</p>
        </div>
    `;
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    var formData = new FormData();
    formData.append('action', 'get_edit_equipment_data');
    formData.append('id', id);
    formData.append('branch', '<?= $selected_branch_id ?>');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(function(response) {
        return response.text();
    })
    .then(function(html) {
        document.getElementById('editModalBody').innerHTML = html;
        setTimeout(function() {
            var event = new CustomEvent('modalOpened');
            document.dispatchEvent(event);
        }, 100);
    })
    .catch(function(error) {
        document.getElementById('editModalBody').innerHTML = `
            <div style="text-align:center;padding:30px;color:var(--danger);">
                <i class="fas fa-exclamation-circle" style="font-size:1.8rem;"></i>
                <p style="margin-top:8px;">Error loading edit form: ${error.message}</p>
                <button type="button" class="btn-cancel" style="margin-top:10px;" onclick="closeModal('editModal')">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        `;
    });
}

// ================================================================
// CONSOLE
// ================================================================
console.log('%c📦 Admin - Inventory Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Add Medicine → GREEN - Shows list of IN_PROGRESS medicine purchases', 'font-size:13px; color:#34D399;');
console.log('%c✅ Add Equipment → PURPLE - Shows list of IN_PROGRESS equipment purchases', 'font-size:13px; color:#7C3AED;');
console.log('%c✅ Purchase History → YELLOW', 'font-size:13px; color:#D97706;');
console.log('%c✅ All 3 buttons same size and design', 'font-size:13px; color:#059669;');
console.log('%c✅ FIXED: Equipment without expiry date now display correctly', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Medicines: <?= $total_medicines ?> | Equipment: <?= $total_equipment ?>', 'font-size:13px; color:#059669;');
console.log('%c💰 Total Value: TSh <?= formatMoney($total_inventory_value) ?>', 'font-size:13px; color:#D97706;');
console.log('%c✅ Category fix: Manual categories saved correctly', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>